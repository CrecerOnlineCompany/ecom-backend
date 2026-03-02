<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\ScreeningSeat;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidSeatOwnershipException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
    const QR_VERSION = '1.0';
    const QR_HMAC_KEY_ENV = 'QR_SIGNATURE_KEY';

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
            $tickets = Ticket::where('order_id', $orderId)
                ->lockForUpdate()
                ->orderBy('ticket_sequence', 'asc')  // Deterministic order
                ->get();

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
                $tickets = Ticket::where('order_id', $orderId)
                    ->lockForUpdate()
                    ->orderBy('ticket_sequence', 'asc')
                    ->get();
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
                    $qrCode = $this->generateQRCode($ticketNumber, $ticket);

                    // Update ticket
                    $ticket->update([
                        'ticket_number' => $ticketNumber,
                        'qr_code' => $qrCode,
                        'status' => PaymentStatus::STATUS_COMPLETED,
                        'purchased_at' => now(),
                    ]);

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

                // Step 7: Clean up provisioned reservations (outside transaction, best effort)
                $this->deleteReservationsAfterFinalization($order);

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

            if (is_null($ticket->qr_code)) {
                $fully_finalized = false;
                $partial = true;
                $issues[] = "Ticket {$ticket->id} has NULL qr_code";
            }

            // Check if corresponding seat is sold
            if ($ticket->seat_id) {
                $screeningSeat = ScreeningSeat::where('screening_id', $order->screening_id)
                    ->where('seat_id', $ticket->seat_id)
                    ->first();

                if (!$screeningSeat || $screeningSeat->status !== 'sold') {
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
            if (!$fully_finalized) {
                $partial = true;
            }
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

                    if ($screeningSeat && $screeningSeat->status !== 'sold') {
                        if ($screeningSeat->status === 'reserved' && $screeningSeat->order_id === $order->id) {
                            $screeningSeat->update([
                                'status' => 'sold',
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
        // If ticket_sequence not set, calculate it
        $sequence = $ticket->ticket_sequence;

        if (is_null($sequence)) {
            // Count confirmed tickets to assign sequence if missing
            $sequence = $ticket->order()
                ->pluck('ticket_sequence')
                ->filter()
                ->max() ?? 0;

            $sequence++;

            // Save the sequence
            $ticket->update(['ticket_sequence' => $sequence]);
            Log::debug("Assigned ticket_sequence", [
                'ticket_id' => $ticket->id,
                'sequence' => $sequence,
            ]);
        }

        $orderNumber = $ticket->order->order_number ?? 'UNKNOWN';

        return sprintf("TKT-%s-%03d", $orderNumber, $sequence);
    }

    /**
     * Generate QR code with proper capture and NO PII
     * 
     * Uses output buffering to capture QRcode::png() output
     * Data encoded: ticket_id, ticket_number, signature (HMAC)
     * Excludes: email, phone, customer name
     */
    private function generateQRCode(string $ticketNumber, Ticket $ticket): string
    {
        try {
            $qrData = $this->buildQRData($ticketNumber, $ticket);

            // Capture output using ob_start
            ob_start();
            \QRcode::png($qrData, false, QR_ECLEVEL_H, 4, 2);
            $qrImage = ob_get_clean();

            if ($qrImage === false) {
                throw new \Exception("Failed to capture QRcode output");
            }

            return base64_encode($qrImage);

        } catch (\Exception $e) {
            Log::warning("Failed to generate QR code", [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback: use signature-based code (queryable by backend)
            return $this->generateQRCodeFallback($ticketNumber, $ticket);
        }
    }

    /**
     * Build QR data WITHOUT PII
     * 
     * Data: ticket_id, ticket_number, signature
     * Signature: HMAC-SHA256 of ticket_id + ticket_number with app key
     */
    private function buildQRData(string $ticketNumber, Ticket $ticket): string
    {
        // Generate HMAC signature for verification
        $signatureData = $ticket->id . "|" . $ticketNumber;
        $signature = hash_hmac(
            'sha256',
            $signatureData,
            env(self::QR_HMAC_KEY_ENV, config('app.key'))
        );

        return json_encode([
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticketNumber,
            'screening_id' => $ticket->screening_id,
            'seat_id' => $ticket->seat_id,
            'signature' => $signature,
            'version' => self::QR_VERSION,
        ]);
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
            if ($screeningSeat->status === 'sold' && $screeningSeat->order_id !== $orderId) {
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
            if ($screeningSeat->status === 'reserved' && $screeningSeat->order_id !== $orderId) {
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
            if ($screeningSeat->status === 'sold' && $screeningSeat->order_id === $orderId) {
                Log::debug("Seat already sold by this order, skipping", [
                    'screening_id' => $screeningId,
                    'seat_id' => $seatId,
                    'order_id' => $orderId,
                ]);
                return;
            }

            // Update: Mark as sold
            $screeningSeat->update([
                'status' => 'sold',
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
                ->where('status', 'reserved')
                ->where(function ($query) {
                    // Include seats with valid reservation (reserved_until >= now())
                    $query->whereNull('reserved_until')  // No TTL = never expires
                        ->orWhere('reserved_until', '>=', now());
                })
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

            // Create one ticket per reserved seat (consistent with StartOrderPaymentAction)
            foreach ($reservedSeats as $screeningSeat) {
                // Create ticket with PENDING status initially
                // Will be transitioned to COMPLETED in finalization step
                $ticket = Ticket::create([
                    'screening_id' => $order->screening_id,
                    'seat_id' => $screeningSeat->seat_id,
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'ticket_sequence' => $sequence,
                    'status' => PaymentStatus::STATUS_PENDING,  // Will be confirmed in finalization
                    'price' => $order->total_amount / $reservedSeats->count(),
                    'customer_email' => $order->customer_email,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'ip_address' => $order->ip_address ?? null,
                    'payment_method' => null,  // Will be set during finalization
                ]);

                Log::info("Ticket created from reservation", [
                    'ticket_id' => $ticket->id,
                    'order_id' => $order->id,
                    'seat_id' => $screeningSeat->seat_id,
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
     * Delete reservations after tickets are created/finalized
     * 
     * Called after successful ticket finalization to clean up screening_seats
     * entries that are no longer needed (transition from provisional to finalized state)
     * 
     * After finalization, reserved seats should have already been transitioned to 'sold'
     * by markSeatAsSold(). Any remaining 'reserved' entries are artifacts that can safely be deleted.
     * 
     * Note: We don't filter by reserved_until here because:
     * - This is a post-finalization cleanup operation
     * - Reserved seats should already be 'sold' at this point
     * - Any 'reserved' entries here are orphaned artifacts
     * 
     * @param Order $order
     * @return void
     */
    private function deleteReservationsAfterFinalization(Order $order): void
    {
        try {
            // Delete all remaining 'reserved' seats (should be artifacts/orphans)
            // Consistent with StartOrderPaymentAction that creates them
            $deleted = ScreeningSeat::where('order_id', $order->id)
                ->where('status', 'reserved')
                ->delete();

            if ($deleted > 0) {
                Log::info("Deleted provisional reservations after finalization", [
                    'order_id' => $order->id,
                    'deleted_count' => $deleted,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("Could not delete provisional reservations", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - this is a cleanup operation
        }
    }
}

