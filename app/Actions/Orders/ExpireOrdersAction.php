<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\Ticket;
use App\Services\SeatInventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Expira órdenes order-first que se quedaron en estado RESERVED
 * 
 * Se ejecuta desde cleanup() del controller.
 * Limpia órdenes cuyo reserved_until < now()
 * 
 * Para cada orden:
 * 1. Release asientos de inventory
 * 2. Marcar orden como EXPIRED
 * 3. Marcar tickets asociados como expired (si existen)
 */
class ExpireOrdersAction
{
    protected SeatInventoryService $inventoryService;

    public function __construct(SeatInventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Limpiar órdenes expiradas order-first
     * 
     * @param int|null $screeningId Filtrar por screening (opcional)
     * @param int|null $hours Filtrar por antigüedad (opcional)
     * @return array [
     *     'expired_orders' => [...],
     *     'expired_tickets' => [...],
     *     'total_orders' => int,
     *     'total_tickets' => int
     * ]
     */
    public function cleanup(?int $screeningId = null, ?int $hours = null): array
    {
        return DB::transaction(function () use ($screeningId, $hours) {
            // Encontrar órdenes expiradas with RESERVED status
            $orderQuery = Order::query()
                ->where('status', Order::STATUS_RESERVED)
                ->whereNotNull('reserved_until')
                ->where('reserved_until', '<', now());

            // Filtros opcionales
            if ($screeningId) {
                $orderQuery->where('screening_id', $screeningId);
            }

            if ($hours) {
                $threshold = now()->subHours($hours);
                $orderQuery->where('created_at', '<', $threshold);
            }

            $expiredOrders = $orderQuery->get();

            if ($expiredOrders->isEmpty()) {
                Log::info("ExpireOrdersAction: No expired orders found");
                return [
                    'expired_orders' => [],
                    'expired_tickets' => [],
                    'total_orders' => 0,
                    'total_tickets' => 0,
                ];
            }

            Log::info("ExpireOrdersAction: Found expired orders", [
                'count' => $expiredOrders->count(),
            ]);

            $expiredOrdersList = [];
            $expiredTicketsList = [];

            foreach ($expiredOrders as $order) {
                try {
                    Log::info("ExpireOrdersAction: Processing expired order", [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'reserved_until' => $order->reserved_until,
                    ]);

                    // Step 1: Release asientos
                    $releasedCount = $this->inventoryService->releaseSeatsByOrder(
                        $order->id,
                        'reservation_expired'
                    );

                    // Step 2: Marcar orden como EXPIRED
                    $order->update([
                        'status' => Order::STATUS_EXPIRED,
                        'cancelled_at' => now(),
                    ]);

                    // Step 3: Marcar tickets como expired (si existen)
                    $tickets = Ticket::where('order_id', $order->id)
                        ->whereIn('status', ['pending_payment', 'processing', 'payment_failed'])
                        ->get();

                    if ($tickets->isNotEmpty()) {
                        Ticket::where('order_id', $order->id)
                            ->whereIn('status', ['pending_payment', 'processing', 'payment_failed'])
                            ->update(['status' => 'expired']);

                        foreach ($tickets as $ticket) {
                            $expiredTicketsList[] = [
                                'id' => $ticket->id,
                                'screening_id' => $ticket->screening_id,
                                'seat_id' => $ticket->seat_id,
                                'order_id' => $order->id,
                                'status' => 'expired',
                            ];
                        }
                    }

                    $expiredOrdersList[] = [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'seats_released' => $releasedCount,
                        'status' => Order::STATUS_EXPIRED,
                    ];

                    Log::info("Order expired and cleaned", [
                        'order_id' => $order->id,
                        'seats_released' => $releasedCount,
                        'tickets_expired' => $tickets->count(),
                    ]);

                } catch (\Exception $e) {
                    Log::error("ExpireOrdersAction: Error processing order", [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continuar con próximas órdenes
                    continue;
                }
            }

            Log::info("ExpireOrdersAction: Cleanup completed", [
                'expired_orders' => count($expiredOrdersList),
                'expired_tickets' => count($expiredTicketsList),
            ]);

            return [
                'expired_orders' => $expiredOrdersList,
                'expired_tickets' => $expiredTicketsList,
                'total_orders' => count($expiredOrdersList),
                'total_tickets' => count($expiredTicketsList),
            ];

        }, attempts: 3);
    }
}
