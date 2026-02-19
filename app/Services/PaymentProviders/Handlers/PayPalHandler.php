<?php

namespace App\Services\PaymentProviders\Handlers;

use App\Services\PaymentProviders\PaymentProviderHandler;
use App\Models\PaymentProviderTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalHandler extends PaymentProviderHandler
{
    /**
     * Procesar pago en PayPal
     */
    public function processPayment(PaymentProviderTicket $paymentTicket, array $additionalData = []): array
    {
        try {
            $ticket = $paymentTicket->ticket;
            
            // Usar total_price si está disponible (batch payment de múltiples asientos)
            // Si no, usar el precio del ticket individual
            if (!empty($additionalData['total_price'])) {
                $price = floatval($additionalData['total_price']);
                $seatCount = intval($additionalData['seat_count'] ?? 1);
                $description = $seatCount > 1 
                    ? "{$seatCount} Entradas - {$ticket->screening->movie->title}"
                    : "Entrada - {$ticket->screening->movie->title}";
            } else {
                $price = $ticket->price;
                $seatCount = 1;
                $description = "Entrada - {$ticket->screening->movie->title}";
            }
            
            // Crear orden en PayPal
            $orderData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => 'USD', // Ajustar según tu país
                            'value' => number_format($price, 2, '.', ''),
                        ],
                        'description' => $description,
                        'reference_id' => (string)$paymentTicket->id,
                    ]
                ],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'return_url' => route('api.payment.success', ['provider' => 'paypal']),
                            'cancel_url' => route('api.payment.failure', ['provider' => 'paypal']),
                            'user_action' => 'PAY_NOW',
                        ]
                    ]
                ]
            ];
            
            // Aquí llamarías a PayPal API v2
            // $response = $this->callPayPalAPI('orders', 'POST', $orderData);
            
            $paymentTicket->update([
                'status' => 'processing',
                'initiated_at' => now(),
                'response_data' => $orderData,
            ]);
            
            return [
                'success' => true,
                'requires_redirect' => true,
                'redirect_url' => 'https://www.paypal.com/checkoutnow?token=MOCK_TOKEN', // Mock
                'payment_id' => $paymentTicket->id,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Procesar webhook de PayPal
     * 
     * Soporta múltiples eventos:
     * - PAYMENT.CAPTURE.COMPLETED → Pago aprobado
     * - PAYMENT.CAPTURE.DENIED → Pago rechazado
     * - PAYMENT.CAPTURE.REFUNDED → Reembolso
     */
    public function handleWebhook(Request $request): bool
    {
        try {
            // Step 1: Validar firma del webhook
            try {
                $this->validateWebhookSignature($request);
            } catch (\Exception $e) {
                Log::warning('PayPal: Webhook signature validation failed', [
                    'error' => $e->getMessage(),
                    'ip' => $request->ip(),
                ]);
                return false;
            }

            // Step 2: Extraer datos críticos
            $event = $request->input('event_type');
            $resource = $request->input('resource');

            if (!$event || !$resource) {
                Log::warning('PayPal: Webhook sin event_type o resource', [
                    'event' => $event,
                    'has_resource' => !empty($resource),
                ]);
                return false;
            }

            // Step 3: Obtener Payment ID (puede venir en múltiples lugares)
            $paymentId = $resource['supplementary_data']['related_ids']['order_id'] 
                      ?? $resource['id'] 
                      ?? null;

            if (!$paymentId) {
                Log::warning('PayPal: Webhook sin payment ID identificable', [
                    'event' => $event,
                    'resource_keys' => array_keys($resource ?? []),
                ]);
                return true; // Procesar pero sin hacer nada
            }

            Log::info('PayPal: Webhook recibido', [
                'event' => $event,
                'payment_id' => $paymentId,
            ]);

            // Step 4: Buscar PaymentProviderTicket
            $paymentTicket = PaymentProviderTicket::findByTransactionOrId($paymentId);

            if (!$paymentTicket) {
                Log::warning('PayPal: PaymentProviderTicket no encontrado', [
                    'payment_id' => $paymentId,
                    'event' => $event,
                ]);
                return true; // Procesar pero sin hacer nada
            }

            Log::info('PayPal: PaymentProviderTicket encontrado', [
                'payment_ticket_id' => $paymentTicket->id,
                'event' => $event,
                'order_id' => $paymentTicket->order_id,
            ]);

            // Step 5: Procesar según tipo de evento
            switch ($event) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    try {
                        $paymentTicket->approve([
                            'transaction_id' => $resource['id'],
                            'status' => 'COMPLETED',
                            'webhook_data' => $request->all(),
                            'captured_at' => now()->toIso8601String(),
                        ]);

                        Log::info('PayPal: Pago capturado y aprobado', [
                            'payment_ticket_id' => $paymentTicket->id,
                            'transaction_id' => $resource['id'],
                        ]);
                    } catch (\Exception $e) {
                        // approve() lanzó excepción = finalización falló
                        Log::error('PayPal: Error al capturar pago', [
                            'payment_ticket_id' => $paymentTicket->id,
                            'error' => $e->getMessage(),
                            'transaction_id' => $resource['id'],
                        ]);
                        return false;
                    }
                    break;

                case 'PAYMENT.CAPTURE.DENIED':
                    try {
                        $paymentTicket->decline([
                            'reason' => 'CAPTURE_DENIED',
                            'webhook_data' => $request->all(),
                            'denied_at' => now()->toIso8601String(),
                        ]);

                        Log::info('PayPal: Pago rechazado', [
                            'payment_ticket_id' => $paymentTicket->id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('PayPal: Error al rechazar pago', [
                            'payment_ticket_id' => $paymentTicket->id,
                            'error' => $e->getMessage(),
                        ]);
                        return false;
                    }
                    break;

                case 'PAYMENT.CAPTURE.REFUNDED':
                    try {
                        $refundId = $resource['id'] ?? 'unknown';
                        $paymentTicket->refund("PayPal refund - Refund ID: {$refundId}");

                        Log::info('PayPal: Reembolso procesado', [
                            'payment_ticket_id' => $paymentTicket->id,
                            'refund_id' => $refundId,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('PayPal: Error al procesar reembolso', [
                            'payment_ticket_id' => $paymentTicket->id,
                            'error' => $e->getMessage(),
                        ]);
                        return false;
                    }
                    break;

                default:
                    Log::debug('PayPal: Evento no procesado', [
                        'event' => $event,
                        'payment_ticket_id' => $paymentTicket->id,
                    ]);
                    return true;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('PayPal: Error inesperado procesando webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Validar configuración
     */
    public function validateConfiguration(): bool
    {
        $config = $this->provider->getConfig();
        
        return !empty($config['client_id']) && 
               !empty($config['client_secret']);
    }

    /**
     * Refund
     */
    public function refund(PaymentProviderTicket $paymentTicket, string $reason = ''): bool
    {
        try {
            // Aquí iría la lógica para procesar reembolso en PayPal
            // $response = $this->callPayPalAPI("captures/{$paymentTicket->transaction_id}/refund", 'POST');
            
            $paymentTicket->refund($reason);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtener estado de pago
     */
    public function getPaymentStatus(PaymentProviderTicket $paymentTicket): string
    {
        // Aquí consultarías el estado real en PayPal
        return $paymentTicket->status;
    }

    /**
     * Validar firma del webhook
     */
    protected function validateWebhookSignature(Request $request): void
    {
        // Validar webhook ID y signature contra PayPal
        // Usar secret del provider
    }
}
