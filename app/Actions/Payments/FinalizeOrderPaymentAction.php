<?php

namespace App\Actions\Payments;

use App\Models\Order;
use App\Models\PaymentProviderTicket;
use App\Models\Ticket;
use App\Models\TicketDetail;
use App\Services\SeatInventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finaliza un pago order-first DESPUÉS que webhook confirma payment aprobado
 * 
 * IDEMPOTENTE: Si se llama 2 veces con mismo payment, no debe duplicar tickets
 * 
 * Flow:
 * 1. Lock Order para evitar race conditions
 * 2. Si Order ya está PAID -> return success (ya fue procesada)
 * 3. Si Order aún RESERVED -> continuar
 * 4. Obtener seat_ids reservados de esta Order (desde inventory)
 * 5. Marcar asientos como SOLD en inventory (atómicamente)
 * 6. Crear 1 Ticket confirmado por cada asiento
 * 7. Generar ticket_number para cada ticket
 * 8. Marcar Order como PAID
 * 9. Actualizar PaymentProviderTicket con completed_at
 */
class FinalizeOrderPaymentAction
{
    protected SeatInventoryService $inventoryService;

    public function __construct(SeatInventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Procesar webhook de pago aprobado
     * 
     * @param PaymentProviderTicket $ppt El pago que fue aprobado
     * @param array $providerPayload Datos normalizados del provider
     * @throws \Exception
     */
    public function handleWebhook(PaymentProviderTicket $ppt, array $providerPayload): void
    {
        DB::transaction(function () use ($ppt, $providerPayload) {
            $this->finalize($ppt, $providerPayload);
        }, attempts: 3);
    }

    /**
     * Lógica principal de finalización (dentro de transaction)
     */
    private function finalize(PaymentProviderTicket $ppt, array $providerPayload): void
    {
        $order = $ppt->order;
        
        if (!$order) {
            Log::error("FinalizeOrderPaymentAction: No order linked to payment", [
                'payment_ticket_id' => $ppt->id,
            ]);
            throw new \Exception('No order found for payment ticket');
        }

        // Lock Order para evitar race conditions
        $order = Order::lockForUpdate()->find($order->id);

        // IDEMPOTENCIA CHECK: Si Order ya está PAID, significa que ya fue procesada
        if ($order->status === Order::STATUS_PAID) {
            Log::warning("FinalizeOrderPaymentAction: Order already paid (idempotent call)", [
                'order_id' => $order->id,
                'payment_ticket_id' => $ppt->id,
            ]);
            // No hacer nada - ya fue procesada
            return;
        }

        // Verificar que Order está en estado RESERVED
        if ($order->status !== Order::STATUS_RESERVED) {
            Log::error("FinalizeOrderPaymentAction: Order in unexpected status", [
                'order_id' => $order->id,
                'status' => $order->status,
                'expected' => Order::STATUS_RESERVED,
            ]);
            throw new \Exception("Order cannot be finalized from status: {$order->status}");
        }

        Log::info("FinalizeOrderPaymentAction: Starting finalization", [
            'order_id' => $order->id,
            'payment_ticket_id' => $ppt->id,
        ]);

        try {
            // Step 1: Obtener asientos reservados para esta Order
            $seatIds = $this->inventoryService->getReservedSeatIdsByOrder($order->id);
            
            if (empty($seatIds)) {
                Log::error("FinalizeOrderPaymentAction: No reserved seats found", [
                    'order_id' => $order->id,
                ]);
                throw new \Exception('No reserved seats found for order');
            }

            Log::info("FinalizeOrderPaymentAction: Found reserved seats", [
                'order_id' => $order->id,
                'seat_count' => count($seatIds),
                'seat_ids' => $seatIds,
            ]);

            // Step 2: Marcar asientos como SOLD en inventory (atómicamente)
            $confirmedCount = $this->inventoryService->finalizeOrderSeatsToSold($order->id);
            
            if ($confirmedCount !== count($seatIds)) {
                Log::warning("FinalizeOrderPaymentAction: Seat count mismatch", [
                    'order_id' => $order->id,
                    'requested' => count($seatIds),
                    'confirmed' => $confirmedCount,
                ]);
            }

            Log::info("Seats marked as SOLD", [
                'order_id' => $order->id,
                'confirmed_count' => $confirmedCount,
            ]);

            // Step 3: Crear 1 Ticket confirmado por cada asiento
            $tickets = [];
            foreach ($seatIds as $seatId) {
                $ticket = Ticket::create([
                    'screening_id' => $order->screening_id,
                    'seat_id' => $seatId,
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'ticket_number' => null, // Genera en siguiente paso
                    'price' => $order->total_amount / count($seatIds), // Distribuir precio
                    'customer_email' => $order->customer_email,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'status' => 'confirmed', // YA CONFIRMADO!
                    'payment_method' => $ppt->paymentProvider->name,
                    'purchased_at' => now(),
                    'ip_address' => $order->ip_address,
                ]);

                // Populate denormalized columns used by admin/reporting.
                $ticket->populateDenormalizedFields();
                $ticket->save();

                // Generar ticket_number (formato: ORD-{order_number}-{index})
                $ticketNumber = $this->generateTicketNumber($order->order_number, count($tickets));
                $ticket->update(['ticket_number' => $ticketNumber]);

                // Ensure per-seat detail record exists/updated for this ticket.
                $this->upsertTicketDetail($ticket);

                $tickets[] = $ticket->id;
                
                Log::info("Ticket created for order", [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticketNumber,
                    'order_id' => $order->id,
                ]);
            }

            // Step 4: Marcar Order como PAID
            $order->update([
                'status' => Order::STATUS_PAID,
                'paid_at' => now(),
            ]);

            Log::info("Order marked as PAID", [
                'order_id' => $order->id,
                'ticket_count' => count($tickets),
            ]);

            // Step 5: Actualizar PaymentProviderTicket
            $ppt->update([
                'status' => 'approved',
                'completed_at' => now(),
                'response_data' => array_merge(
                    $ppt->response_data ?? [],
                    ['ticket_ids' => $tickets, 'finalized_at' => now()->toIso8601String()]
                ),
            ]);

            Log::info("FinalizeOrderPaymentAction: Completed successfully", [
                'order_id' => $order->id,
                'payment_ticket_id' => $ppt->id,
                'tickets_created' => count($tickets),
            ]);

        } catch (\Exception $e) {
            Log::error("FinalizeOrderPaymentAction: Error during finalization", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Generar ticket_number único para cada ticket en la orden
     * Formato: ORD-{order_number}-001, ORD-{order_number}-002, etc.
     */
    private function generateTicketNumber(string $orderNumber, int $index): string
    {
        return sprintf('ORD-%s-%03d', $orderNumber, $index + 1);
    }

    private function upsertTicketDetail(Ticket $ticket): void
    {
        $ticket->loadMissing(['seat', 'screening.room']);

        $seatCode = $ticket->seat_code ?: $ticket->seat?->seat_code;
        $rowNumber = $ticket->row_number ?: $ticket->seat?->row_number;
        $seatNumber = $ticket->seat_number ?: $ticket->seat?->seat_number;

        if (!$seatCode || $rowNumber === null || $seatNumber === null) {
            Log::warning("FinalizeOrderPaymentAction: missing seat data for ticket detail", [
                'ticket_id' => $ticket->id,
                'seat_id' => $ticket->seat_id,
                'screening_id' => $ticket->screening_id,
            ]);
            return;
        }

        TicketDetail::updateOrCreate(
            ['ticket_id' => $ticket->id],
            [
                'screening_id' => $ticket->screening_id,
                'seat_id' => $ticket->seat_id,
                'seat_code' => $seatCode,
                'row_number' => (int) $rowNumber,
                'seat_number' => (int) $seatNumber,
                'room_non_number' => (bool) ($ticket->screening?->room?->non_number ?? false),
                'price' => $ticket->price,
                'status' => $ticket->status === 'cancelled' ? 'cancelled' : 'confirmed',
                'qr_code' => $ticket->qr_code,
                'used_at' => $ticket->used_at,
            ]
        );
    }
}
