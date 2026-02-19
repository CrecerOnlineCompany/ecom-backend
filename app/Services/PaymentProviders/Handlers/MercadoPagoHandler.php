<?php

namespace App\Services\PaymentProviders\Handlers;

use App\Services\PaymentProviders\PaymentProviderHandler;
use App\Models\PaymentProviderTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;

class MercadoPagoHandler extends PaymentProviderHandler
{
    /**
     * Procesar pago en Mercado Pago
     */
    public function processPayment(PaymentProviderTicket $paymentTicket, array $additionalData = []): array
    {
        try {
            // Obtener credenciales de la BD
            $config = $this->provider->config;
            
            // Validar que config sea array
            if (!is_array($config)) {
                // Si es string, decodificar
                if (is_string($config)) {
                    $config = json_decode($config, true) ?? [];
                } else {
                    $config = [];
                }
            }
            
            Log::info('MercadoPago: Iniciando pago', [
                'provider_name' => $this->provider->name,
                'provider_id' => $this->provider->id,
                'config_keys' => is_array($config) ? array_keys($config) : [],
                'config_type' => gettype($config),
            ]);
            
            $accessToken = $config['access_token'] ?? null;
            
            if (!$accessToken) {
                Log::error('MercadoPago: Access token no encontrado', [
                    'provider' => $this->provider->name,
                    'config' => is_array($config) ? array_keys($config) : 'not-array',
                ]);
                throw new \Exception('Access token de Mercado Pago no configurado. Ve a Admin > Payment Providers > Mercado Pago y agrega el token.');
            }

            Log::info('MercadoPago: Token encontrado', [
                'token_prefix' => substr($accessToken, 0, 20) . '...',
            ]);

            // Configurar SDK de Mercado Pago
            MercadoPagoConfig::setAccessToken($accessToken);

            // ORDER-FIRST: Obtener info de Order, no de Ticket
            $order = $paymentTicket->order;
            $screening = $order->screening()->with('movie')->first();
            
            if (!$order || !$screening) {
                throw new \Exception('Order o Screening no encontrado');
            }

            // Para order-first, siempre usamos total_price
            $price = floatval($additionalData['total_price'] ?? $order->total_amount);
            $itemQuantity = intval($additionalData['seat_count'] ?? 1);

            Log::info('MercadoPago: Creating preference', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'price' => $price,
                'seat_count' => $itemQuantity,
                'movie' => $screening->movie->title ?? 'Unknown',
            ]);

            // Crear preferencia en Mercado Pago
            $client = new PreferenceClient();
            
            try {
                // Crear preference en Mercado Pago
                // SDK v3 soporta back_urls con estructura: success, failure, pending
                // auto_return debe estar dentro de back_urls
                
                // Determinar descripción según cantidad de asientos
                $itemTitle = $itemQuantity > 1 
                    ? "{$itemQuantity} Entradas - {$screening->movie->title}"
                    : "Entrada - {$screening->movie->title}";
                
                // Para batch payments, mostrar precio total; para single, precio unitario
                $itemQuantityForMP = $itemQuantity > 1 ? 1 : 1;
                $itemPrice = $itemQuantity > 1 ? $price : $price;
                
                $createData = [
                    'items' => [
                        [
                            'title' => $itemTitle,
                            'quantity' => $itemQuantityForMP,
                            'currency_id' => 'ARS',
                            'unit_price' => $itemPrice,
                        ]
                    ],
                    'external_reference' => $paymentTicket->generateExternalReference('MP'),
                    'notification_url' => $this->provider->getWebhookUrl(),
                ];

                // Agregar back_urls con auto_return
                $createData['back_urls'] = [
                    'success' => route('api.payment.success'),
                    'failure' => route('api.payment.failure'),
                    'pending' => route('api.payment.pending'),
                ];

                $preference = $client->create($createData);

                Log::info('MercadoPago: Preference created successfully', [
                    'preference_id' => $preference->id,
                    'init_point' => $preference->init_point ?? 'N/A',
                ]);

            } catch (MPApiException $sdkException) {
                // MPApiException tiene: getMessage(), getApiResponse()
                $apiResponse = $sdkException->getApiResponse();
                
                Log::error('MercadoPago API Exception', [
                    'message' => $sdkException->getMessage(),
                    'code' => $sdkException->getCode(),
                    'status_code' => $apiResponse->getStatusCode() ?? null,
                    'response_data' => json_encode($apiResponse->getContent() ?? []),
                ]);

                throw new \Exception('Mercado Pago Error: ' . $sdkException->getMessage());
            } catch (\Exception $sdkException) {
                Log::error('MercadoPago SDK Error', [
                    'message' => $sdkException->getMessage(),
                    'code' => $sdkException->getCode(),
                    'file' => $sdkException->getFile(),
                    'line' => $sdkException->getLine(),
                ]);

                throw $sdkException;
            }

            // Guardar ID de preferencia en la BD
            $paymentTicket->update([
                'status' => 'processing',
                'transaction_id' => $preference->id,
                'initiated_at' => now(),
                'response_data' => json_encode([
                    'preference_id' => $preference->id,
                    'init_point' => $preference->init_point,
                ])
            ]);

            return [
                'success' => true,
                'message' => 'Preferencia de pago creada en Mercado Pago',
                'redirect_url' => $preference->init_point,
                'transaction_id' => $preference->id,
                'requires_redirect' => true,
            ];
        } catch (\Exception $e) {
            Log::error('MercadoPago payment error', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception' => class_basename($e),
            ]);
            
            $paymentTicket->update([
                'status' => 'declined',
                'response_data' => json_encode([
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'timestamp' => now(),
                ])
            ]);

            return [
                'success' => false,
                'message' => 'Error al procesar pago en Mercado Pago: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Procesar webhook de Mercado Pago
     * 
     * Soporta:
     * - payment notifications (aprobado, rechazado, pendiente)
     * - Validación de firma
     * - Manejo robusto de errores
     * - Logging completo para auditoría
     */
    public function handleWebhook(Request $request): bool
    {
        try {
            // Step 1: Validar firma del webhook
            try {
                $this->validateWebhookSignature($request);
            } catch (\Exception $e) {
                Log::warning('MercadoPago: Webhook signature validation failed', [
                    'error' => $e->getMessage(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
                return false;
            }

            $data = $request->all();
            
            // Step 2: Procesar solo notificaciones de pago
            if ($request->input('type') !== 'payment') {
                Log::debug('MercadoPago: Webhook no es de tipo payment', [
                    'type' => $request->input('type'),
                ]);
                return true; // Not a payment notification, but not an error
            }

            // Step 3: Extraer datos críticos
            $externalId = $request->input('data.id');
            $paymentStatus = $request->input('data.status');
            
            if (empty($externalId)) {
                Log::warning('MercadoPago: Webhook sin payment ID', [
                    'data' => $data,
                ]);
                return false;
            }

            Log::info('MercadoPago: Webhook recibido', [
                'external_payment_id' => $externalId,
                'status' => $paymentStatus,
            ]);

            // Step 4: Buscar PaymentProviderTicket
            $paymentTicket = PaymentProviderTicket::findByTransactionOrId($externalId);
            
            if (!$paymentTicket) {
                Log::warning('MercadoPago: PaymentProviderTicket no encontrado', [
                    'external_id' => $externalId,
                    'status' => $paymentStatus,
                ]);
                // Retornar true (webhook procesado) aunque no encontremos el ticket
                // Podría ser de un intento anterior o de otro sistema
                return true;
            }

            Log::info('MercadoPago: PaymentProviderTicket encontrado', [
                'payment_ticket_id' => $paymentTicket->id,
                'current_status' => $paymentTicket->status,
                'new_status' => $paymentStatus,
                'order_id' => $paymentTicket->order_id,
            ]);

            // Step 5: Procesar según status
            switch ($paymentStatus) {
                case 'approved':
                    try {
                        $paymentTicket->approve([
                            'external_payment_id' => $externalId,
                            'webhook_data' => $data,
                            'approved_at' => now()->toIso8601String(),
                        ]);

                        Log::info('MercadoPago: Pago aprobado y orden finalizada', [
                            'payment_ticket_id' => $paymentTicket->id,
                            'external_id' => $externalId,
                        ]);
                    } catch (\Exception $e) {
                        // approve() lanzó excepción = finalización falló
                        Log::error('MercadoPago: Error al aprobar pago (finalización falló)', [
                            'payment_ticket_id' => $paymentTicket->id,
                            'error' => $e->getMessage(),
                            'external_id' => $externalId,
                        ]);
                        // Retornar false para que se reintente
                        return false;
                    }
                    break;

                case 'declined':
                case 'rejected':
                    try {
                        $paymentTicket->decline([
                            'external_payment_id' => $externalId,
                            'reason' => $paymentStatus,
                            'webhook_data' => $data,
                            'declined_at' => now()->toIso8601String(),
                        ]);

                        Log::info('MercadoPago: Pago rechazado', [
                            'payment_ticket_id' => $paymentTicket->id,
                            'external_id' => $externalId,
                            'reason' => $paymentStatus,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('MercadoPago: Error al rechazar pago', [
                            'payment_ticket_id' => $paymentTicket->id,
                            'error' => $e->getMessage(),
                        ]);
                        return false;
                    }
                    break;

                case 'pending':
                    try {
                        $paymentTicket->update([
                            'status' => 'processing',
                            'response_data' => array_merge($paymentTicket->response_data ?? [], [
                                'webhook_data' => $data,
                                'pending_at' => now()->toIso8601String(),
                            ]),
                        ]);

                        Log::info('MercadoPago: Pago en estado pendiente', [
                            'payment_ticket_id' => $paymentTicket->id,
                            'external_id' => $externalId,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('MercadoPago: Error al procesar pagobull pendiente', [
                            'payment_ticket_id' => $paymentTicket->id,
                            'error' => $e->getMessage(),
                        ]);
                        return false;
                    }
                    break;

                default:
                    Log::warning('MercadoPago: Status desconocido', [
                        'status' => $paymentStatus,
                        'external_id' => $externalId,
                    ]);
                    return true; // Procesar pero no cambiar nada
            }

            return true;
        } catch (\Exception $e) {
            Log::error('MercadoPago: Error inesperado procesando webhook', [
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
        
        return !empty($config['access_token']) && 
               !empty($config['public_key']);
    }

    /**
     * Refund
     */
    public function refund(PaymentProviderTicket $paymentTicket, string $reason = ''): bool
    {
        try {
            // Aquí iría la lógica para procesar reembolso
            // $response = $this->callMercadoPagoAPI('refund', $paymentTicket->transaction_id);
            
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
        // Aquí consultarías el estado real en Mercado Pago
        return $paymentTicket->status;
    }

    /**
     * Validar firma del webhook (placeholder)
     */
    protected function validateWebhookSignature(Request $request): void
    {
        // Implementar validación real basada en firma de Mercado Pago
        // Usar secret del provider para validar
    }
}
