<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\ScreeningSeat;
use App\Models\Screening;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de Finalización de Órdenes
 * 
 * Responsable de:
 * - Generar ticket_number definitivos por asiento
 * - Generar QR codes
 * - Marcar tickets como confirmed/sold
 * - Transicionar screening_seats a sold
 * - Ser completamente idempotente (webhook duplicate-safe)
 */
class OrderFinalizationService
{
    /**
     * Finalize order after payment approval
     * 
     * Flujo order-first REAL:
     * 1. Si el order NO tiene tickets pre-existentes:
     *    - Crear Ticket por cada asiento reservado
     *    - Generar ticket_number y QR
     *    - Marcar como confirmed
     * 2. Si el order ya tiene tickets (legacy):
     *    - Solo actualizar ticket_number y QR
     *    - Marcar como confirmed
     * 3. Marcar asientos como sold en screening_seats
     * 4. Marcar order como paid/completed
     * 
     * Idempotent operation: puede ejecutarse múltiples veces seguramente
     * 
     * @param int $orderId
     * @param array $paymentData Información adicional del pago (transaction_id, webhook data, etc)
     * @return array Resultado con success and details
     */
    public function finalizeOrderAfterApproval(int $orderId, array $paymentData = []): array
    {
        try {
            Log::info("Order finalization started (order-first flow)", [
                'order_id' => $orderId,
                'has_payment_data' => !empty($paymentData),
            ]);

            DB::beginTransaction();

            try {
                // Step 1: Load order with lock
                $order = Order::lockForUpdate()->find($orderId);
                
                if (!$order) {
                    Log::error("Order not found for finalization", ['order_id' => $orderId]);
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Order not found',
                        'error_code' => 'ORDER_NOT_FOUND',
                    ];
                }

                Log::info("Order loaded for finalization", [
                    'order_id' => $orderId,
                    'order_status' => $order->status,
                    'total_amount' => $order->total_amount,
                ]);

                // Step 2: Check if order is already finalized (idempotency)
                if (in_array($order->status, ['completed', 'paid'])) {
                    Log::info("Order already finalized (idempotency check)", [
                        'order_id' => $orderId,
                        'status' => $order->status,
                    ]);
                    
                    DB::rollBack();
                    return [
                        'success' => true,
                        'message' => 'Order already finalized',
                        'idempotent' => true,
                        'order_id' => $orderId,
                    ];
                }

                // Step 3: Check if tickets exist for this order
                $existingTickets = Ticket::where('order_id', $orderId)
                    ->lockForUpdate()
                    ->get();

                $hasPreexistingTickets = $existingTickets->count() > 0;
                $finalizedTickets = [];

                if ($hasPreexistingTickets) {
                    // Legacy path: Tickets already created (pre-payment)
                    // Just update them with ticket_number and QR
                    Log::info("Finalizing pre-existing tickets", [
                        'order_id' => $orderId,
                        'ticket_count' => $existingTickets->count(),
                    ]);

                    foreach ($existingTickets as $ticket) {
                        if ($ticket->status === 'confirmed') {
                            // Already confirmed, skip
                            Log::debug("Ticket already confirmed, skipping", ['ticket_id' => $ticket->id]);
                            $finalizedTickets[] = [
                                'ticket_id' => $ticket->id,
                                'status' => 'already_confirmed',
                            ];
                            continue;
                        }

                        // Generate final ticket number and QR
                        $ticketNumber = $this->generateFinalTicketNumber($order, $ticket);
                        $qrCode = $this->generateQRCode($ticketNumber, $ticket);

                        // Update ticket
                        $ticket->update([
                            'ticket_number' => $ticketNumber,
                            'qr_code' => $qrCode,
                            'status' => 'confirmed',
                            'purchased_at' => now(),
                        ]);

                        Log::info("Ticket finalized", [
                            'ticket_id' => $ticket->id,
                            'ticket_number' => $ticketNumber,
                        ]);

                        $finalizedTickets[] = [
                            'ticket_id' => $ticket->id,
                            'ticket_number' => $ticketNumber,
                            'status' => 'finalized',
                        ];

                        // Mark seat as sold
                        if ($ticket->seat_id) {
                            $this->markSeatAsSold($order->screening_id, $ticket->seat_id, $orderId);
                        }
                    }

                } else {
                    // Order-first path: NO pre-existing tickets
                    // Create Ticket for each reserved seat in this order
                    Log::info("Creating tickets from reserved seats (order-first)", [
                        'order_id' => $orderId,
                    ]);

                    $screeningSeats = ScreeningSeat::where('order_id', $orderId)
                        ->where('status', ScreeningSeat::STATUS_RESERVED)
                        ->lockForUpdate()
                        ->get();

                    if ($screeningSeats->isEmpty()) {
                        Log::warning("Order has no reserved seats, cannot finalize", [
                            'order_id' => $orderId,
                        ]);
                        DB::rollBack();
                        return [
                            'success' => false,
                            'message' => 'Order has no reserved seats to finalize',
                            'error_code' => 'NO_SEATS',
                        ];
                    }

                    // Create Ticket for each reserved seat
                    foreach ($screeningSeats as $screeningSeat) {
                        $seat = $screeningSeat->seat;
                        
                        // Create ticket
                        $ticket = Ticket::create([
                            'screening_id' => $order->screening_id,
                            'seat_id' => $screeningSeat->seat_id,
                            'user_id' => $order->user_id,
                            'order_id' => $orderId,
                            'ticket_number' => null, // Will generate below
                            'price' => $this->getScreeningPrice($order->screening_id),
                            'customer_email' => $order->customer_email,
                            'customer_name' => $order->customer_name,
                            'customer_phone' => $order->customer_phone,
                            'status' => 'confirmed',
                            'ip_address' => $order->ip_address,
                            'purchased_at' => now(),
                            // Desnormalized fields
                            'seat_code' => $seat->seat_code,
                            'row_number' => $seat->row_number,
                            'seat_number' => $seat->seat_number,
                        ]);

                        // Generate ticket number and QR
                        $ticketNumber = $this->generateFinalTicketNumber($order, $ticket);
                        $qrCode = $this->generateQRCode($ticketNumber, $ticket);

                        // Update with generated fields
                        $ticket->update([
                            'ticket_number' => $ticketNumber,
                            'qr_code' => $qrCode,
                        ]);

                        Log::info("Ticket created from reserved seat", [
                            'ticket_id' => $ticket->id,
                            'seat_id' => $screeningSeat->seat_id,
                            'ticket_number' => $ticketNumber,
                        ]);

                        $finalizedTickets[] = [
                            'ticket_id' => $ticket->id,
                            'ticket_number' => $ticketNumber,
                            'seat_id' => $screeningSeat->seat_id,
                            'status' => 'created and confirmed',
                        ];

                        // Mark seat as sold
                        $this->markSeatAsSold($order->screening_id, $screeningSeat->seat_id, $orderId);
                    }
                }

                // Step 4: Update order status
                $order->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                ]);

                Log::info("Order finalization completed", [
                    'order_id' => $orderId,
                    'order_number' => $order->order_number,
                    'finalized_tickets' => count($finalizedTickets),
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Order finalized successfully',
                    'order_id' => $orderId,
                    'order_number' => $order->order_number,
                    'finalized_tickets' => count($finalizedTickets),
                    'tickets_created' => !$hasPreexistingTickets ? count($finalizedTickets) : 0,
                    'details' => $finalizedTickets,
                ];

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error during order finalization", [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Order finalization failed: ' . $e->getMessage(),
                'error_code' => 'FINALIZATION_ERROR',
                'exception' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get screening price (helper for order-first ticket creation)
     */
    private function getScreeningPrice(int $screeningId): float
    {
        $screening = Screening::find($screeningId);
        return $screening?->price ?? 0;
    }

    /**
     * Finalize order after payment approval
     * 
     * Idempotent operation: can be called multiple times safely
     * (e.g., if webhook arrives twice)
     * 
     * @param int $orderId
     * @param array $paymentData Additional context (e.g., transaction_id, payment_provider data)
     * @return array Result with status and details
     */
    public function finalizeOrderAfterApprovalOld(int $orderId, array $paymentData = []): array
    {
        try {
            Log::info("Order finalization started", [
                'order_id' => $orderId,
                'has_payment_data' => !empty($paymentData),
            ]);

            DB::beginTransaction();

            try {
                // Step 1: Load order with tickets
                $order = Order::lockForUpdate()->find($orderId);
                
                if (!$order) {
                    Log::error("Order not found for finalization", ['order_id' => $orderId]);
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Order not found',
                        'error_code' => 'ORDER_NOT_FOUND',
                    ];
                }

                // Step 2: Load all tickets for this order
                $tickets = Ticket::where('order_id', $orderId)
                    ->lockForUpdate()
                    ->get();

                Log::info("Order loaded for finalization", [
                    'order_id' => $orderId,
                    'ticket_count' => $tickets->count(),
                ]);

                // Step 3: Check idempotency - if all tickets are already confirmed, skip
                $confirmedCount = $tickets->where('status', 'confirmed')->count();
                
                if ($confirmedCount === $tickets->count() && $confirmedCount > 0) {
                    Log::info("Order already finalized (idempotency check)", [
                        'order_id' => $orderId,
                        'confirmed_tickets' => $confirmedCount,
                    ]);
                    
                    DB::rollBack();
                    return [
                        'success' => true,
                        'message' => 'Order already finalized',
                        'idempotent' => true,
                        'finalized_tickets' => $confirmedCount,
                    ];
                }

                // Warn if some tickets are already confirmed (partial state)
                if ($confirmedCount > 0 && $confirmedCount < $tickets->count()) {
                    Log::warning("Order in partial finalization state", [
                        'order_id' => $orderId,
                        'confirmed_tickets' => $confirmedCount,
                        'total_tickets' => $tickets->count(),
                    ]);
                }

                // Step 4: Finalize each ticket
                $finalizedTickets = [];

                foreach ($tickets as $ticket) {
                    // Skip if already confirmed (partial finalization recovery)
                    if ($ticket->status === 'confirmed') {
                        Log::debug("Ticket already confirmed, skipping", ['ticket_id' => $ticket->id]);
                        $finalizedTickets[] = [
                            'ticket_id' => $ticket->id,
                            'status' => 'already_confirmed',
                        ];
                        continue;
                    }

                    // Generate final ticket_number if not already set (or placeholder)
                    $ticketNumber = $this->generateFinalTicketNumber($order, $ticket);
                    
                    // Generate QR code
                    $qrCode = $this->generateQRCode($ticketNumber, $ticket);

                    // Update ticket
                    $ticket->update([
                        'ticket_number' => $ticketNumber,
                        'qr_code' => $qrCode,
                        'status' => 'confirmed',
                        'purchased_at' => now(),
                    ]);

                    Log::info("Ticket finalized", [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticketNumber,
                        'status' => 'confirmed',
                    ]);

                    $finalizedTickets[] = [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticketNumber,
                        'status' => 'finalized',
                    ];

                    // Step 5: Mark corresponding screening seat as sold
                    if ($ticket->seat_id) {
                        $this->markSeatAsSold($order->screening_id, $ticket->seat_id, $orderId);
                    }
                }

                // Step 6: Update order status to completed
                $order->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                Log::info("Order finalization completed", [
                    'order_id' => $orderId,
                    'order_number' => $order->order_number,
                    'finalized_count' => count($finalizedTickets),
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Order (tickets and seats) finalized successfully',
                    'order_id' => $orderId,
                    'order_number' => $order->order_number,
                    'finalized_tickets' => count($finalizedTickets),
                    'details' => $finalizedTickets,
                ];

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error during order finalization", [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Order finalization failed: ' . $e->getMessage(),
                'error_code' => 'FINALIZATION_ERROR',
                'exception' => $e->getMessage(),
            ];
        }
    }

    /**
     * Finalize order after payment approval
     * 
     * Idempotent operation: can be called multiple times safely
     * (e.g., if webhook arrives twice)
     * 
     * @param int $orderId
     * @param array $paymentData Additional context (e.g., transaction_id, payment_provider data)
     * @return array Result with status and details
     */
    public function finalizeOrderAfterApprovalOldOriginal(int $orderId, array $paymentData = []): array
    {
        try {
            Log::info("Order finalization started", [
                'order_id' => $orderId,
                'has_payment_data' => !empty($paymentData),
            ]);

            DB::beginTransaction();

            try {
                // Step 1: Load order with tickets
                $order = Order::lockForUpdate()->find($orderId);
                
                if (!$order) {
                    Log::error("Order not found for finalization", ['order_id' => $orderId]);
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Order not found',
                        'error_code' => 'ORDER_NOT_FOUND',
                    ];
                }

                // Step 2: Load all tickets for this order
                $tickets = Ticket::where('order_id', $orderId)
                    ->lockForUpdate()
                    ->get();

                Log::info("Order loaded for finalization", [
                    'order_id' => $orderId,
                    'ticket_count' => $tickets->count(),
                ]);

                // Step 3: Check idempotency - if all tickets are already confirmed, skip
                $confirmedCount = $tickets->where('status', 'confirmed')->count();
                
                if ($confirmedCount === $tickets->count() && $confirmedCount > 0) {
                    Log::info("Order already finalized (idempotency check)", [
                        'order_id' => $orderId,
                        'confirmed_tickets' => $confirmedCount,
                    ]);
                    
                    DB::rollBack();
                    return [
                        'success' => true,
                        'message' => 'Order already finalized',
                        'idempotent' => true,
                        'finalized_tickets' => $confirmedCount,
                    ];
                }

                // Warn if some tickets are already confirmed (partial state)
                if ($confirmedCount > 0 && $confirmedCount < $tickets->count()) {
                    Log::warning("Order in partial finalization state", [
                        'order_id' => $orderId,
                        'confirmed_tickets' => $confirmedCount,
                        'total_tickets' => $tickets->count(),
                    ]);
                }

                // Step 4: Finalize each ticket
                $finalizedTickets = [];

                foreach ($tickets as $ticket) {
                    // Skip if already confirmed (partial finalization recovery)
                    if ($ticket->status === 'confirmed') {
                        Log::debug("Ticket already confirmed, skipping", ['ticket_id' => $ticket->id]);
                        $finalizedTickets[] = [
                            'ticket_id' => $ticket->id,
                            'status' => 'already_confirmed',
                        ];
                        continue;
                    }

                    // Generate final ticket_number if not already set (or placeholder)
                    $ticketNumber = $this->generateFinalTicketNumber($order, $ticket);
                    
                    // Generate QR code
                    $qrCode = $this->generateQRCode($ticketNumber, $ticket);

                    // Update ticket
                    $ticket->update([
                        'ticket_number' => $ticketNumber,
                        'qr_code' => $qrCode,
                        'status' => 'confirmed',
                        'purchased_at' => now(),
                    ]);

                    Log::info("Ticket finalized", [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticketNumber,
                        'status' => 'confirmed',
                    ]);

                    $finalizedTickets[] = [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticketNumber,
                        'status' => 'finalized',
                    ];

                    // Step 5: Mark corresponding screening seat as sold
                    if ($ticket->seat_id) {
                        $this->markSeatAsSold($order->screening_id, $ticket->seat_id, $orderId);
                    }
                }

                // Step 6: Update order status to completed
                $order->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                Log::info("Order finalization completed", [
                    'order_id' => $orderId,
                    'order_number' => $order->order_number,
                    'finalized_count' => count($finalizedTickets),
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Order (tickets and seats) finalized successfully',
                    'order_id' => $orderId,
                    'order_number' => $order->order_number,
                    'finalized_tickets' => count($finalizedTickets),
                    'details' => $finalizedTickets,
                ];

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error during order finalization", [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Order finalization failed: ' . $e->getMessage(),
                'error_code' => 'FINALIZATION_ERROR',
                'exception' => $e->getMessage(),
            ];
        }
    }


    /**
     * Generate final ticket number
     * Format: ORD-YYYYMMDD-{ORDER_SEQ}{CHECK_DIGIT}-TKT-{SEAT_SEQ}
     * 
     * Example: ORD-20250214-0001A-TKT-001
     */
    private function generateFinalTicketNumber(Order $order, Ticket $ticket): string
    {
        // Use order's order_number as base
        $orderNumber = $order->order_number ?? 'UNKNOWN';
        
        // Add ticket sequence within order
        $ticketSeqInOrder = $order->tickets()
            ->where('id', '<=', $ticket->id)
            ->count();

        return "TKT-{$orderNumber}-{$ticketSeqInOrder}";
    }

    /**
     * Generate QR code for ticket
     * 
     * Uses phpqrcode library or similar to generate QR
     * Returns encoded string (usually base64 data URI or SVG)
     */
    private function generateQRCode(string $ticketNumber, Ticket $ticket): string
    {
        try {
            // Data to encode in QR
            $qrData = $this->buildQRData($ticketNumber, $ticket);
            
            // Generate QR code
            $qrCode = \QRcode::png($qrData, false, QR_ECLEVEL_H, 4, 2);
            
            return base64_encode($qrCode);

        } catch (\Exception $e) {
            Log::warning("Failed to generate QR code, using fallback", [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
            
            // Fallback: encode ticket number
            return hash('sha256', $ticketNumber . $ticket->id);
        }
    }

    /**
     * Build data to encode in QR code
     */
    private function buildQRData(string $ticketNumber, Ticket $ticket): string
    {
        return json_encode([
            'ticket_number' => $ticketNumber,
            'ticket_id' => $ticket->id,
            'screening_id' => $ticket->screening_id,
            'seat_id' => $ticket->seat_id,
            'customer_email' => $ticket->customer_email,
            'version' => '1.0',
        ]);
    }

    /**
     * Mark screening seat as sold
     */
    private function markSeatAsSold(int $screeningId, int $seatId, int $orderId): void
    {
        try {
            $screeningSeat = ScreeningSeat::where('screening_id', $screeningId)
                ->where('seat_id', $seatId)
                ->lockForUpdate()
                ->first();

            if (!$screeningSeat) {
                Log::warning("Screening seat not found for marking as sold", [
                    'screening_id' => $screeningId,
                    'seat_id' => $seatId,
                ]);
                return;
            }

            // Only update if not already sold
            if ($screeningSeat->status === 'sold') {
                Log::debug("Seat already marked as sold", [
                    'screening_id' => $screeningId,
                    'seat_id' => $seatId,
                ]);
                return;
            }

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

        } catch (\Exception $e) {
            Log::error("Error marking seat as sold", [
                'screening_id' => $screeningId,
                'seat_id' => $seatId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
