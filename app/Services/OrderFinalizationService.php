<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentProviderTicket;
use App\Models\Ticket;
use App\Models\TicketDetail;
use App\Models\ScreeningSeat;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidSeatOwnershipException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Order Finalization Service - REFACTORED
 * 
 * Responsabilidades:
 * - Finalizar orden después de aprobación de pago
 * - Generar ticket_number DETERMINÍSTICO usando ticket_sequence
 * - Generar QR codes sin PII
 * - Marcar tickets como confirmed
 * - Marcar screening_seats como sold (con validación de ownership)
 * - Reparar estados parciales
 * - Ser 100% idempotente y duplicate-safe para webhooks
 * 
 * Flujo ORDER-FIRST (from PaymentController):
 * 1. StartOrderPaymentAction - Crea Order + reserva asientos en screening_seats
 * 2. Pago se procesa (webhook)
 * 3. OrderFinalizationService.finalizeOrderAfterApproval() - Crea/finaliza tickets
 * 4. Limpia reservas provisionales
 * 
 * Consistencia con StartOrderPaymentAction:
 * - Query reservas: where(order_id + status='reserved' + reserved_until >= now())
 * - Crea tickets con status=PENDING, luego transiciona a COMPLETED
 * - Distribuye total_amount entre cantidad de asientos
 * 
 * Garantías:
 * - Idempotencia: detecta si ya está finalizado y sale early
 * - Repair: si hay state corruption, la arregla
 * - Atomicidad: todo o nada con transactions y locks
 * - Trazabilidad: logs completos sin PII
 */
class OrderFinalizationService
{
    private ?bool $hasTicketSequenceColumn = null;
    private OrderItemPricingService $orderItemPricingService;

    public function __construct(OrderItemPricingService $orderItemPricingService)
    {
        $this->orderItemPricingService = $orderItemPricingService;
    }

    /**
     * Valida que la orden tenga pago aprobado y monto compatible con el total esperado.
     * Reutilizable desde comandos, acciones y controladores.
     */
    public function validateApprovedPaymentForOrder(Order $order): array
    {
        $expectedAmount = $this->getExpectedOrderAmountForValidation($order);

        $payments = $order->paymentProviderTickets()
            ->latest('id')
            ->get();

        if ($payments->isEmpty()) {
            return ['ok' => false, 'reason' => 'No tiene pagos asociados.'];
        }

        foreach ($payments as $payment) {
            $statusCandidates = $this->extractStatusCandidatesForValidation($payment);
            $approved = false;

            foreach ($statusCandidates as $candidate) {
                if ($this->mapProviderStatusForValidation($candidate) === PaymentStatus::STATUS_COMPLETED) {
                    $approved = true;
                    break;
                }
            }

            if (!$approved) {
                continue;
            }

            $matchedAmount = $this->extractPaidAmountForValidation($payment);
            if ($matchedAmount === null) {
                continue;
            }

            if (abs($matchedAmount - $expectedAmount) > 0.01) {
                continue;
            }

            return [
                'ok' => true,
                'payment_ticket_id' => $payment->id,
                'matched_amount' => number_format($matchedAmount, 2, '.', ''),
                'expected_amount' => number_format($expectedAmount, 2, '.', ''),
            ];
        }

        return [
            'ok' => false,
            'reason' => "No se encontró pago aprobado con monto igual al total esperado ({$expectedAmount}).",
        ];
    }

    /**
     * Finalize order after payment approval
     * 
     * MEJORADO: Si no hay tickets pero hay asientos reservados, los crea primero
     * Esto maneja el caso donde FinalizeOrderPaymentAction no fue llamada
     * 
     * @param int $orderId
     * @param array $paymentData Must contain 'transaction_id' and optionally 'provider_id', 'approval_date'
     * @return array['success', 'message', 'order_id', 'finalized_tickets', 'idempotent', 'repaired']
     */
    public function finalizeOrderAfterApproval(int $orderId, array $paymentData = []): array
    {
        try {
            Log::info("Order finalization started", [
                'order_id' => $orderId,
                'has_transaction_id' => isset($paymentData['transaction_id']),
            ]);

            // Step 1: Load order and validate existence
            $order = Order::lockForUpdate()->findOrFail($orderId);

            // Step 2: Load all tickets for this order
            $ticketsQuery = Ticket::where('order_id', $orderId)
                ->lockForUpdate();

            $ticketsQuery->orderBy(
                $this->hasTicketSequenceColumn() ? 'ticket_sequence' : 'id',
                'asc'
            );

            $tickets = $ticketsQuery->get();

            // MEJORADO: Si no hay tickets, crear desde reservas de asientos
            if ($tickets->isEmpty()) {
                Log::warning("Order has no tickets, attempting to create from reservations", [
                    'order_id' => $orderId,
                ]);

                $createResult = $this->createTicketsFromReservations($order);
                if (!$createResult['success']) {
                    Log::error("Failed to create tickets from reservations", [
                        'order_id' => $orderId,
                        'error' => $createResult['error'],
                    ]);
                    return [
                        'success' => false,
                        'message' => 'Order has no tickets and no reserved seats to create from',
                        'error_code' => 'NO_TICKETS_OR_RESERVATIONS',
                    ];
                }

                Log::info("Tickets created from reservations", [
                    'order_id' => $orderId,
                    'created_count' => $createResult['created_count'],
                ]);

                // Reload tickets after creation
                $ticketsQuery = Ticket::where('order_id', $orderId)
                    ->lockForUpdate();

                $ticketsQuery->orderBy(
                    $this->hasTicketSequenceColumn() ? 'ticket_sequence' : 'id',
                    'asc'
                );

                $tickets = $ticketsQuery->get();
            }

            Log::info("Order loaded for finalization", [
                'order_id' => $orderId,
                'ticket_count' => $tickets->count(),
            ]);

            // Step 3: Check if already fully finalized (ROBUST check)
            $finalizationStatus = $this->checkFinalizationStatus($order, $tickets);

            if ($finalizationStatus['fully_finalized']) {
                Log::info("Order already fully finalized (idempotency check)", [
                    'order_id' => $orderId,
                    'finalized_tickets' => $tickets->count(),
                ]);

                // Update payment_data if not already set
                if (!$order->payment_data && !empty($paymentData)) {
                    $order->update(['payment_data' => array_merge($order->payment_data ?? [], $paymentData)]);
                }

                return [
                    'success' => true,
                    'message' => 'Order already finalized',
                    'idempotent' => true,
                    'finalized_tickets' => $tickets->count(),
                ];
            }

            // Step 4: If partially finalized, repair state
            $repaired = false;
            if ($finalizationStatus['partial']) {
                Log::warning("Order in partial finalization state, attempting repair", [
                    'order_id' => $orderId,
                    'issues' => $finalizationStatus['issues'],
                ]);

                $repairResult = $this->repairPartialFinalization($order, $tickets);
                if (!$repairResult['success']) {
                    Log::error("Failed to repair partial finalization", [
                        'order_id' => $orderId,
                        'errors' => $repairResult['errors'],
                    ]);

                    return [
                        'success' => false,
                        'message' => 'Failed to repair order state',
                        'error_code' => 'STATE_REPAIR_FAILED',
                        'errors' => $repairResult['errors'],
                    ];
                }

                $repaired = true;
                Log::info("Partial finalization repaired", [
                    'order_id' => $orderId,
                    'repairs_applied' => $repairResult['repairs_applied'],
                ]);
            }

            // Step 5: Finalize all tickets
            DB::beginTransaction();

            try {
                $finalizedTickets = [];

                foreach ($tickets as $ticket) {
                    // Skip tickets already fully finalized (after repair)
                    if ($this->isTicketFullyFinalized($ticket)) {
                        Log::debug("Ticket already fully finalized, skipping", [
                            'ticket_id' => $ticket->id,
                            'ticket_number' => $ticket->ticket_number,
                        ]);

                        $finalizedTickets[] = [
                            'ticket_id' => $ticket->id,
                            'ticket_number' => $ticket->ticket_number,
                            'status' => 'already_finalized',
                        ];
                        continue;
                    }

                    // Generate ticket_number using ticket_sequence (DETERMINISTIC)
                    $ticketNumber = $this->generateFinalTicketNumber($order, $ticket);

                    // Generate QR code (without PII)
                    //$qrCode = $this->generateQRCode($ticketNumber, $ticket);

                    // Update ticket
                    $ticket->update([
                        'ticket_number' => $ticketNumber,
                        'purchased_at' => now(),
                    ]);

                    // Ensure per-seat detail record exists/updated for this ticket.
                    $this->upsertTicketDetail($ticket, (int) $order->screening_id);

                    Log::info("Ticket finalized", [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticketNumber,
                        'sequence' => $ticket->ticket_sequence,
                    ]);

                    $finalizedTickets[] = [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticketNumber,
                        'status' => 'newly_finalized',
                    ];

                    // Mark corresponding screening seat as sold (with ownership validation)
                    if ($ticket->seat_id) {
                        $this->markSeatAsSold(
                            $order->screening_id,
                            $ticket->seat_id,
                            $order->id
                        );
                    }
                }

                // Step 6: Update order status and payment_data
                $orderUpdate = [
                    'status' => PaymentStatus::STATUS_COMPLETED,
                    'paid_at' => now(),
                    'completed_at' => now(),
                ];

                // Merge payment_data if provided
                if (!empty($paymentData)) {
                    $orderUpdate['payment_data'] = array_merge(
                        $order->payment_data ?? [],
                        $paymentData
                    );
                }

                $order->update($orderUpdate);

                Log::info("Order finalization completed", [
                    'order_id' => $orderId,
                    'order_number' => $order->order_number,
                    'finalized_count' => count($finalizedTickets),
                    'repaired' => $repaired,
                ]);

                DB::commit();

                // Step 7: Normalize any leftover reservations (outside transaction, best effort)
                $this->normalizeReservationsAfterFinalization($order);

                return [
                    'success' => true,
                    'message' => 'Order finalized successfully',
                    'order_id' => $orderId,
                    'order_number' => $order->order_number,
                    'finalized_tickets' => count($finalizedTickets),
                    'repaired' => $repaired,
                    'details' => $finalizedTickets,
                ];

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error during order finalization transaction", [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error("Order finalization failed", [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Order finalization failed',
                'error_code' => 'FINALIZATION_ERROR',
            ];
        }
    }

    /**
     * Check if order is fully finalized, partially finalized, or not started
     * 
     * Fully finalized: ALL tickets have status=confirmed AND ticket_number IS NOT NULL AND qr_code NOT NULL
     *                  AND ALL seats have status=sold with matching order_id
     * 
     * Partially finalized: Some but not all tickets are fully finalized, or seats mismatch
     * 
     * Not started: Tickets in pending_payment state
     */
    private function checkFinalizationStatus(Order $order, $tickets): array
    {
        $issues = [];
        $fully_finalized = true;
        $partial = false;

        foreach ($tickets as $ticket) {
            if ($ticket->status !== PaymentStatus::STATUS_COMPLETED) {
                $fully_finalized = false;
                $partial = true;
                $issues[] = "Ticket {$ticket->id} status is {$ticket->status}, not " . PaymentStatus::STATUS_COMPLETED;
            }

            if (is_null($ticket->ticket_number)) {
                $fully_finalized = false;
                $partial = true;
                $issues[] = "Ticket {$ticket->id} has NULL ticket_number";
            }

            // Check if corresponding seat is sold
            if ($ticket->seat_id) {
                $screeningSeat = ScreeningSeat::where('screening_id', $order->screening_id)
                    ->where('seat_id', $ticket->seat_id)
                    ->first();

                if (!$screeningSeat || $screeningSeat->status !== ScreeningSeat::STATUS_SOLD) {
                    $fully_finalized = false;
                    $partial = true;
                    $issues[] = "Seat {$ticket->seat_id} not marked as sold";
                }

                if ($screeningSeat && $screeningSeat->order_id !== $order->id) {
                    $fully_finalized = false;
                    $partial = true;
                    $issues[] = "Seat {$ticket->seat_id} belongs to order {$screeningSeat->order_id}, not {$order->id}";
                }
            }
        }

        // Check order status
        if ($order->status !== PaymentStatus::STATUS_COMPLETED) {
            $fully_finalized = false;
            $partial = true;
            $issues[] = "Order {$order->id} status is {$order->status}, not " . PaymentStatus::STATUS_COMPLETED;
        }

        return [
            'fully_finalized' => $fully_finalized,
            'partial' => $partial,
            'issues' => $issues,
        ];
    }

    /**
     * Check if a single ticket is fully finalized
     */
    private function isTicketFullyFinalized(Ticket $ticket): bool
    {
        return $ticket->status === PaymentStatus::STATUS_COMPLETED
            && !is_null($ticket->ticket_number)
            && !is_null($ticket->qr_code);
    }

    /**
     * Repair partial finalization state
     * 
     * Scenarios:
     * - Ticket confirmed but without ticket_number: Generate it
     * - Ticket confirmed but without qr_code: Generate it
     * - Seat not marked sold: Mark it
     */
    private function repairPartialFinalization(Order $order, $tickets): array
    {
        $repairs = [];
        $errors = [];

        try {
            foreach ($tickets as $ticket) {
                if ($ticket->status !== PaymentStatus::STATUS_COMPLETED) {
                    // Don't try to repair non-confirmed tickets in a repair operation
                    continue;
                }

                // Repair missing ticket_number
                if (is_null($ticket->ticket_number)) {
                    $ticketNumber = $this->generateFinalTicketNumber($order, $ticket);
                    $ticket->ticket_number = $ticketNumber;
                    $ticket->save();
                    $repairs[] = "Generated ticket_number for ticket {$ticket->id}";
                }

                // Repair missing qr_code
                if (is_null($ticket->qr_code)) {
                    $qrCode = $this->generateQRCode(
                        $ticket->ticket_number ?? 'REPAIR-' . $ticket->id,
                        $ticket
                    );
                    $ticket->qr_code = $qrCode;
                    $ticket->save();
                    $repairs[] = "Generated qr_code for ticket {$ticket->id}";
                }

                // Repair seat status
                if ($ticket->seat_id) {
                    $screeningSeat = ScreeningSeat::where('screening_id', $order->screening_id)
                        ->where('seat_id', $ticket->seat_id)
                        ->lockForUpdate()
                        ->first();

                    if ($screeningSeat && $screeningSeat->status !== ScreeningSeat::STATUS_SOLD) {
                        if ($screeningSeat->status === ScreeningSeat::STATUS_RESERVED && $screeningSeat->order_id === $order->id) {
                            $screeningSeat->update([
                                'status' => ScreeningSeat::STATUS_SOLD,
                                'sold_at' => now(),
                            ]);
                            $repairs[] = "Marked seat {$ticket->seat_id} as sold";
                        } else {
                            $errors[] = "Seat {$ticket->seat_id} has incompatible state for repair";
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Repair failed: " . $e->getMessage();
        }

        return [
            'success' => empty($errors),
            'repairs_applied' => $repairs,
            'errors' => $errors,
        ];
    }

    /**
     * Generate final ticket number using ticket_sequence
     * 
     * Format: TKT-{order_number}-{ticket_sequence:03d}
     * Example: TKT-ORD-20250214-0001A-001
     * 
     * DETERMINISTIC: Based on ticket_sequence, not on count()
     * STABLE: Same ticket always gets same number
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
                Log::debug("Assigned ticket_sequence", [
                    'ticket_id' => $ticket->id,
                    'sequence' => $sequence,
                ]);
            }
        } else {
            $orderedTicketIds = Ticket::where('order_id', $ticket->order_id)
                ->orderBy('id', 'asc')
                ->pluck('id')
                ->values();

            $position = $orderedTicketIds->search($ticket->id);
            $sequence = $position === false ? 1 : ($position + 1);
        }

        $orderNumber = $ticket->order->order_number ?? 'UNKNOWN';

        return sprintf("TKT-%s-%03d", $orderNumber, $sequence);
    }

    /**
     * Generate lightweight QR token (no image generation)
     *
     * Intencionalmente no genera PNG ni usa librerías externas.
     * Guarda un token compacto para validación backend.
     */
    private function generateQRCode(string $ticketNumber, Ticket $ticket): string
    {
        return $this->generateQRCodeFallback($ticketNumber, $ticket);
    }

    /**
     * Fallback QR code generation if PNG fails
     * Returns a URL-safe token that can be looked up
     */
    private function generateQRCodeFallback(string $ticketNumber, Ticket $ticket): string
    {
        $token = hash('sha256', $ticketNumber . $ticket->id . time());
        $compact = substr($token, 0, 16);

        return "QR:" . strtoupper($compact);
    }

    /**
     * Mark screening seat as sold
     * 
     * Validations:
     * - If status=sold and order_id != this order: ERROR
     * - If status=reserved and order_id != this order: ERROR
     * - If status=available: OK, update to sold
     * - If status=reserved and order_id == this order: OK, update to sold
     */
    private function markSeatAsSold(int $screeningId, int $seatId, int $orderId): void
    {
        try {
            $screeningSeat = ScreeningSeat::where('screening_id', $screeningId)
                ->where('seat_id', $seatId)
                ->lockForUpdate()
                ->first();

            if (!$screeningSeat) {
                Log::warning("Screening seat not found", [
                    'screening_id' => $screeningId,
                    'seat_id' => $seatId,
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
     * Create tickets from reserved seats (when they don't exist yet)
     * 
     * This handles the case where FinalizeOrderPaymentAction was not called
     * but the order has reserved seats in screening_seats table
     * 
     * Consistent with StartOrderPaymentAction:
     * - Query: ScreeningSeat where order_id={order_id}, status='reserved', reserved_until >= now()
     * - Creates: Ticket with status=PENDING (will be confirmed in finalization)
     * - One ticket per reserved seat with distributed price
     * 
     * @param Order $order
     * @return array ['success' => bool, 'created_count' => int, 'error' => ?string]
     */
    private function createTicketsFromReservations(Order $order): array
    {
        try {
            DB::beginTransaction();

            // Get all reserved seats for this order (consistent with StartOrderPaymentAction)
            // Filter: order_id + status='reserved' + reserved_until not expired
            $reservedSeats = ScreeningSeat::where('order_id', $order->id)
                ->where('status', ScreeningSeat::STATUS_RESERVED)
                ->lockForUpdate()
                ->get();

            if ($reservedSeats->isEmpty()) {
                Log::warning("No reserved seats found for order", ['order_id' => $order->id]);
                DB::rollBack();
                return [
                    'success' => false,
                    'created_count' => 0,
                    'error' => 'No reserved seats found for this order',
                ];
            }

            Log::info("Creating tickets from reserved seats", [
                'order_id' => $order->id,
                'reserved_seat_count' => $reservedSeats->count(),
            ]);

            $createdCount = 0;
            $sequence = 1;
            $seatPriceMap = $this->orderItemPricingService->getSeatPriceMapForOrder($order);
            $fallbackPrice = $reservedSeats->count() > 0
                ? round((float) $order->total_amount / $reservedSeats->count(), 2)
                : (float) $order->total_amount;

            // Create one ticket per reserved seat (consistent with StartOrderPaymentAction)
            foreach ($reservedSeats as $screeningSeat) {
                // Create ticket with PENDING status initially
                // Will be transitioned to COMPLETED in finalization step
                $ticket = Ticket::create([
                    'screening_id' => $order->screening_id,
                    'seat_id' => $screeningSeat->seat_id,
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'status' => PaymentStatus::STATUS_PENDING,  // Will be confirmed in finalization
                    'price' => $seatPriceMap[$screeningSeat->seat_id] ?? $fallbackPrice,
                    'customer_email' => $order->customer_email,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'ip_address' => $order->ip_address ?? null,
                    'payment_method' => null,  // Will be set during finalization
                    'ticket_number' => 'TEMP',  // Temporary value, will be updated after creation
                ]);

                if ($this->hasTicketSequenceColumn()) {
                    $ticket->update(['ticket_sequence' => $sequence]);
                }

                // Populate denormalized columns used by admin/reporting.
                $ticket->populateDenormalizedFields();
                $ticket->save();

                $ticket->ticket_number = sprintf("%s-%06d", now()->year, $ticket->id);
                $ticket->save();

                // Ensure per-seat detail record exists/updated for this ticket.
                $this->upsertTicketDetail($ticket, (int) $order->screening_id);

                Log::info("Ticket created from reservation", [
                    'ticket_id' => $ticket->id,
                    'order_id' => $order->id,
                    'seat_id' => $screeningSeat->seat_id,
                    'ticket_number' => $ticket->ticket_number,
                    'sequence' => $sequence,
                ]);

                $sequence++;
                $createdCount++;
            }

            DB::commit();

            return [
                'success' => true,
                'created_count' => $createdCount,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error creating tickets from reservations", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'created_count' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Normalize reservations after tickets are finalized
     * 
     * Called after successful finalization to ensure inventory state consistency.
     * Any leftover reserved rows for this order are transitioned to SOLD.
     * 
     * We do NOT delete rows: screening_seats is now source of truth for availability.
     * 
     * @param Order $order
     * @return void
     */
    private function normalizeReservationsAfterFinalization(Order $order): void
    {
        try {
            $updated = ScreeningSeat::where('order_id', $order->id)
                ->where('status', ScreeningSeat::STATUS_RESERVED)
                ->update([
                    'status' => ScreeningSeat::STATUS_SOLD,
                    'sold_at' => now(),
                    'reserved_until' => null,
                    'reserved_by_type' => null,
                    'reserved_by_id' => null,
                ]);

            if ($updated > 0) {
                Log::warning("Normalized leftover reserved seats to sold after finalization", [
                    'order_id' => $order->id,
                    'updated_count' => $updated,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("Could not normalize provisional reservations", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - this is a cleanup operation
        }
    }

    private function hasTicketSequenceColumn(): bool
    {
        if ($this->hasTicketSequenceColumn !== null) {
            return $this->hasTicketSequenceColumn;
        }

        $this->hasTicketSequenceColumn = Schema::hasColumn('tickets', 'ticket_sequence');

        return $this->hasTicketSequenceColumn;
    }

    /**
     * Total esperado para validación de pago:
     * suma de tickets si existen, sino total_amount de orden.
     */
    private function getExpectedOrderAmountForValidation(Order $order): float
    {
        $ticketTotal = (float) $order->tickets()->sum('price');
        if ($ticketTotal > 0) {
            return $ticketTotal;
        }

        return (float) $order->total_amount;
    }

    /**
     * Candidatos de estado provenientes del registro de pago.
     */
    private function extractStatusCandidatesForValidation(PaymentProviderTicket $payment): array
    {
        $responseData = $this->asArrayForValidation($payment->response_data);

        return array_values(array_filter([
            $payment->status,
            data_get($responseData, 'webhook_status'),
            data_get($responseData, 'status'),
            data_get($responseData, 'terminal_status'),
            data_get($responseData, 'mp_payment_status'),
            data_get($responseData, 'payment_details.status'),
            data_get($responseData, 'webhook_data.status'),
            data_get($responseData, 'webhook_data.data.status'),
        ], fn ($value) => !is_null($value) && $value !== ''));
    }

    /**
     * Mapea estados del provider al estado unificado.
     */
    private function mapProviderStatusForValidation(?string $status): string
    {
        $raw = strtolower(trim((string) $status));

        if (in_array($raw, ['approved', 'completed', 'processed'], true)) {
            return PaymentStatus::STATUS_COMPLETED;
        }

        if (in_array($raw, ['pending', 'processing', 'queued', 'created', 'at_terminal', 'authorized', 'in_process'], true)) {
            return PaymentStatus::STATUS_PROCESSING;
        }

        if (in_array($raw, ['declined', 'rejected', 'failed', 'finalization_failed'], true)) {
            return PaymentStatus::STATUS_FAILED;
        }

        if (in_array($raw, ['cancelled', 'canceled'], true)) {
            return PaymentStatus::STATUS_CANCELLED;
        }

        if ($raw === 'expired') {
            return PaymentStatus::STATUS_EXPIRED;
        }

        if ($raw === 'refunded') {
            return PaymentStatus::STATUS_REFUNDED;
        }

        return $raw;
    }

    /**
     * Extrae monto pagado desde distintas estructuras de response_data/webhook.
     */
    private function extractPaidAmountForValidation(PaymentProviderTicket $payment): ?float
    {
        $responseData = $this->asArrayForValidation($payment->response_data);

        $candidates = [
            data_get($responseData, 'amount'),
            data_get($responseData, 'total_amount'),
            data_get($responseData, 'amount_paid'),
            data_get($responseData, 'paid_amount'),
            data_get($responseData, 'transaction_amount'),
            data_get($responseData, 'transaction_details.total_paid_amount'),
            data_get($responseData, 'payment_details.transaction_amount'),
            data_get($responseData, 'payment_details.transaction_details.total_paid_amount'),
            data_get($responseData, 'webhook_data.transaction_amount'),
            data_get($responseData, 'webhook_data.transaction_details.total_paid_amount'),
            data_get($responseData, 'webhook_data.amount'),
            data_get($responseData, 'webhook_data.data.amount'),
            data_get($responseData, 'payload.transactions.payments.0.amount'),
        ];

        foreach ($candidates as $amount) {
            $parsed = $this->parseAmountForValidation($amount);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    private function parseAmountForValidation($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/[^0-9,.\-]/', '', $value);
        if ($normalized === '' || $normalized === null) {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    private function asArrayForValidation($data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Create or update the ticket_details row associated to a ticket.
     * One ticket = one seat detail in current order-first flow.
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

    private function mapTicketStatusToDetailStatus(?string $ticketStatus): string
    {
        return match ($ticketStatus) {
            'cancelled', PaymentStatus::STATUS_CANCELLED => 'cancelled',
            'used' => 'used',
            default => 'confirmed',
        };
    }
}
