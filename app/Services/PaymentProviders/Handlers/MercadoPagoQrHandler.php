<?php

namespace App\Services\PaymentProviders\Handlers;

use Exception;
use App\Services\PaymentProviders\PaymentProviderHandler;
use App\Services\QRCodeGenerator;
use App\Models\PaymentProviderTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
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

            // Default a preference para mantener QR dinámico con monto predefinido.
            $mode = $additionalData['qr_mode'] ?? $this->provider->getConfig('qr_mode') ?? self::MODE_PREFERENCE;
            
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

            // Usar referencia estable con order_number para facilitar correlación en webhooks.
            $externalReference = $paymentTicket->generateExternalReference('QR');
            
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
                'notification_url' => route('api.webhook.payment', ['hash' => $this->provider->webhook_secret]),
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

            // Usar referencia estable con order_number para facilitar correlación en webhooks.
            $externalReference = $paymentTicket->generateExternalReference('QR');
            
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
                    'message' => $sdkException->getApiResponse(),
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
            $webhookId = \Illuminate\Support\Str::uuid();
            
            Log::info('MercadoPagoQR: Webhook recibido', [
                'webhook_id' => $webhookId,
                'data_keys' => array_keys($data),
                'full_data' => json_encode($data),
                'headers' => $request->headers->all(),
                'method' => $request->method(),
                'url' => $request->url(),
            ]);

            // Step 1: Extraer ID externo (puede venir en múltiples formatos)
            $externalId = $this->extractExternalId($data);
            if (empty($externalId)) {
                Log::warning('MercadoPagoQR: Webhook sin ID identificable', [
                    'webhook_id' => $webhookId,
                    'data_structure' => json_encode(array_keys($data)),
                    'data' => json_encode($data),
                ]);
                return true; // Procesar pero sin hacer nada
            }

            Log::info('MercadoPagoQR: ID externo extraído exitosamente', [
                'webhook_id' => $webhookId,
                'external_id' => $externalId,
                'data_keys' => array_keys($data),
            ]);

            // Step 2: Buscar PaymentProviderTicket
            Log::info('MercadoPagoQR: Buscando PaymentProviderTicket', [
                'webhook_id' => $webhookId,
                'external_id' => $externalId,
            ]);
            $paymentTicket = $this->findPaymentTicket($externalId, $data, $webhookId);
            if (!$paymentTicket) {
                Log::warning('MercadoPagoQR: PaymentProviderTicket no encontrado - PROBLEMA CRÍTICO', [
                    'webhook_id' => $webhookId,
                    'external_id' => $externalId,
                    'all_request_data' => json_encode($data),
                ]);
                return true; // Procesar pero sin hacer nada
            }
            Log::info('MercadoPagoQR: PaymentProviderTicket encontrado', [
                'webhook_id' => $webhookId,
                'payment_ticket_id' => $paymentTicket->id,
                'current_status' => $paymentTicket->status,
                'transaction_id' => $paymentTicket->transaction_id,
            ]);

            // Step 3: Obtener status del webhook
            Log::info('MercadoPagoQR: Obteniendo status del pago', [
                'webhook_id' => $webhookId,
                'external_id' => $externalId,
                'payment_ticket_id' => $paymentTicket->id,
            ]);
            $status = $this->getPaymentStatusFromWebhook($data, $externalId, $webhookId);
            $mappedStatus = $this->mapPaymentStatus($status);

            Log::info('MercadoPagoQR: Status obtenido y mapeado', [
                'webhook_id' => $webhookId,
                'payment_ticket_id' => $paymentTicket->id,
                'transaction_id' => $externalId,
                'original_status' => $status,
                'mapped_status' => $mappedStatus,
                'order_id' => $paymentTicket->order_id,
                'previous_status' => $paymentTicket->status,
            ]);

            // Step 4: Procesar según status mapeado
            Log::info('MercadoPagoQR: Iniciando Step 4 - Procesamiento por status', [
                'webhook_id' => $webhookId,
                'mapped_status' => $mappedStatus,
                'payment_ticket_id' => $paymentTicket->id,
            ]);
            
            if ($mappedStatus === 'approved') {
                try {
                    Log::info('MercadoPagoQR: Intentando aprobar pago', [
                        'webhook_id' => $webhookId,
                        'payment_ticket_id' => $paymentTicket->id,
                        'external_id' => $externalId,
                    ]);
                    
                    $paymentTicket->approve([
                        'external_payment_id' => $externalId,
                        'webhook_data' => $data,
                        'qr_approved_at' => now()->toIso8601String(),
                    ]);

                    Log::info('MercadoPagoQR: Pago QR aprobado exitosamente', [
                        'webhook_id' => $webhookId,
                        'payment_ticket_id' => $paymentTicket->id,
                        'external_id' => $externalId,
                        'order_id' => $paymentTicket->order_id,
                    ]);
                } catch (\Exception $e) {
                    // approve() lanzó excepción = finalización falló
                    Log::error('MercadoPagoQR: Error crítico al aprobar pago QR', [
                        'webhook_id' => $webhookId,
                        'payment_ticket_id' => $paymentTicket->id,
                        'error' => $e->getMessage(),
                        'error_trace' => $e->getTraceAsString(),
                        'external_id' => $externalId,
                    ]);
                    return false; // Reintentar webhook
                }
            } else {
                // No aprobado: update status sin llamar approve()
                Log::info('MercadoPagoQR: Pago no aprobado - actualizando status sin finalizar', [
                    'webhook_id' => $webhookId,
                    'payment_ticket_id' => $paymentTicket->id,
                    'new_status' => $mappedStatus,
                    'original_status' => $status,
                ]);
                
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
                        'webhook_id' => $webhookId,
                        'payment_ticket_id' => $paymentTicket->id,
                        'external_id' => $externalId,
                        'new_status' => $mappedStatus,
                    ]);
                } catch (\Exception $e) {
                    Log::error('MercadoPagoQR: Error al actualizar pago en BD', [
                        'webhook_id' => $webhookId,
                        'payment_ticket_id' => $paymentTicket->id,
                        'error' => $e->getMessage(),
                        'error_trace' => $e->getTraceAsString(),
                    ]);
                    return false;
                }
            }

            Log::info('MercadoPagoQR: Webhook procesado exitosamente', [
                'webhook_id' => $webhookId,
                'payment_ticket_id' => $paymentTicket->id,
                'final_status' => $mappedStatus,
            ]);
            
            return true;

        } catch (\Exception $e) {
            Log::error('MercadoPagoQR: Error inesperado procesando webhook', [
                'webhook_id' => $webhookId ?? null,
                'error' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return false;
        }
    }

    /**
     * Extraer ID externo del webhook (puede venir en múltiples formatos)
     */
    private function extractExternalId(array $data): ?string
    {
        
        // Intentar múltiples rutas posibles
        $possiblePaths = [
            $data['data']['id'] ?? null,
            $data['id'] ?? null,
            $data['resource']['id'] ?? null,
            $data['data']['payment_id'] ?? null,
            $data['payment_id'] ?? null,
        ];
        
        foreach ($possiblePaths as $path) {
            if (!empty($path)) {
                Log::debug('MercadoPagoQR: ID extraído', [
                    'found_id' => $path,
                    'source_data_keys' => array_keys($data),
                ]);
                return $path;
            }
        }
        
        // Si no encontramos, loguear la estructura completa
        Log::warning('MercadoPagoQR: No se pudo extraer ID de ningún path conocido', [
            'data_structure' => json_encode($data),
            'attempted_paths' => ['data.id', 'id', 'resource.id', 'data.payment_id', 'payment_id'],
        ]);
        
        return null;
    }

    /**
     * Buscar PaymentProviderTicket por transaction_id, external_reference o payment_provider_ticket_id
     */
    private function findPaymentTicket(string $externalId, array $data, ?string $webhookId = null): ?PaymentProviderTicket
    {
        // Primero intentar con el método estático que busca transaction_id
        Log::info('MercadoPagoQR: Buscando por transaction_id', [
            'webhook_id' => $webhookId,
            'external_id' => $externalId,
        ]);
        
        $paymentTicket = PaymentProviderTicket::findByTransactionOrId($externalId);
        
        if ($paymentTicket) {
            Log::info('MercadoPagoQR: ✓ Encontrado por findByTransactionOrId', [
                'webhook_id' => $webhookId,
                'payment_ticket_id' => $paymentTicket->id,
                'method' => 'findByTransactionOrId',
            ]);
            return $paymentTicket;
        }
        
        Log::warning('MercadoPagoQR: No encontrado por findByTransactionOrId', [
            'webhook_id' => $webhookId,
            'external_id' => $externalId,
        ]);

        // Intentar por external_reference en response_data (JSON)
        $externalReference = $data['external_reference'] ?? $data['data']['external_reference'] ?? null;
        
        Log::info('MercadoPagoQR: Intentando búsqueda por external_reference', [
            'webhook_id' => $webhookId,
            'external_reference' => $externalReference,
        ]);
        
        if (!empty($externalReference)) {
            // Método 1: JSON contains en response_data
            $paymentTicket = PaymentProviderTicket::whereJsonContains('response_data->external_reference', $externalReference)->first();
            if ($paymentTicket) {
                Log::info('MercadoPagoQR: ✓ Encontrado por JSON contains en response_data', [
                    'webhook_id' => $webhookId,
                    'payment_ticket_id' => $paymentTicket->id,
                    'method' => 'json_contains',
                ]);
                return $paymentTicket;
            }
            
            Log::warning('MercadoPagoQR: No encontrado por JSON contains', [
                'webhook_id' => $webhookId,
                'external_reference' => $externalReference,
            ]);
            
            // Método 2: Búsqueda directa en reference_number (si se usara)
            $paymentTicket = PaymentProviderTicket::where('reference_number', $externalReference)->first();
            if ($paymentTicket) {
                Log::info('MercadoPagoQR: ✓ Encontrado por reference_number', [
                    'webhook_id' => $webhookId,
                    'payment_ticket_id' => $paymentTicket->id,
                    'method' => 'reference_number',
                ]);
                return $paymentTicket;
            }
            
            Log::warning('MercadoPagoQR: No encontrado por reference_number', [
                'webhook_id' => $webhookId,
                'external_reference' => $externalReference,
            ]);

            // Método 3: Fallback por order_number extraído de external_reference
            $orderNumber = $this->extractOrderNumberFromExternalReference($externalReference);
            if (!empty($orderNumber)) {
                $paymentTicket = PaymentProviderTicket::whereHas('order', function ($query) use ($orderNumber) {
                        $query->where('order_number', $orderNumber);
                    })
                    ->where('payment_provider_id', $this->provider->id)
                    ->latest('id')
                    ->first();

                if ($paymentTicket) {
                    Log::info('MercadoPagoQR: ✓ Encontrado por order_number derivado de external_reference', [
                        'webhook_id' => $webhookId,
                        'payment_ticket_id' => $paymentTicket->id,
                        'order_number' => $orderNumber,
                        'method' => 'order_number_fallback',
                    ]);
                    return $paymentTicket;
                }

                Log::warning('MercadoPagoQR: No encontrado por order_number fallback', [
                    'webhook_id' => $webhookId,
                    'order_number' => $orderNumber,
                    'external_reference' => $externalReference,
                ]);
            }
        }
        
        // Log final - ningún método funcionó
        Log::error('MercadoPagoQR: PaymentProviderTicket NO ENCONTRADO tras todos los intentos - ERROR CRÍTICO', [
            'webhook_id' => $webhookId,
            'external_id' => $externalId,
            'external_reference' => $externalReference,
            'all_payment_tickets_count' => PaymentProviderTicket::count(),
            'all_payment_tickets_sample' => PaymentProviderTicket::limit(5)->get(['id', 'transaction_id', 'status', 'payment_provider_id'])->toArray(),
        ]);
        
        return null;
    }

    /**
     * Extrae order_number desde external_reference tipo:
     * CINEA-ORDER-{order_number}-ATT-{payment_ticket_id}
     */
    private function extractOrderNumberFromExternalReference(?string $externalReference): ?string
    {
        if (empty($externalReference)) {
            return null;
        }

        if (preg_match('/^CINEA-ORDER-(.+)-ATT-\d+$/', $externalReference, $matches)) {
            return $matches[1] ?? null;
        }

        return null;
    }

    /**
     * Obtener estado del pago desde el webhook o consultando MP API
     */
    private function getPaymentStatusFromWebhook(array $data, string $externalId, ?string $webhookId = null): ?string
    {
        // Intentar múltiples rutas para obtener status
        $possibleStatuses = [
            $data['data']['status'] ?? null,
            $data['status'] ?? null,
            $data['resource']['status'] ?? null,
        ];
        
        foreach ($possibleStatuses as $status) {
            if (!empty($status)) {
                Log::info('MercadoPagoQR: Status encontrado en webhook data', [
                    'webhook_id' => $webhookId,
                    'status' => $status,
                    'source' => 'webhook_data',
                ]);
                return $status;
            }
        }
        
        Log::warning('MercadoPagoQR: Status no encontrado en webhook data, consultando MP API', [
            'webhook_id' => $webhookId,
            'external_id' => $externalId,
            'data_keys' => array_keys($data),
        ]);

        return $this->queryMercadoPagoStatus($externalId, $data, $webhookId);
    }

    /**
     * Consultar estado a Mercado Pago API si no viene en el webhook
     * Detecta si es order o payment
     */
    private function queryMercadoPagoStatus(string $externalId, array $data, ?string $webhookId = null): ?string
    {
        try {
            Log::info('MercadoPagoQR: Preparando consulta a MP API', [
                'webhook_id' => $webhookId,
                'external_id' => $externalId,
            ]);
            
            $resourceType = $this->detectResourceType($data);
            if (empty($resourceType)) {
                Log::error('MercadoPagoQR: No se pudo determinar tipo de recurso en webhook', [
                    'webhook_id' => $webhookId,
                    'data_keys' => array_keys($data),
                    'data' => json_encode($data),
                ]);
                return null;
            }
            
            Log::info('MercadoPagoQR: Tipo de recurso detectado', [
                'webhook_id' => $webhookId,
                'resource_type' => $resourceType,
                'external_id' => $externalId,
            ]);

            $accessToken = $this->provider->getConfig('access_token');
            if (empty($accessToken)) {
                Log::error('MercadoPagoQR: Access token no configurado para consultar API', [
                    'webhook_id' => $webhookId,
                ]);
                return null;
            }

            $endpoint = "https://api.mercadopago.com/{$resourceType}/{$externalId}";
            
            Log::info('MercadoPagoQR: Consultando MP API', [
                'webhook_id' => $webhookId,
                'endpoint' => $endpoint,
            ]);
            
            $response = Http::withToken($accessToken)->get($endpoint);
            
            if (!$response->successful()) {
                Log::error('MercadoPagoQR: Error consultando MP API - respuesta no exitosa', [
                    'webhook_id' => $webhookId,
                    'status' => $response->status(),
                    'endpoint' => $endpoint,
                    'response_body' => $response->body(),
                ]);
                return null;
            }

            $apiData = $response->json();
            
            Log::info('MercadoPagoQR: Respuesta de MP API recibida', [
                'webhook_id' => $webhookId,
                'resource_type' => $resourceType,
                'api_response_keys' => array_keys($apiData),
            ]);
            
            if ($resourceType === 'payments') {
                $status = $apiData['status'] ?? null;
                Log::info('MercadoPagoQR: Status de pago obtenido del API', [
                    'webhook_id' => $webhookId,
                    'status' => $status,
                ]);
                return $status;
            } elseif ($resourceType === 'orders') {
                $status = $apiData['status'] ?? null;
                Log::info('MercadoPagoQR: Status de orden obtenido del API', [
                    'webhook_id' => $webhookId,
                    'status' => $status,
                ]);
                return $status;
            }

            Log::warning('MercadoPagoQR: Tipo de recurso no tiene handler para extraer status', [
                'webhook_id' => $webhookId,
                'resource_type' => $resourceType,
            ]);
            
            return null;

        } catch (\Exception $e) {
            Log::error('MercadoPagoQR: Excepción al consultar MP API', [
                'webhook_id' => $webhookId,
                'error' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
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
        
        Log::debug('MercadoPagoQR: Detectando tipo de recurso', [
            'type_value' => $type,
            'data_keys' => array_keys($data),
        ]);
        
        if ($type === 'payment' || $type === 'payment.created' || $type === 'payment.updated') {
            Log::debug('MercadoPagoQR: Tipo detectado: PAYMENT');
            return 'payments';
        }
        if ($type === 'order' || $type === 'order.created' || $type === 'order.updated') {
            Log::debug('MercadoPagoQR: Tipo detectado: ORDER');
            return 'orders';
        }
        if ($type === 'merchant_order') {
            Log::debug('MercadoPagoQR: Tipo detectado: MERCHANT_ORDER');
            return 'merchant_orders';
        }

        Log::warning('MercadoPagoQR: No se pudo detectar tipo de recurso', [
            'type_value' => $type,
            'all_data_keys' => array_keys($data),
        ]);

        return null;
    }

    /**
     * Mapear estados de Mercado Pago a nuestros estados
     */
    private function mapPaymentStatus(string $status = null): string
    {
        Log::debug('MercadoPagoQR: Mapeando status', [
            'original_status' => $status,
        ]);
        
        if (in_array($status, ['approved', 'processed'], true)) {
            Log::debug('MercadoPagoQR: Status mapeado a APPROVED');
            return 'approved';
        }
        if (in_array($status, ['pending', 'created', 'at_terminal'], true)) {
            Log::debug('MercadoPagoQR: Status mapeado a PENDING');
            return 'pending';
        }
        if (in_array($status, ['rejected', 'declined', 'cancelled', 'refunded'])) {
            Log::debug('MercadoPagoQR: Status mapeado a DECLINED', [
                'original_status' => $status,
            ]);
            return 'declined';
        }

        Log::warning('MercadoPagoQR: Status no reconocido, asignando PENDING por defecto', [
            'status_received' => $status,
        ]);
        
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
