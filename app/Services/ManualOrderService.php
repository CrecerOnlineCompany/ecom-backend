<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketDetail;
use App\Models\Screening;
use App\Models\Seat;
use App\Models\ScreeningSeat;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidSeatOwnershipException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Manual Order Service
 * 
 * Responsabilidades:
 * - Crear órdenes manuales desde admin sin pasarlas por pago
 * - Generar ticket_number DETERMINÍSTICO usando ticket_sequence
 * - Generar QR codes sin PII
 * - Marcar tickets como confirmed
 * - Marcar screening_seats como sold (con validación de ownership)
 * - Ser atómico (todo o nada) con transacciones y locks
 * - Mantener consistencia con OrderFinalizationService
 * 
 * Flujo:
 * 1. Validar screening, asientos disponibles
 * 2. Crear Order con status=COMPLETED
 * 3. Crear Ticket + TicketDetail por cada asiento
 * 4. Marcar ScreeningSeat como SOLD (validando ownership)
 * 5. Generar QR code
 */
class ManualOrderService
{
    private OrderNumberGenerator $orderNumberGenerator;
    private SeatInventoryService $seatInventoryService;
    private OrderItemPricingService $orderItemPricingService;
    private ?bool $hasTicketSequenceColumn = null;

    public function __construct(
        OrderNumberGenerator $orderNumberGenerator,
        SeatInventoryService $seatInventoryService,
        OrderItemPricingService $orderItemPricingService
    )
    {
        $this->orderNumberGenerator = $orderNumberGenerator;
        $this->seatInventoryService = $seatInventoryService;
        $this->orderItemPricingService = $orderItemPricingService;
    }

    /**
     * Create manual order from admin
     * 
     * Flujo:
     * 1. Validar screening y asientos (ROBUST)
     * 2. Crear Order con status=COMPLETED
     * 3. Crear Ticket + TicketDetail por cada asiento
     * 4. Marcar ScreeningSeat como SOLD (con validación de ownership)
     * 5. Generar QR code (sin externe dependencies)
     * 
     * @param int $screeningId
     * @param array $seatIds Array de IDs de asientos
     * @param string $customerEmail
     * @param string $customerName
     * @param string|null $customerPhone
     * @param array<int,array{code:string,quantity:int}> $products
     * @param string|null $promotionCode
     * @return array ['success', 'message', 'order', 'finalized_tickets']
     */
    public function createManualOrder(
        int $screeningId,
        array $seatIds,
        string $customerEmail,
        string $customerName,
        ?string $customerPhone = null,
        array $products = [],
        ?string $promotionCode = null
    ): array {
        try {
            $seatIds = array_values(array_unique(array_map('intval', $seatIds)));

            if (empty($seatIds)) {
                return [
                    'success' => false,
                    'message' => 'Debe seleccionar al menos un asiento válido',
                    'error_code' => 'INVALID_SEATS',
                ];
            }

            Log::info("Starting manual order creation", [
                'screening_id' => $screeningId,
                'seat_count' => count($seatIds),
                'customer_email' => $customerEmail,
            ]);

            // Step 1: Load and validate screening
            $screening = Screening::lockForUpdate()->findOrFail($screeningId);

            // Step 2: Validate all seats exist and belong to screening room
            $seats = Seat::whereIn('id', $seatIds)
                ->where('room_id', $screening->room_id)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            if ($seats->count() !== count($seatIds)) {
                Log::warning("Invalid seats provided", [
                    'screening_id' => $screeningId,
                    'provided_count' => count($seatIds),
                    'valid_count' => $seats->count(),
                    'screening_room_id' => $screening->room_id,
                    'missing_or_invalid_ids' => array_values(array_diff($seatIds, $seats->keys()->toArray())),
                ]);

                return [
                    'success' => false,
                    'message' => 'Uno o más asientos no son válidos para la sala de esta función',
                    'error_code' => 'INVALID_SEATS',
                ];
            }

            // Step 3: Ensure screening inventory rows exist
            $this->seatInventoryService->ensureScreeningSeats($screeningId);

            // Step 4: Check availability in inventory (screening_seats)
            $inventorySeats = ScreeningSeat::where('screening_id', $screeningId)
                ->whereIn('seat_id', $seatIds)
                ->lockForUpdate()
                ->get();

            if ($inventorySeats->count() !== count($seatIds)) {
                Log::error("Inventory rows missing for manual order seats", [
                    'screening_id' => $screeningId,
                    'expected' => count($seatIds),
                    'found' => $inventorySeats->count(),
                    'seat_ids' => $seatIds,
                ]);

                return [
                    'success' => false,
                    'message' => 'No se pudo validar el inventario de asientos para esta función',
                    'error_code' => 'INVENTORY_MISMATCH',
                ];
            }

            $unavailableSeats = $inventorySeats->filter(function (ScreeningSeat $screeningSeat) {
                return in_array($screeningSeat->status, [ScreeningSeat::STATUS_SOLD, ScreeningSeat::STATUS_RESERVED], true);
            });

            if ($unavailableSeats->isNotEmpty()) {
                Log::warning("Unavailable seats detected", [
                    'screening_id' => $screeningId,
                    'unavailable_count' => $unavailableSeats->count(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Algunos asientos ya están vendidos o reservados',
                    'error_code' => 'SEATS_UNAVAILABLE',
                ];
            }

            // Step 5: Calculate totals from itemized pricing (seats + optional products)
            $pricing = $this->orderItemPricingService->calculatePricedItems($screening, $seatIds, [
                'products' => $products,
                'promotion_code' => trim((string) $promotionCode),
                'customer_email' => $customerEmail,
                'user_id' => auth()->id(),
                'screening_id' => (int) $screening->id,
                'room_id' => (int) $screening->room_id,
                'cinema_id' => (int) ($screening->room?->cinema_id ?? 0),
            ]);
            $basePrice = (float) $screening->price;
            $seatPrices = $pricing['seat_prices'];
            $totalAmount = (float) $pricing['total_amount'];

            // Step 6: Create order within transaction
            DB::beginTransaction();

            try {
                $order = Order::create([
                    'uuid' => \Illuminate\Support\Str::uuid(),
                    'order_number' => $this->orderNumberGenerator->generate(),
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'screening_id' => $screeningId,
                    'total_amount' => $totalAmount,
                    'currency' => 'ARS',
                    'status' => PaymentStatus::STATUS_COMPLETED,
                    'purchase_device' => 'admin_manual',
                    'paid_at' => now(),
                    'completed_at' => now(),
                ]);

                Log::info("Order created", [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'seat_count' => count($seatIds),
                ]);

                $this->orderItemPricingService->syncSeatItems($order, $pricing['items']);

                // Step 7: Create tickets and details
                $finalizedTickets = [];
                $ticketSequence = 1;

                foreach ($seatIds as $seatId) {
                    $seat = $seats->get($seatId);

                    // Create ticket with COMPLETED status (manual order is immediately confirmed)
                    $ticket = Ticket::create([
                        'screening_id' => $screeningId,
                        'order_id' => $order->id,
                        'seat_id' => $seatId,
                        'user_id' => auth()->user()->id ?? null,
                        'ticket_number' => 'TEMP',  // Temporary, will be updated
                        'ticket_sequence' => $ticketSequence,
                        'price' => $seatPrices[$seatId] ?? $basePrice,
                        'status' => PaymentStatus::STATUS_COMPLETED,
                        'customer_name' => $customerName,
                        'customer_email' => $customerEmail,
                        'customer_phone' => $customerPhone,
                        'purchased_at' => now(),
                        'purchase_device' => 'admin_manual',
                    ]);

                    // Generate final ticket_number (DETERMINISTIC, like OrderFinalizationService)
                    $ticketNumber = $this->generateFinalTicketNumber($order, $ticket);
                    $ticket->update(['ticket_number' => $ticketNumber]);

                    // Ensure ticket_detail record exists (consistent with OrderFinalizationService)
                    $this->upsertTicketDetail($ticket, $screeningId);

                    // Mark seat as sold (with ownership validation)
                    $this->markSeatAsSold($screeningId, $seatId, $order->id);

                    // Generate QR code (lightweight, no PNG)
                    $qrCode = $this->generateQRCode($ticketNumber, $ticket);
                    $ticket->update(['qr_code' => $qrCode]);

                    Log::info("Ticket created", [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticketNumber,
                        'sequence' => $ticketSequence,
                    ]);

                    $finalizedTickets[] = [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticketNumber,
                        'seat_id' => $seatId,
                    ];

                    $ticketSequence++;
                }

                DB::commit();

                Log::info("Manual order finalized successfully", [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'ticket_count' => count($finalizedTickets),
                ]);

                return [
                    'success' => true,
                    'message' => "Orden {$order->order_number} creada con éxito",
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'finalized_tickets' => count($finalizedTickets),
                    'details' => $finalizedTickets,
                ];

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error during manual order creation transaction", [
                    'screening_id' => $screeningId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error("Manual order creation failed", [
                'screening_id' => $screeningId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'error_code' => 'CREATION_ERROR',
            ];
        }
    }

    /**
     * Generate final ticket number using ticket_sequence (DETERMINISTIC)
     * 
     * Format: TKT-{order_number}-{ticket_sequence:03d}
     * Example: TKT-ORD-20250214-0001A-001
     */
    private function generateFinalTicketNumber(Order $order, Ticket $ticket): string
    {
        if ($this->hasTicketSequenceColumn()) {
            $sequence = $ticket->ticket_sequence;

            if (is_null($sequence)) {
                $sequence = Ticket::where('order_id', $ticket->order_id)
                    ->whereNotNull('ticket_sequence')
                    ->max('ticket_sequence') ?? 0;
                $sequence++;

                $ticket->update(['ticket_sequence' => $sequence]);
            }
        } else {
            $orderedTicketIds = Ticket::where('order_id', $ticket->order_id)
                ->orderBy('id', 'asc')
                ->pluck('id')
                ->values();

            $position = $orderedTicketIds->search($ticket->id);
            $sequence = $position === false ? 1 : ($position + 1);
        }

        $orderNumber = $order->order_number ?? 'UNKNOWN';

        return sprintf("TKT-%s-%03d", $orderNumber, $sequence);
    }

    /**
     * Generate lightweight QR code (no PNG, no external dependencies)
     * Returns a token that can be looked up
     */
    private function generateQRCode(string $ticketNumber, Ticket $ticket): string
    {
        $token = hash('sha256', $ticketNumber . $ticket->id . time());
        $compact = substr($token, 0, 16);

        return "QR:" . strtoupper($compact);
    }

    /**
     * Mark screening seat as sold with ownership validation
     * (Consistent with OrderFinalizationService)
     * 
     * Validations:
     * - If status=sold and order_id != this order: ERROR
     * - If status=reserved and order_id != this order: ERROR
     * - If status=available: OK, update to sold
     * - If status=sold and order_id == this order: OK (idempotent, skip)
     */
    private function markSeatAsSold(int $screeningId, int $seatId, int $orderId): void
    {
        try {
            $screeningSeat = ScreeningSeat::where('screening_id', $screeningId)
                ->where('seat_id', $seatId)
                ->lockForUpdate()
                ->first();

            if (!$screeningSeat) {
                $screeningSeat = ScreeningSeat::create([
                    'screening_id' => $screeningId,
                    'seat_id' => $seatId,
                    'status' => ScreeningSeat::STATUS_SOLD,
                    'order_id' => $orderId,
                    'reserved_until' => null,
                    'reserved_by_type' => null,
                    'reserved_by_id' => null,
                    'sold_at' => now(),
                ]);

                Log::info("Screening seat row missing, created as sold", [
                    'screening_id' => $screeningId,
                    'seat_id' => $seatId,
                    'order_id' => $orderId,
                    'screening_seat_id' => $screeningSeat->id,
                ]);
                return;
            }

            // Validation 1: If already SOLD by different order, error
            if ($screeningSeat->status === ScreeningSeat::STATUS_SOLD && $screeningSeat->order_id !== $orderId) {
                Log::error("Seat ownership conflict: already sold to different order", [
                    'screening_id' => $screeningId,
                    'seat_id' => $seatId,
                    'current_order_id' => $screeningSeat->order_id,
                    'attempting_order_id' => $orderId,
                ]);

                throw new InvalidSeatOwnershipException(
                    "Seat {$seatId} already sold to order {$screeningSeat->order_id}"
                );
            }

            // Validation 2: If RESERVED by different order, error
            if ($screeningSeat->status === ScreeningSeat::STATUS_RESERVED && $screeningSeat->order_id !== $orderId) {
                Log::error("Seat ownership conflict: reserved by different order", [
                    'screening_id' => $screeningId,
                    'seat_id' => $seatId,
                    'reserved_by' => $screeningSeat->order_id,
                    'attempting_order_id' => $orderId,
                ]);

                throw new InvalidSeatOwnershipException(
                    "Seat {$seatId} reserved by order {$screeningSeat->order_id}"
                );
            }

            // Validation 3: If already sold by SAME order, skip (idempotent)
            if ($screeningSeat->status === ScreeningSeat::STATUS_SOLD && $screeningSeat->order_id === $orderId) {
                Log::debug("Seat already sold by this order, skipping", [
                    'screening_id' => $screeningId,
                    'seat_id' => $seatId,
                    'order_id' => $orderId,
                ]);
                return;
            }

            // Update: Mark as sold
            $screeningSeat->update([
                'status' => ScreeningSeat::STATUS_SOLD,
                'order_id' => $orderId,
                'sold_at' => now(),
            ]);

            Log::info("Screening seat marked as sold", [
                'screening_id' => $screeningId,
                'seat_id' => $seatId,
                'order_id' => $orderId,
            ]);

        } catch (InvalidSeatOwnershipException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Error marking seat as sold", [
                'screening_id' => $screeningId,
                'seat_id' => $seatId,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create or update the ticket_details row associated to a ticket.
     * (Consistent with OrderFinalizationService)
     */
    private function upsertTicketDetail(Ticket $ticket, int $screeningId): void
    {
        $ticket->loadMissing(['seat', 'screening.room']);

        $seatCode = $ticket->seat_code ?: $ticket->seat?->seat_code;
        $rowNumber = $ticket->row_number ?: $ticket->seat?->row_number;
        $seatNumber = $ticket->seat_number ?: $ticket->seat?->seat_number;

        if (!$seatCode || $rowNumber === null || $seatNumber === null) {
            Log::warning("Skipping ticket detail upsert due to missing seat data", [
                'ticket_id' => $ticket->id,
                'screening_id' => $screeningId,
                'seat_id' => $ticket->seat_id,
            ]);
            return;
        }

        TicketDetail::updateOrCreate(
            ['ticket_id' => $ticket->id],
            [
                'screening_id' => $screeningId,
                'seat_id' => $ticket->seat_id,
                'seat_code' => $seatCode,
                'row_number' => (int) $rowNumber,
                'seat_number' => (int) $seatNumber,
                'room_non_number' => (bool) ($ticket->screening?->room?->non_number ?? false),
                'price' => $ticket->price,
                'status' => $this->mapTicketStatusToDetailStatus($ticket->status),
                'qr_code' => $ticket->qr_code,
                'used_at' => $ticket->used_at,
            ]
        );
    }

    /**
     * Map ticket status to detail status (Consistent with OrderFinalizationService)
     */
    private function mapTicketStatusToDetailStatus(?string $ticketStatus): string
    {
        return match ($ticketStatus) {
            'cancelled', PaymentStatus::STATUS_CANCELLED => 'cancelled',
            'used' => 'used',
            default => 'confirmed',
        };
    }

    private function hasTicketSequenceColumn(): bool
    {
        if ($this->hasTicketSequenceColumn !== null) {
            return $this->hasTicketSequenceColumn;
        }

        $this->hasTicketSequenceColumn = Schema::hasColumn('tickets', 'ticket_sequence');

        return $this->hasTicketSequenceColumn;
    }
}
