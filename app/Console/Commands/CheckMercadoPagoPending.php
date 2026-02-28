<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentProviderTicket;
use App\Models\PaymentProvider;
use App\Services\OrderFinalizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MercadoPago\MercadoPagoConfig;

class CheckMercadoPagoPending extends Command
{
    protected $signature = 'order:check-mercadopago-pending {--limit=50 : Cantidad máxima de órdenes a revisar} {--hours=48 : Revisar órdenes de las últimas N horas} {--update : Actualizar estados encontrados}';
    protected $description = 'Verificar órdenes pendientes en Mercado Pago y actualizar sus estados';

    public function handle(): int
    {
        $this->info('=== Verificando Órdenes Pendientes en Mercado Pago ===');
        $this->newLine();

        // Obtener proveedor de Mercado Pago
        $mercadoPagoProvider = PaymentProvider::where('name', 'mercado_pago')
            ->where('is_active', true)
            ->first();

        if (!$mercadoPagoProvider) {
            $this->error('❌ Proveedor de pago Mercado Pago no encontrado o no está activo');
            return 1;
        }

        // Verificar token
        $accessToken = $mercadoPagoProvider->getConfig('access_token');
        if (empty($accessToken)) {
            $this->error('❌ Access token de Mercado Pago no configurado');
            return 1;
        }

        // Buscar pagos pendientes o en procesamiento
        $hoursBack = (int)$this->option('hours');
        $limit = (int)$this->option('limit');
        $shouldUpdate = $this->option('update');

        $query = PaymentProviderTicket::where('payment_provider_id', $mercadoPagoProvider->id)
            ->whereIn('status', PaymentStatus::transient())
            ->with([
                'order.screening.movie',
                'order.user',
                'paymentProvider'
            ])
            ->latest('created_at');

        if ($hoursBack > 0) {
            $query->where('created_at', '>=', now()->subHours($hoursBack));
        }

        $pendingPayments = $query->limit($limit)->get();

        if ($pendingPayments->isEmpty()) {
            $this->info('✓ No hay órdenes pendientes en Mercado Pago');
            return 0;
        }

        $this->info("Encontradas <fg=yellow>{$pendingPayments->count()}</> órdenes pendientes");
        $this->newLine();

        $headers = ['ID', 'Orden', 'Cine', 'Película', 'Monto', 'Estado Local', 'Estado MP', 'Acción'];
        $rows = [];
        $updated = 0;
        $errors = 0;
        $changesDetected = 0;

        foreach ($pendingPayments as $payment) {
            try {
                $transactionId = $payment->transaction_id;
                
                if (empty($transactionId)) {
                    $this->warn("⚠ Payment #{$payment->id} sin transaction_id");
                    continue;
                }

                // Consultar estado en Mercado Pago
                $this->info("📍 Consultando estado de: {$transactionId}");
                $mpStatus = $this->getOrderStatusFromMercadoPago($transactionId);

                if ($mpStatus === null) {
                    $this->warn("⚠ No se pudo obtener estado de MP para Order ID: {$transactionId}");
                    Log::warning('CheckMercadoPagoPending: No se obtuvo estado de MP', [
                        'transaction_id' => $transactionId,
                        'payment_id' => $payment->id,
                    ]);
                    $rows[] = [
                        $payment->id,
                        $payment->order->order_number ?? '?',
                        $payment->order->screening->movie->title ?? 'N/A',
                        '$' . number_format($payment->order->total_amount, 2),
                        $payment->status,
                        '❌ Error',
                        'Error',
                    ];
                    $errors++;
                    continue;
                }

                $newStatus = $this->mapMercadoPagoStatus($mpStatus);
                $statusChanged = $newStatus !== $payment->status;

                if ($statusChanged) {
                    $changesDetected++;
                    $actionStr = "Cambiar a: <fg=cyan>{$newStatus}</>";
                    
                    if ($shouldUpdate) {
                        try {
                            // Obtener datos completos del pago de Mercado Pago
                            $paymentData = $this->getPaymentDataFromMercadoPago($transactionId);

                            // Actualizar estado en PaymentProviderTicket
                            $payment->update([
                                'status' => $newStatus,
                            ]);

                            // Guardar info completa del pago en la Order
                            if ($payment->order_id) {
                                $order = $payment->order;
                                $paymentDataInOrder = $order->payment_data ?? [];
                                
                                // Guardar datos de Mercado Pago en order->payment_data
                                $paymentDataInOrder['mercadopago'] = [
                                    'transaction_id' => $transactionId,
                                    'status' => $newStatus,
                                    'mercadopago_status' => $mpStatus,
                                    'payment_data' => $paymentData,
                                    'last_sync_at' => now()->toIso8601String(),
                                ];
                                
                                $order->update([
                                    'payment_data' => $paymentDataInOrder,
                                ]);
                                
                                Log::debug('CheckMercadoPagoPending: Payment data saved to Order', [
                                    'order_id' => $order->id,
                                    'payment_id' => $payment->id,
                                    'transaction_id' => $transactionId,
                                    'payment_data' => json_encode($paymentDataInOrder),
                                ]);
                            }

                            $actionStr = "✓ <fg=green>Actualizado a: {$newStatus}</>";
                            $updated++;

                            Log::info('CheckMercadoPagoPending: Status actualizado', [
                                'payment_id' => $payment->id,
                                'order_id' => $payment->order_id,
                                'old_status' => $payment->status,
                                'new_status' => $newStatus,
                                'mercadopago_status' => $mpStatus,
                            ]);

                            // CRUCIAL: Finalizar orden y crear tickets si pago está completado
                            if ($newStatus === PaymentStatus::STATUS_COMPLETED && $payment->order_id) {
                                try {
                                    $finalizationService = app(OrderFinalizationService::class);
                                    $result = $finalizationService->finalizeOrderAfterApproval(
                                        $payment->order_id,
                                        [
                                            'transaction_id' => $transactionId,
                                            'status' => $newStatus,
                                            'mercadopago_status' => $mpStatus,
                                        ]
                                    );

                                    if ($result['success']) {
                                        $this->info("✓ Orden #{$payment->order_id} finalizada - Tickets creados: {$result['finalized_tickets']}");
                                        Log::info('CheckMercadoPagoPending: Order finalized successfully', [
                                            'order_id' => $payment->order_id,
                                            'payment_id' => $payment->id,
                                            'tickets_created' => $result['finalized_tickets'],
                                        ]);
                                    } else {
                                        $this->warn("⚠ Error finalizando orden #{$payment->order_id}: {$result['message']}");
                                        Log::warning('CheckMercadoPagoPending: Order finalization failed', [
                                            'order_id' => $payment->order_id,
                                            'payment_id' => $payment->id,
                                            'message' => $result['message'],
                                        ]);
                                    }
                                } catch (\Exception $e) {
                                    $this->error("❌ Error al finalizar orden: {$e->getMessage()}");
                                    Log::error('CheckMercadoPagoPending: Exception finalizing order', [
                                        'order_id' => $payment->order_id,
                                        'payment_id' => $payment->id,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }

                        } catch (\Exception $e) {
                            $actionStr = "❌ <fg=red>Error: {$e->getMessage()}</>";
                            $errors++;
                            Log::error('CheckMercadoPagoPending: Error actualizando', [
                                'payment_id' => $payment->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                } else {
                    $actionStr = "<fg=green>Sin cambios</>";
                }

                $rows[] = [
                    $payment->id,
                    $payment->order->order_number ?? '?',
                    $payment->order->screening->movie->title ?? 'N/A',
                    '$' . number_format($payment->order->total_amount, 2),
                    $payment->status,
                    $this->getStatusIcon($mpStatus) . ' ' . $mpStatus,
                    $actionStr,
                ];

            } catch (\Exception $e) {
                $this->error("Error procesando payment #{$payment->id}: {$e->getMessage()}");
                $errors++;
                $rows[] = [
                    $payment->id,
                    $payment->order->order_number ?? '?',
                    'N/A',
                    'N/A',
                    'N/A',
                    $payment->status,
                    '❌ Error',
                    '❌ Exception',
                ];
            }
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->info('=== Resumen ===');
        $this->line("Total revisadas: <fg=cyan>{$pendingPayments->count()}</>");
        $this->line("Cambios detectados: <fg=yellow>{$changesDetected}</>");
        
        if ($shouldUpdate) {
            $this->line("Actualizadas: <fg=green>{$updated}</>");
            $this->line("Errores: <fg=red>{$errors}</>");
        } else {
            if ($changesDetected > 0) {
                $this->warn('💡 Ejecuta con --update para actualizar los ' . $changesDetected . ' estado(s) encontrado(s)');
            }
        }

        return 0;
    }

    /**
     * Obtener estado de una orden en Mercado Pago
     */
    private function getOrderStatusFromMercadoPago(string $orderId): ?string
    {
        try {
            $accessToken = $this->getMercadoPagoToken();
            
            $response = Http::withToken($accessToken)
                ->get("https://api.mercadopago.com/v1/orders/{$orderId}");

            if (!$response->successful()) {
                Log::warning('CheckMercadoPagoPending: Error en respuesta de API MP', [
                    'order_id' => $orderId,
                    'status_code' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $order = $response->json();
            Log::debug('CheckMercadoPagoPending: Respuesta completa de MP', [
                'order_id' => $orderId,
                'response_keys' => array_keys($order),
                'response' => json_encode($order),
            ]);

            // Buscar en transactions.payments (estructura de API MP)
            if (isset($order['transactions']['payments']) && !empty($order['transactions']['payments'])) {
                $lastPayment = collect($order['transactions']['payments'])->last();
                
                if ($lastPayment && isset($lastPayment['status'])) {
                    Log::info('CheckMercadoPagoPending: Estado obtenido de transactions.payments', [
                        'order_id' => $orderId,
                        'payment_status' => $lastPayment['status'],
                        'status_detail' => $lastPayment['status_detail'] ?? null,
                    ]);
                    return $lastPayment['status'];
                }
            }

            // Fallback: Si no hay pagos, usar estado de la orden
            if (isset($order['status'])) {
                Log::info('CheckMercadoPagoPending: Usando estado de la orden', [
                    'order_id' => $orderId,
                    'order_status' => $order['status'],
                ]);
                return $order['status'];
            }

            Log::warning('CheckMercadoPagoPending: No se encontró status en respuesta de MP', [
                'order_id' => $orderId,
                'response_keys' => array_keys($order),
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('CheckMercadoPagoPending: Error inesperado al consultar MP', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Obtener token de acceso de Mercado Pago
     */
    private function getMercadoPagoToken(): string
    {
        return PaymentProvider::where('name', 'mercado_pago')
            ->where('is_active', true)
            ->first()
            ->getConfig('access_token');
    }

    /**
     * Mapear estado de Mercado Pago a estado local
     */
    private function mapMercadoPagoStatus(string $mpStatus): string
    {
        return match($mpStatus) {
            'approved' => PaymentStatus::STATUS_COMPLETED,
            'processed' => PaymentStatus::STATUS_COMPLETED,
            'authorized' => PaymentStatus::STATUS_PROCESSING,
            'pending' => PaymentStatus::STATUS_PENDING,
            'pending_review' => PaymentStatus::STATUS_PENDING,
            'pending_cardholder_action' => PaymentStatus::STATUS_PENDING,
            'pending_payment_in_wallet' => PaymentStatus::STATUS_PENDING,
            'processing' => PaymentStatus::STATUS_PROCESSING,
            'in_mediation' => PaymentStatus::STATUS_PENDING,
            'rejected' => PaymentStatus::STATUS_FAILED,
            'cancelled' => PaymentStatus::STATUS_FAILED,
            'refunded' => PaymentStatus::STATUS_REFUNDED,
            'partially_refunded' => PaymentStatus::STATUS_PENDING,
            'disputed' => PaymentStatus::STATUS_PENDING,
            default => PaymentStatus::STATUS_PENDING,
        };
    }

    /**
     * Obtener icono para mostrar el estado
     */
    private function getStatusIcon(string $status): string
    {
        return match($status) {
            'approved' => '✓',
            'pending' => '⏳',
            'processing' => '⟳',
            'rejected' => '✗',
            'cancelled' => '✗',
            'refunded' => '↩',
            default => '?',
        };
    }

    /**
     * Obtener datos completos del pago de Mercado Pago
     */
    private function getPaymentDataFromMercadoPago(string $orderId): ?array
    {
        try {
            $accessToken = $this->getMercadoPagoToken();
            
            $response = Http::withToken($accessToken)
                ->get("https://api.mercadopago.com/v1/orders/{$orderId}");

            if (!$response->successful()) {
                return null;
            }

            $order = $response->json();

            // Obtener el último pago de transactions.payments
            if (isset($order['transactions']['payments']) && !empty($order['transactions']['payments'])) {
                return collect($order['transactions']['payments'])->last();
            }

            return null;

        } catch (\Exception $e) {
            Log::error('CheckMercadoPagoPending: Error obteniendo datos del pago', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
