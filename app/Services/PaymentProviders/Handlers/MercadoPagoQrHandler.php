<?php

namespace App\Services\PaymentProviders\Handlers;

use Exception;
use App\Services\PaymentProviders\PaymentProviderHandler;
use App\Services\QRCodeGenerator;
use App\Models\PaymentProviderTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Order\OrderClient;
use MercadoPago\Exceptions\MPApiException;

class MercadoPagoQrHandler extends PaymentProviderHandler
{
    const MODE_PREFERENCE = 'preference';
    const MODE_POS_STATIC_ORDER = 'pos_static_order';
    const MIN_AMOUNT_ORDER_QR = 15.00;

    /**
     * ORDER-FIRST: Procesar pago por QR
     * Espera Order creada con asientos reservados en inventory
     * Genera un QR dinámico para escanear
     * Soporta dos modos: preference (dinámico) o pos_static_order (estático del POS)
     * 
     * @param PaymentProviderTicket $paymentTicket (con order_id, sin ticket_id)
     * @param array $additionalData ['order_id', 'total_price', 'seat_count', 'seat_ids']
     * @return array con qr_data, amount, etc
     */
    public function processPayment(PaymentProviderTicket $paymentTicket, array $additionalData = []): array
    {
        try {
            // ORDER-FIRST: Requiere Order, no Ticket
            $order = $paymentTicket->order()->with(['screening.movie'])->first();
            if (!$order) {
                throw new \Exception('No se encontró orden vinculada (order-first flow requiere Order)');
            }

            $screening = $order->screening;
            if (!$screening) {
                throw new \Exception('Screening no encontrado en la orden');
            }

            $mode = $additionalData['qr_mode'] ?? $this->provider->getConfig('qr_mode') ?? self::MODE_POS_STATIC_ORDER;
            
            Log::info('MercadoPagoQR (order-first): Iniciando procesamiento', [
                'payment_ticket_id' => $paymentTicket->id,
                'order_id' => $order->id,
                'mode' => $mode,
            ]);
            
            $this->validateConfiguration($mode);

            $accessToken = $this->provider->getConfig('access_token');
            MercadoPagoConfig::setAccessToken($accessToken);

            $price = floatval($additionalData['total_price'] ?? $order->total_amount);
            $seatCount = intval($additionalData['seat_count'] ?? count($additionalData['seat_ids'] ?? []));

            if ($mode === self::MODE_POS_STATIC_ORDER) {
                return $this->generatePosStaticOrderQr($paymentTicket, $order, $screening, $price, $seatCount, $additionalData);
            } else {
                return $this->generatePreferenceQr($paymentTicket, $order, $screening, $price, $seatCount, $additionalData);
            }

        } catch (\Exception $e) {
            Log::error('MercadoPagoQR: Error procesando pago', [
                'error' => $e->getMessage(),
                'payment_ticket_id' => $paymentTicket->id ?? null,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'qr_data' => null,
            ];
        }
    }

    /**
     * MODO 1: Generar QR dinámico a partir de preferencia de Mercado Pago (ORDER-FIRST)
     */
    private function generatePreferenceQr(PaymentProviderTicket $paymentTicket, $order, $screening, float $price, int $seatCount, array $additionalData = []): array
    {
        try {
            $accessToken = $this->provider->getConfig('access_token');
            MercadoPagoConfig::setAccessToken($accessToken);

            $externalReference = 'CINEA-' . $paymentTicket->id . '-' . Str::random(8);
            
            $client = new PreferenceClient();
            
            $movieTitle = $screening->movie->title ?? 'Cine - Entrada';
            $itemTitle = $seatCount > 1
                ? "{$seatCount} Entradas - {$movieTitle}"
                : "Entrada - {$movieTitle}";
            
            $createData = [
                'items' => [
                    [
                        'title' => $itemTitle,
                        'quantity' => 1,
                        'currency_id' => 'ARS',
                        'unit_price' => floatval($price),
                    ]
                ],
                'external_reference' => $externalReference,
                'notification_url' => route('api.webhook.payment', ['hash' => 'mercadopago']),
                'back_urls' => [
                    'success' => route('api.payment.success'),
                    'failure' => route('api.payment.failure'),
                    'pending' => route('api.payment.pending'),
                ],
            ];

            try {
                $preference = $client->create($createData);
            } catch (MPApiException $sdkException) {
                Log::error('MercadoPagoQR: Error creando preferencia (order-first)', [
                    'message' => $sdkException->getMessage(),
                ]);
                throw new \Exception('Mercado Pago Error: ' . $sdkException->getMessage());
            }

            $preferenceLink = $preference->init_point ?? null;
            if (empty($preferenceLink)) {
                throw new \Exception('No se pudo obtener init_point de la preferencia');
            }

            $qrDataUri = QRCodeGenerator::generateQRDataUri($preferenceLink);
            $qrSvg = QRCodeGenerator::generateQRSvg($preferenceLink);

            // Obtener idempotency_key desde additionalData (si viene del manager)
            // o desde response_data si fue reutilizado
            $idempotencyKey = $additionalData['idempotency_key'] ?? $paymentTicket->response_data['idempotency_key'] ?? null;

            $paymentTicket->update([
                'status' => 'processing',
                'transaction_id' => $preference->id,
                'response_data' => [
                    'qr_mode' => self::MODE_PREFERENCE,
                    'preference_id' => $preference->id,
                    'init_point' => $preferenceLink,
                    'external_reference' => $externalReference,
                    'qr_data_uri' => $qrDataUri,
                    'amount' => $price,
                    'currency' => 'ARS',
                    'movie' => $movieTitle,
                    'seats' => $seatCount,
                    'idempotency_key' => $idempotencyKey,
                ],
            ]);

            return [
                'success' => true,
                'method' => 'qr',
                'qr_mode' => self::MODE_PREFERENCE,
                'qr_data' => $qrDataUri,
                'qr_image' => $qrDataUri,
                'qr_svg' => $qrSvg,
                'preference_id' => $preference->id,
                'init_point' => $preferenceLink,
                'payment_ticket_id' => $paymentTicket->id,
                'amount' => $price,
            ];

        } catch (\Exception $e) {
            Log::error('MercadoPagoQR: Error generando QR dinámico (order-first)', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * MODO 2: Generar Order QR + usar POS estático
     * Crea una Order type="qr" asociada a external_pos_id
     * Devuelve los datos para mostrar QR estático del POS
     * ORDER-FIRST
     */
    private function generatePosStaticOrderQr(PaymentProviderTicket $paymentTicket, $order, $screening, float $price, int $seatCount, array $additionalData = []): array
    {
        try {
            if ($price < self::MIN_AMOUNT_ORDER_QR) {
                throw new \Exception("Monto mínimo para Order QR es ARS " . self::MIN_AMOUNT_ORDER_QR);
            }

            $accessToken = $this->provider->getConfig('access_token');
            $externalPosId = $this->provider->getConfig('external_pos_id') ?? 'default';
            
            MercadoPagoConfig::setAccessToken($accessToken);

            $externalReference = 'CINEA-' . $paymentTicket->id . '-' . Str::random(8);
            
            $client = new OrderClient();
            
            $createData = [
                'type' => 'qr',
                'external_reference' => $externalReference,
                'transactions' => [
                    'payments' => [
                        [
                            'amount' => number_format($price, 2, '.', ''),
                        ]
                    ]
                ],
                'config' => [
                    'qr' => [
                        'external_pos_id' => $externalPosId,
                    ]
                ]
            ];

            try {
                $mpOrder = $client->create($createData);
            } catch (MPApiException $sdkException) {
                Log::error('MercadoPagoQR: Error creando Order QR (order-first)', [
                    'message' => $sdkException->getMessage(),
                ]);
                throw new \Exception('Mercado Pago Order Error: ' . $sdkException->getMessage());
            }

            $orderId = $mpOrder->id ?? null;
            if (empty($orderId)) {
                throw new \Exception('No se pudo obtener order_id de la Order QR');
            }

            $posQrImageUrl = $this->getPosQrImageUrl($externalPosId);

            $movieTitle = $screening->movie->title ?? 'Cine - Entrada';
            
            // Obtener idempotency_key desde additionalData o desde response_data si fue reutilizado
            $idempotencyKey = $additionalData['idempotency_key'] ?? $paymentTicket->response_data['idempotency_key'] ?? null;
            
            $responseData = [
                'qr_mode' => self::MODE_POS_STATIC_ORDER,
                'order_id' => $orderId,
                'external_reference' => $externalReference,
                'external_pos_id' => $externalPosId,
                'amount' => $price,
                'currency' => 'ARS',
                'movie' => $movieTitle,
                'seats' => $seatCount,
                'idempotency_key' => $idempotencyKey,
            ];
            
            if (!empty($posQrImageUrl)) {
                $responseData['qr_image_url'] = $posQrImageUrl;
            }

            $paymentTicket->update([
                'status' => 'processing',
                'transaction_id' => $orderId,
                'response_data' => $responseData,
            ]);

            $response = [
                'success' => true,
                'method' => 'qr',
                'qr_mode' => self::MODE_POS_STATIC_ORDER,
                'mode' => 'pos_static',
                'order_id' => $orderId,
                'external_reference' => $externalReference,
                'external_pos_id' => $externalPosId,
                'payment_ticket_id' => $paymentTicket->id,
                'amount' => $price,
            ];
            
            if (!empty($posQrImageUrl)) {
                $response['qr_image_url'] = $posQrImageUrl;
                $response['qr_data'] = $posQrImageUrl;
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('MercadoPagoQR: Error en modo pos_static_order', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtener URL de imagen QR estática del POS de Mercado Pago
     */
    private function getPosQrImageUrl(string $externalPosId): ?string
    {
        try {
            $accessToken = $this->provider->getConfig('access_token');
            if (empty($accessToken)) {
                return null;
            }

            $response = Http::withToken($accessToken)
                ->get('https://api.mercadopago.com/pos', [
                    'external_id' => $externalPosId,
                ]);

            if (!$response->successful()) {
                Log::warning('MercadoPagoQR: Error obteniendo POS', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $posData = $response->json();
            if (empty($posData['results']) || count($posData['results']) === 0) {
                return null;
            }

            $pos = $posData['results'][0];
            $posId = $pos['id'] ?? null;
            if (empty($posId)) {
                return null;
            }

            $response = Http::withToken($accessToken)
                ->get("https://api.mercadopago.com/pos/{$posId}");

            if (!$response->successful()) {
                Log::warning('MercadoPagoQR: Error obteniendo detalles del POS', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $posDetails = $response->json();
            return $posDetails['qr']['image'] ?? null;

        } catch (\Exception $e) {
            Log::warning('MercadoPagoQR: Error al obtener QR estático del POS', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Procesar webhook de confirmación de pago QR
     * Soporta múltiples formatos y tipos de notificaciones
     * 
     * @param Request $request
     * @return bool Success indica si webhook fue procesado en general
     *              (no necesariamente que el pago fue aprobado,
     *               solo que no hubo error técnico)
     */
    public function handleWebhook(Request $request): bool
    {
        try {
            $data = $request->all();
            
            Log::info('MercadoPagoQR: Webhook recibido', [
                'data_keys' => array_keys($data),
            ]);

            // Step 1: Extraer ID externo (puede venir en múltiples formatos)
            $externalId = $this->extractExternalId($data);
            if (empty($externalId)) {
                Log::warning('MercadoPagoQR: Webhook sin ID identificable');
                return true; // Procesar pero sin hacer nada
            }

            Log::info('MercadoPagoQR: ID externo extraído', [
                'external_id' => $externalId,
            ]);

            // Step 2: Buscar PaymentProviderTicket
            $paymentTicket = $this->findPaymentTicket($externalId, $data);
            if (!$paymentTicket) {
                Log::warning('MercadoPagoQR: PaymentProviderTicket no encontrado', [
                    'external_id' => $externalId,
                ]);
                return true; // Procesar pero sin hacer nada
            }

            // Step 3: Obtener status del webhook
            $status = $this->getPaymentStatusFromWebhook($data, $externalId);
            $mappedStatus = $this->mapPaymentStatus($status);

            Log::info('MercadoPagoQR: Status mapeado', [
                'payment_ticket_id' => $paymentTicket->id,
                'transaction_id' => $externalId,
                'original_status' => $status,
                'mapped_status' => $mappedStatus,
                'order_id' => $paymentTicket->order_id,
            ]);

            // Step 4: Procesar según status mapeado
            if ($mappedStatus === 'approved') {
                try {
                    $paymentTicket->approve([
                        'external_payment_id' => $externalId,
                        'webhook_data' => $data,
                        'qr_approved_at' => now()->toIso8601String(),
                    ]);

                    Log::info('MercadoPagoQR: Pago QR aprobado', [
                        'payment_ticket_id' => $paymentTicket->id,
                        'external_id' => $externalId,
                    ]);
                } catch (\Exception $e) {
                    // approve() lanzó excepción = finalización falló
                    Log::error('MercadoPagoQR: Error al aprobar pago QR', [
                        'payment_ticket_id' => $paymentTicket->id,
                        'error' => $e->getMessage(),
                        'external_id' => $externalId,
                    ]);
                    return false; // Reintentar webhook
                }
            } else {
                // No aprobado: update status sin llamar approve()
                $responseData = $paymentTicket->response_data ?? [];
                $responseData = array_merge($responseData, [
                    'webhook_status' => $status,
                    'webhook_processed_at' => now()->toIso8601String(),
                    'webhook_data' => $data,
                ]);
                
                try {
                    $paymentTicket->update([
                        'status' => $mappedStatus,
                        'response_data' => $responseData,
                    ]);

                    Log::info('MercadoPagoQR: Status actualizado (no aprobado)', [
                        'payment_ticket_id' => $paymentTicket->id,
                        'external_id' => $externalId,
                        'new_status' => $mappedStatus,
                    ]);
                } catch (\Exception $e) {
                    Log::error('MercadoPagoQR: Error al actualizar pago', [
                        'payment_ticket_id' => $paymentTicket->id,
                        'error' => $e->getMessage(),
                    ]);
                    return false;
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('MercadoPagoQR: Error inesperado procesando webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Extraer ID externo del webhook (puede venir en múltiples formatos)
     */
    private function extractExternalId(array $data): ?string
    {
        if (isset($data['data']['id'])) {
            return $data['data']['id'];
        }
        if (isset($data['id'])) {
            return $data['id'];
        }
        return null;
    }

    /**
     * Buscar PaymentProviderTicket por transaction_id, external_reference o payment_provider_ticket_id
     */
    private function findPaymentTicket(string $externalId, array $data): ?PaymentProviderTicket
    {
        // Primero intentar con el método estático que busca transaction_id
        $paymentTicket = PaymentProviderTicket::findByTransactionOrId($externalId);
        
        if ($paymentTicket) {
            return $paymentTicket;
        }

        // Intentar por external_reference en response_data (JSON)
        $externalReference = $data['external_reference'] ?? $data['data']['external_reference'] ?? null;
        if (!empty($externalReference)) {
            $paymentTicket = PaymentProviderTicket::whereJsonContains('response_data->external_reference', $externalReference)->first();
            if ($paymentTicket) {
                return $paymentTicket;
            }
            
            // Fallback: buscar en reference_number (si se usara)
            $paymentTicket = PaymentProviderTicket::where('reference_number', $externalReference)->first();
            if ($paymentTicket) {
                return $paymentTicket;
            }
        }

        Log::warning('MercadoPagoQR: PaymentProviderTicket no encontrado', [
            'transaction_id' => $externalId,
            'external_reference' => $externalReference,
        ]);
        
        return null;
    }

    /**
     * Obtener estado del pago desde el webhook o consultando MP API
     */
    private function getPaymentStatusFromWebhook(array $data, string $externalId): ?string
    {
        $status = $data['data']['status'] ?? $data['status'] ?? null;
        
        if (!empty($status)) {
            return $status;
        }

        return $this->queryMercadoPagoStatus($externalId, $data);
    }

    /**
     * Consultar estado a Mercado Pago API si no viene en el webhook
     * Detecta si es order o payment
     */
    private function queryMercadoPagoStatus(string $externalId, array $data): ?string
    {
        try {
            $resourceType = $this->detectResourceType($data);
            if (empty($resourceType)) {
                Log::warning('MercadoPagoQR: No se pudo determinar tipo de recurso');
                return null;
            }

            $accessToken = $this->provider->getConfig('access_token');
            if (empty($accessToken)) {
                return null;
            }

            $endpoint = "https://api.mercadopago.com/{$resourceType}/{$externalId}";
            
            $response = Http::withToken($accessToken)->get($endpoint);
            
            if (!$response->successful()) {
                Log::warning('MercadoPagoQR: Error consultando MP API', [
                    'status' => $response->status(),
                    'endpoint' => $endpoint,
                ]);
                return null;
            }

            $apiData = $response->json();
            
            if ($resourceType === 'payments') {
                return $apiData['status'] ?? null;
            } elseif ($resourceType === 'orders') {
                return $apiData['status'] ?? null;
            }

            return null;

        } catch (\Exception $e) {
            Log::warning('MercadoPagoQR: Error al consultar MP API', [
                'error' => $e->getMessage(),
                'external_id' => $externalId,
            ]);
            return null;
        }
    }

    /**
     * Detectar si el recurso es payment u order
     */
    private function detectResourceType(array $data): ?string
    {
        $type = $data['type'] ?? $data['data']['type'] ?? null;
        
        if ($type === 'payment' || $type === 'payment.created' || $type === 'payment.updated') {
            return 'payments';
        }
        if ($type === 'order' || $type === 'order.created' || $type === 'order.updated') {
            return 'orders';
        }
        if ($type === 'merchant_order') {
            return 'merchant_orders';
        }

        return null;
    }

    /**
     * Mapear estados de Mercado Pago a nuestros estados
     */
    private function mapPaymentStatus(string $status = null): string
    {
        if ($status === 'approved') {
            return 'approved';
        }
        if ($status === 'pending') {
            return 'pending';
        }
        if (in_array($status, ['rejected', 'declined', 'cancelled', 'refunded'])) {
            return 'declined';
        }

        return 'pending';
    }

    /**
     * Validar configuración del proveedor según el modo
     * 
     * @param string|null $mode Modo a validar (preference o pos_static_order)
     * @return bool
     * @throws Exception Si falta configuración crítica
     */
    public function validateConfiguration(string $mode = null): bool
    {
        $mode = $mode ?? $this->provider->getConfig('qr_mode') ?? self::MODE_PREFERENCE;
        
        $accessToken = $this->provider->getConfig('access_token');
        if (empty($accessToken)) {
            throw new \Exception('Access token de Mercado Pago no configurado');
        }

        if ($mode === self::MODE_POS_STATIC_ORDER) {
            $externalPosId = $this->provider->getConfig('external_pos_id') ?? 'default';
            if (empty($externalPosId)) {
                throw new \Exception('external_pos_id no configurado para modo pos_static_order');
            }
        }

        return true;
    }

    /**
     * Reembolsar pago
     * 
     * @param PaymentProviderTicket $paymentTicket
     * @param string $reason Motivo del reembolso
     * @return bool
     */
    public function refund(PaymentProviderTicket $paymentTicket, string $reason = ''): bool
    {
        try {
            $accessToken = $this->provider->getConfig('access_token');
            MercadoPagoConfig::setAccessToken($accessToken);

            $responseData = $paymentTicket->response_data ?? [];
            $responseData = array_merge($responseData, [
                'refund_reason' => $reason,
                'refunded_at' => now()->toIso8601String(),
            ]);
            
            $paymentTicket->update([
                'status' => 'refunded',
                'response_data' => $responseData,
            ]);

            Log::info('MercadoPagoQR: Reembolso procesado', [
                'payment_ticket_id' => $paymentTicket->id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('MercadoPagoQR: Error al reembolsar', [
                'error' => $e->getMessage(),
                'payment_ticket_id' => $paymentTicket->id ?? null,
            ]);
            return false;
        }
    }

    /**
     * Obtener estado actual del pago
     * 
     * @param PaymentProviderTicket $paymentTicket
     * @return string Estado del pago (approved, pending, declined, unknown)
     */
    public function getPaymentStatus(PaymentProviderTicket $paymentTicket): string
    {
        return $paymentTicket->status ?? 'pending';
    }
}
