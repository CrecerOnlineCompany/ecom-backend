<?php

namespace App\Actions\Payments;

use App\Models\Order;
use App\Models\PaymentProviderTicket;
use App\Models\Ticket;
use App\Services\SeatInventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cancela un pago order-first pendiente
 * 
 * When user cancels payment attempt:
 * 1. Release asientos de inventory
 * 2. Marcar Order como CANCELLED
 * 3. Marcar PaymentProviderTicket como cancelled
 * 4. Marcar cualquier Ticket pre-existente como cancelled (no se borran para auditar)
 */
class CancelOrderPaymentAction
{
    protected SeatInventoryService $inventoryService;

    public function __construct(SeatInventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Cancelar pago pendiente
     */
    public function cancel(PaymentProviderTicket $ppt, string $reason = 'User cancelled'): void
    {
        DB::transaction(function () use ($ppt, $reason) {
            // Validar que el pago está en estado cancelable
            if (!in_array($ppt->status, ['processing', 'pending'])) {
                Log::warning("CancelOrderPaymentAction: Cannot cancel payment in status", [
                    'payment_ticket_id' => $ppt->id,
                    'status' => $ppt->status,
                ]);
                throw new \Exception(
                    "Cannot cancel payment in status: {$ppt->status}"
                );
            }

            $order = $ppt->order;

            if (!$order) {
                Log::warning("CancelOrderPaymentAction: No order linked to payment", [
                    'payment_ticket_id' => $ppt->id,
                ]);
                // Legacy behavior - just mark payment as cancelled
                $ppt->update(['status' => 'cancelled']);
                return;
            }

            Log::info("CancelOrderPaymentAction: Cancelling order payment", [
                'order_id' => $order->id,
                'payment_ticket_id' => $ppt->id,
                'reason' => $reason,
            ]);

            try {
                // Step 1: Release asientos de inventory
                $releasedCount = $this->inventoryService->releaseSeatsByOrder(
                    $order->id,
                    'payment_cancelled'
                );

                Log::info("Seats released in CancelOrderPaymentAction", [
                    'order_id' => $order->id,
                    'released_count' => $releasedCount,
                ]);

                // Step 2: Marcar Order como CANCELLED
                $order->update([
                    'status' => Order::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);

                // Step 3: Marcar PaymentProviderTicket como cancelled
                $ppt->update([
                    'status' => 'cancelled',
                ]);

                // Step 4: Si existen tickets (edge case), marcarlos como cancelled
                $tickets = Ticket::where('order_id', $order->id)
                    ->whereIn('status', ['pending_payment', 'processing', 'payment_failed'])
                    ->get();

                if ($tickets->isNotEmpty()) {
                    Ticket::where('order_id', $order->id)
                        ->whereIn('status', ['pending_payment', 'processing', 'payment_failed'])
                        ->update(['status' => 'cancelled']);

                    Log::info("Tickets marked as cancelled", [
                        'order_id' => $order->id,
                        'ticket_count' => $tickets->count(),
                    ]);
                }

                Log::info("CancelOrderPaymentAction: Completed", [
                    'order_id' => $order->id,
                    'payment_ticket_id' => $ppt->id,
                ]);

            } catch (\Exception $e) {
                Log::error("CancelOrderPaymentAction: Error", [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }, attempts: 3);
    }
}
