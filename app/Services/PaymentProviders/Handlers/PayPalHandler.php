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
     */
    public function handleWebhook(Request $request): bool
    {
        try {
            $this->validateWebhookSignature($request);
            
            $event = $request->input('event_type');
            $resource = $request->input('resource');
            
            $paymentId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
            
            if (!$paymentId) {
                return false;
            }
            
            $paymentTicket = PaymentProviderTicket::findByTransactionOrId($paymentId);
            
            if (!$paymentTicket) {
                return false;
            }
            
            switch ($event) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    $paymentTicket->approve([
                        'transaction_id' => $resource['id'],
                        'status' => 'COMPLETED',
                        'webhook_data' => $request->all(),
                    ]);
                    break;
                    
                case 'PAYMENT.CAPTURE.DENIED':
                    $paymentTicket->decline([
                        'reason' => 'CAPTURE_DENIED',
                        'webhook_data' => $request->all(),
                    ]);
                    break;
                    
                case 'PAYMENT.CAPTURE.REFUNDED':
                    $paymentTicket->refund('PayPal refund');
                    break;
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('PayPal webhook error: ' . $e->getMessage());
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
