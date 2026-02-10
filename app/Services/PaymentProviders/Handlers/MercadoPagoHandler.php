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

            $ticket = $paymentTicket->ticket()->with(['screening.movie'])->first();
            
            if (!$ticket) {
                throw new \Exception('Ticket no encontrado');
            }

            // Usar total_price si está disponible (batch payment de múltiples asientos)
            // Si no, usar el precio del ticket individual
            if (!empty($additionalData['total_price'])) {
                $price = floatval($additionalData['total_price']);
                $itemQuantity = intval($additionalData['seat_count'] ?? 1);
            } else {
                $price = floatval($ticket->price);
                $itemQuantity = 1;
            }

            Log::info('MercadoPago: Creating preference', [
                'ticket_id' => $ticket->id,
                'price' => $price,
                'seat_count' => $itemQuantity,
                'movie' => $ticket->screening->movie->title ?? 'Unknown',
            ]);

            // Crear preferencia en Mercado Pago
            $client = new PreferenceClient();
            
            try {
                // Crear preference en Mercado Pago
                // SDK v3 soporta back_urls con estructura: success, failure, pending
                // auto_return debe estar dentro de back_urls
                
                // Determinar descripción según cantidad de asientos
                $itemTitle = $itemQuantity > 1 
                    ? "{$itemQuantity} Entradas - {$ticket->screening->movie->title}"
                    : "Entrada - {$ticket->screening->movie->title}";
                
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
                    'external_reference' => (string)$paymentTicket->id,
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
     */
    public function handleWebhook(Request $request): bool
    {
        try {
            // Validar firma/token del webhook
            $this->validateWebhookSignature($request);
            
            $data = $request->all();
            
            if ($request->input('type') === 'payment') {
                $externalId = $request->input('data.id');
                $paymentStatus = $request->input('data.status');
                
                $paymentTicket = PaymentProviderTicket::where('transaction_id', $externalId)->first();
                
                if (!$paymentTicket) {
                    return false;
                }
                
                switch ($paymentStatus) {
                    case 'approved':
                        $paymentTicket->approve([
                            'external_payment_id' => $externalId,
                            'webhook_data' => $data,
                        ]);
                        break;
                    case 'declined':
                    case 'rejected':
                        $paymentTicket->decline([
                            'external_payment_id' => $externalId,
                            'reason' => $paymentStatus,
                            'webhook_data' => $data,
                        ]);
                        break;
                    case 'pending':
                        $paymentTicket->update([
                            'status' => 'processing',
                            'response_data' => array_merge($paymentTicket->response_data ?? [], $data),
                        ]);
                        break;
                }
                
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('MercadoPago webhook error: ' . $e->getMessage());
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
