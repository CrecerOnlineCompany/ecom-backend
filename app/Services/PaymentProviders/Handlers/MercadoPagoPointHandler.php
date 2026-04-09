<?php

namespace App\Services\PaymentProviders\Handlers;

use App\Services\PaymentProviders\PaymentProviderHandler;
use App\Services\PaymentProviders\MercadoPagoTerminalService;
use App\Models\PaymentProviderTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MercadoPagoPointHandler extends PaymentProviderHandler
{
    const MP_API_BASE = 'https://api.mercadopago.com/v1/orders';
    private const ENABLE_AUTO_CANCEL_QUEUED = true;
    private const AUTO_CANCEL_LOOKBACK_MINUTES = 120;
    private const AUTO_CANCEL_RETRY_ONCE = true;
    private const ENABLE_AUTO_CANCEL_ON_ANY_METHOD = true;

    private MercadoPagoTerminalService $terminalService;

    private function getTerminalService(): MercadoPagoTerminalService
    {
        if (!isset($this->terminalService)) {
            $this->terminalService = new MercadoPagoTerminalService($this->provider);
        }
        return $this->terminalService;
    }

    private function buildTerminalBusyResponse(?int $retryAfterSeconds = null, ?string $legacyErrorCode = null): array
    {
        $message = 'La terminal tiene otra orden en curso. Cancelala manualmente desde la terminal o elegí pagar con QR.';

        if ($retryAfterSeconds) {
            $minutes = max(1, (int) ceil($retryAfterSeconds / 60));
            $message .= " Si no podés cancelarla, probá nuevamente en {$minutes} minuto(s).";
        }

        return [
            'success' => false,
            'error_code' => 'terminal_busy_manual_cancel_or_qr',
            'legacy_error_code' => $legacyErrorCode,
            'retryable' => true,
            'message' => $message,
            'retry_after_seconds' => $retryAfterSeconds,
        ];
    }

    /**
     * ORDER-FIRST: Procesar pago por Terminal Smart Mercado Pago Punto
     * Espera Order creada con asientos reservados en inventory
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

            $this->validateConfiguration();

            // Obtener configuración
            $accessToken = $this->provider->getConfig('access_token');
            $terminalId = $this->provider->getConfig('terminal_id');

            $price = floatval($additionalData['total_price'] ?? $order->total_amount);
            $seatCount = intval($additionalData['seat_count'] ?? count($additionalData['seat_ids'] ?? []));
            $externalReference = $paymentTicket->generateExternalReference('POINT');
            $amountFormatted = number_format($price, 2, '.', '');
            $idempotencyKey = $additionalData['idempotency_key'] ?? $paymentTicket->response_data['idempotency_key'] ?? null;

            Log::info('MercadoPagoPoint (order-first): Iniciando procesamiento', [
                'payment_ticket_id' => $paymentTicket->id,
                'order_id' => $order->id,
                'terminal_id' => $terminalId,
                'idempotency_key' => $idempotencyKey,
            ]);

            // ===== PRE-CHECK: Auto-cancel global por terminal si está habilitado =====
            if (self::ENABLE_AUTO_CANCEL_QUEUED || self::ENABLE_AUTO_CANCEL_ON_ANY_METHOD) {
                $guardResult = $this->getTerminalService()->guardTerminalBeforePayment($terminalId);

                if (!$guardResult['can_proceed']) {
                    Log::warning('MercadoPagoPoint: Guard bloqueó la creación de orden', [
                        'reason' => $guardResult['cancel_error'],
                        'terminal_id' => $terminalId,
                        'retry_after' => $guardResult['retry_after_seconds'] ?? null,
                    ]);

                    return $this->buildTerminalBusyResponse(
                        $guardResult['retry_after_seconds'] ?? null,
                        'terminal_blocked'
                    );
                }
            }
            // =========================================================================

            // Enviar orden a la API de Mercado Pago
            $result = $this->sendToTerminal(
                $paymentTicket,
                $screening->movie->title,
                $price,
                $seatCount,
                $accessToken,
                $terminalId,
                $externalReference,
                $idempotencyKey
            );

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error_code' => $result['error_code'] ?? 'unknown',
                    'retryable' => $result['retryable'] ?? false,
                    'message' => $result['message'] ?? $result['error'] ?? 'Unknown error',
                ];
            }

            $orderId = $result['order_id'];
            $usedIdempotencyKey = $result['idempotency_key'] ?? $idempotencyKey;

            $responseData = [
                'terminal_id' => $terminalId,
                'external_reference' => $externalReference,
                'order_id' => $orderId,
                'amount' => $amountFormatted,
                'idempotency_key' => $usedIdempotencyKey, // Mantener el key efectivamente usado
                'payload' => [
                    'type' => 'point',
                    'external_reference' => $externalReference,
                    'description' => "Entradas - {$screening->movie->title} ({$seatCount} un.)",
                    'expiration_time' => 'PT10M',
                    'transactions' => [
                        'payments' => [
                            [
                                'amount' => $amountFormatted,
                            ]
                        ]
                    ],
                    'config' => [
                        'point' => [
                            'terminal_id' => $terminalId,
                        ]
                    ]
                ],
                'sent_to_terminal_at' => now()->toIso8601String(),
            ];

            // Si hay metadata de autocancel, guardarla
            if (isset($result['metadata'])) {
                $responseData = array_merge($responseData, $result['metadata']);
                if (isset($result['metadata']['canceled_order_id'])) {
                    $responseData['canceled_order_id'] = $result['metadata']['canceled_order_id'];
                    $responseData['canceled_at'] = now()->toIso8601String();
                }
            }

            // Actualizar PaymentProviderTicket
            $paymentTicket->update([
                'status' => 'processing',
                'transaction_id' => $orderId,
                'response_data' => $responseData,
            ]);

            // Track en mp_terminal_orders para detección global futura
            $this->getTerminalService()->trackNewOrder(
                $terminalId,
                $orderId,
                $externalReference,
                $paymentTicket->id,
                $responseData
            );

            Log::info('MercadoPagoPoint: Orden enviada a API', [
                'payment_ticket_id' => $paymentTicket->id,
                'order_id' => $orderId,
                'amount' => $price,
                'terminal_id' => $terminalId,
            ]);

            return [
                'success' => true,
                'method' => 'terminal',
                'order_id' => $orderId,
                'amount' => $price,
                'payment_ticket_id' => $paymentTicket->id,
                'message' => 'Orden creada. Por favor procesa el pago en la terminal.',
            ];

        } catch (\Exception $e) {
            Log::error('MercadoPagoPoint: Error al procesar pago', [
                'error' => $e->getMessage(),
                'payment_ticket_id' => $paymentTicket->id ?? null,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Enviar orden a la API real de Mercado Pago Point
     */
    private function sendToTerminal(
        PaymentProviderTicket $paymentTicket,
        string $movieTitle,
        float $amount,
        int $seatCount,
        string $accessToken,
        string $terminalId,
        string $externalReference,
        string $idempotencyKey = null
    ): array {
        $amountFormatted = number_format($amount, 2, '.', '');

        // Usar el idempotency_key que viene de additionalData (si no vino, usar el almacenado)
        if (!$idempotencyKey) {
            $idempotencyKey = $paymentTicket->response_data['idempotency_key'] ?? null;
        }
        
        // Si aún no tenemos key, generar uno nuevo (nunca debería llegar aquí)
        if (!$idempotencyKey) {
            $idempotencyKey = $this->generateIdempotencyKey($paymentTicket->id);
            
            // Guardar el idempotency_key inmediatamente
            $currentResponseData = $paymentTicket->response_data ?? [];
            $currentResponseData['idempotency_key'] = $idempotencyKey;
            $paymentTicket->update(['response_data' => $currentResponseData]);
        }

        $payload = [
            'type' => 'point',
            'external_reference' => $externalReference,
            'description' => "Entradas - {$movieTitle} ({$seatCount} un.)",
            'expiration_time' => 'PT10M',
            'transactions' => [
                'payments' => [
                    [
                        'amount' => $amountFormatted,
                    ]
                ]
            ],
            'config' => [
                'point' => [
                    'terminal_id' => $terminalId,
                ]
            ]
        ];

        Log::debug('MercadoPagoPoint: Enviando a terminal con headers', [
            'payment_ticket_id' => $paymentTicket->id,
            'idempotency_key' => $idempotencyKey,
            'terminal_id' => $terminalId,
        ]);
        Log::info('MercadoPagoPoint: Payload enviado a MP (terminal)', [
            'payment_ticket_id' => $paymentTicket->id,
            'terminal_id' => $terminalId,
            'payload' => $payload,
        ]);

        $response = Http::withToken($accessToken)
            ->withHeaders([
                'X-Idempotency-Key' => $idempotencyKey,
                'Content-Type' => 'application/json',
            ])
            ->post(self::MP_API_BASE, $payload);

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['id'])) {
                return [
                    'success' => true,
                    'order_id' => $data['id'],
                    'idempotency_key' => $idempotencyKey,
                ];
            }
            return [
                'success' => false,
                'message' => 'No se recibió order_id en la respuesta de Mercado Pago',
            ];
        }

        $statusCode = $response->status();
        $errorBody = $response->body();

        // Manejo de error 409: ya existe orden queued en esta terminal
        // Nota: En condiciones normales esto no debería ocurrir porque ya pasamos guardTerminalBeforePayment()
        // Si ocurre, es por race condition o timeout. Intentamos cancelar nuevamente.
        if ($statusCode === 409) {
            $errorData = $response->json();
            $errorCode = $errorData['errors'][0]['code'] ?? null;

            // La key ya fue usada por MP. Reintentamos una vez con una nueva para evitar bloqueo del flujo.
            if ($errorCode === 'idempotency_key_already_used' && self::AUTO_CANCEL_RETRY_ONCE) {
                $newIdempotencyKey = $this->generateIdempotencyKey($paymentTicket->id, true);

                Log::warning('MercadoPagoPoint: 409 idempotency_key_already_used, reintentando con nueva key', [
                    'external_reference' => $externalReference,
                    'terminal_id' => $terminalId,
                    'previous_idempotency_key' => $idempotencyKey,
                    'new_idempotency_key' => $newIdempotencyKey,
                ]);

                $retryResponse = Http::withToken($accessToken)
                    ->withHeaders([
                        'X-Idempotency-Key' => $newIdempotencyKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post(self::MP_API_BASE, $payload);

                if ($retryResponse->successful()) {
                    $retryData = $retryResponse->json();
                    if (isset($retryData['id'])) {
                        Log::info('MercadoPagoPoint: Reintento exitoso tras idempotency_key_already_used', [
                            'new_order_id' => $retryData['id'],
                            'external_reference' => $externalReference,
                        ]);

                        return [
                            'success' => true,
                            'order_id' => $retryData['id'],
                            'idempotency_key' => $newIdempotencyKey,
                            'metadata' => [
                                'idempotency_key_rotated' => true,
                                'previous_idempotency_key' => $idempotencyKey,
                            ],
                        ];
                    }
                }

                Log::error('MercadoPagoPoint: Reintento falló tras idempotency_key_already_used', [
                    'retry_status' => $retryResponse->status(),
                    'retry_error' => $retryResponse->body(),
                    'external_reference' => $externalReference,
                ]);

                return [
                    'success' => false,
                    'error_code' => 'idempotency_key_retry_failed',
                    'retryable' => true,
                    'message' => 'No se pudo crear orden tras regenerar la idempotency key.',
                ];
            }

            if ($errorCode === 'already_queued_order_on_terminal') {
                Log::warning('MercadoPagoPoint: 409 en sendToTerminal (race condition?)', [
                    'external_reference' => $externalReference,
                    'terminal_id' => $terminalId,
                ]);

                if (!self::ENABLE_AUTO_CANCEL_QUEUED) {
                    return $this->buildTerminalBusyResponse(null, 'terminal_busy');
                }

                // Usar el servicio para auto-cancel en este punto también
                $guardResult = $this->getTerminalService()->guardTerminalBeforePayment($terminalId);

                if (!$guardResult['can_proceed']) {
                    return $this->buildTerminalBusyResponse(
                        $guardResult['retry_after_seconds'] ?? null,
                        'auto_cancel_failed'
                    );
                }

                // Reintentar con nueva X-Idempotency-Key
                if (self::AUTO_CANCEL_RETRY_ONCE) {
                    $newIdempotencyKey = $this->generateIdempotencyKey($paymentTicket->id, true);

                    Log::info('MercadoPagoPoint: Reintentando tras 409 en sendToTerminal', [
                        'external_reference' => $externalReference,
                        'new_idempotency_key' => $newIdempotencyKey,
                    ]);

                    $retryResponse = Http::withToken($accessToken)
                        ->withHeaders([
                            'X-Idempotency-Key' => $newIdempotencyKey,
                            'Content-Type' => 'application/json',
                        ])
                        ->post(self::MP_API_BASE, $payload);

                    if ($retryResponse->successful()) {
                        $retryData = $retryResponse->json();
                        if (isset($retryData['id'])) {
                            Log::info('MercadoPagoPoint: Reintento exitoso tras 409', [
                                'new_order_id' => $retryData['id'],
                                'external_reference' => $externalReference,
                                'canceled_order_id' => $guardResult['cancelled_order_id'],
                            ]);
                            return [
                                'success' => true,
                                'order_id' => $retryData['id'],
                                'idempotency_key' => $newIdempotencyKey,
                                'metadata' => [
                                    'canceled_order_id' => $guardResult['cancelled_order_id'],
                                    'auto_canceled' => true,
                                ],
                            ];
                        }
                    }

                    Log::error('MercadoPagoPoint: Reintento falló tras 409', [
                        'retry_status' => $retryResponse->status(),
                        'retry_error' => $retryResponse->body(),
                        'external_reference' => $externalReference,
                    ]);
                    return [
                        'success' => false,
                        'error_code' => 'retry_failed',
                        'retryable' => true,
                        'message' => 'No se pudo crear orden incluso tras cancelar la anterior.',
                    ];
                }
            }
        }

        Log::error('MercadoPagoPoint: API Error', [
            'status' => $statusCode,
            'body' => $errorBody,
            'external_reference' => $externalReference,
            'terminal_id' => $terminalId,
            'payload' => $payload,
        ]);

        return [
            'success' => false,
            'message' => "API de Mercado Pago: {$errorBody}",
        ];
    }

    /**
     * Generar X-Idempotency-Key único para cada transacción
     */
    private function generateIdempotencyKey(int $paymentTicketId, bool $isRetry = false): string
    {
        $suffix = $isRetry ? '-retry' : '';
        return "CINEA-{$paymentTicketId}{$suffix}-" . Str::random(16);
    }

    /**
     * Procesar webhook de confirmación de pago en terminal Smart
     * 
     * Soporta notificaciones de pagos con Mercado Pago Smart Point
     * Mapea status específico a estados estándar del sistema
     * 
     * @return bool Indica si webhook fue procesado sin errores técnicos
     */
    public function handleWebhook(Request $request): bool
    {
        try {
            $data = $request->all();

            Log::info('MercadoPagoPoint: Webhook recibido', [
                'data_keys' => array_keys($data),
            ]);

            // Step 1: Extraer IDs y status
            // El webhook debe contener el order_id en transaction_id
            $orderId = $data['id'] ?? $data['data']['id'] ?? null;
            $status = $data['status'] ?? $data['data']['status'] ?? null;
            $externalReference = $data['external_reference']
                ?? $data['data']['external_reference']
                ?? $data['order']['external_reference']
                ?? null;

            if (!$orderId || !$status) {
                Log::warning('MercadoPagoPoint: Webhook incompleto', [
                    'has_order_id' => !empty($orderId),
                    'has_status' => !empty($status),
                    'data_keys' => array_keys($data),
                ]);
                return true; // Procesar pero sin hacer nada
            }

            Log::info('MercadoPagoPoint: Datos extraídos', [
                'order_id' => $orderId,
                'status' => $status,
            ]);

            // Step 2: Buscar PaymentProviderTicket
            $paymentTicket = PaymentProviderTicket::findByTransactionOrId($orderId);

            if (!$paymentTicket && !empty($externalReference)) {
                // Método 1: JSON contains en response_data
                $paymentTicket = PaymentProviderTicket::whereJsonContains('response_data->external_reference', $externalReference)->first();
            }

            if (!$paymentTicket && !empty($externalReference)) {
                // Método 2: reference_number
                $paymentTicket = PaymentProviderTicket::where('reference_number', $externalReference)->first();
            }

            if (!$paymentTicket && !empty($externalReference)) {
                // Método 3: fallback por order_number en external_reference
                $orderNumber = $this->extractOrderNumberFromExternalReference((string) $externalReference);
                if (!empty($orderNumber)) {
                    $paymentTicket = PaymentProviderTicket::whereHas('order', function ($query) use ($orderNumber) {
                            $query->where('order_number', $orderNumber);
                        })
                        ->where('payment_provider_id', $this->provider->id)
                        ->latest('id')
                        ->first();
                }
            }

            if (!$paymentTicket) {
                Log::warning('MercadoPagoPoint: PaymentProviderTicket no encontrado', [
                    'order_id' => $orderId,
                    'status' => $status,
                    'external_reference' => $externalReference,
                ]);
                return true; // Procesar pero sin hacer nada
            }

            Log::info('MercadoPagoPoint: PaymentProviderTicket encontrado', [
                'payment_ticket_id' => $paymentTicket->id,
                'current_status' => $paymentTicket->status,
                'order_id' => $paymentTicket->order_id,
            ]);

            // Step 3: Mapear status de API Terminal
            $mappedStatus = match($status) {
                'approved' => 'approved',
                'pending' => 'processing',
                'payment_failure', 'declined', 'cancelled' => 'failed',
                default => 'processing',
            };

            Log::info('MercadoPagoPoint: Status mapeado', [
                'original_status' => $status,
                'mapped_status' => $mappedStatus,
            ]);

            // Step 4: Actualizar según status
            if ($mappedStatus === 'approved') {
                try {
                    $paymentTicket->approve(array_merge($data, [
                        'webhook_received_at' => now()->toIso8601String(),
                        'terminal_approved_at' => now()->toIso8601String(),
                    ]));

                    Log::info('MercadoPagoPoint: Pago terminal aprobado y orden finalizada', [
                        'payment_ticket_id' => $paymentTicket->id,
                        'order_id' => $orderId,
                    ]);
                } catch (\Exception $e) {
                    // approve() lanzó excepción = finalización falló
                    Log::error('MercadoPagoPoint: Error al aprobar pago terminal', [
                        'payment_ticket_id' => $paymentTicket->id,
                        'error' => $e->getMessage(),
                        'order_id' => $orderId,
                    ]);
                    return false; // Reintentar
                }
            } else {
                // No aprobado: actualizar status sin llamar approve()
                try {
                    $paymentTicket->update([
                        'status' => $mappedStatus,
                        'response_data' => array_merge(
                            $paymentTicket->response_data ?? [],
                            $data,
                            [
                                'webhook_received_at' => now()->toIso8601String(),
                                'terminal_status' => $status,
                                'external_reference' => $externalReference,
                            ]
                        ),
                    ]);

                    Log::info('MercadoPagoPoint: Status terminal actualizado', [
                        'payment_ticket_id' => $paymentTicket->id,
                        'new_status' => $mappedStatus,
                        'original_status' => $status,
                    ]);
                } catch (\Exception $e) {
                    Log::error('MercadoPagoPoint: Error al actualizar pago terminal', [
                        'payment_ticket_id' => $paymentTicket->id,
                        'error' => $e->getMessage(),
                    ]);
                    return false;
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('MercadoPagoPoint: Error inesperado procesando webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Extrae order_number desde external_reference tipo:
     * CINEA-ORDER-{order_number}-ATT-{payment_ticket_id}
     */
    private function extractOrderNumberFromExternalReference(string $externalReference): ?string
    {
        if (preg_match('/^CINEA-ORDER-(.+)-ATT-\d+$/', $externalReference, $matches)) {
            return $matches[1] ?? null;
        }

        return null;
    }

    /**
     * Validar configuración del proveedor
     */
    public function validateConfiguration(): bool
    {
        $accessToken = $this->provider->getConfig('access_token');
        $terminalId = $this->provider->getConfig('terminal_id');

        if (empty($accessToken)) {
            throw new \Exception('Access token de Mercado Pago no configurado');
        }

        if (empty($terminalId)) {
            throw new \Exception('Terminal ID (terminal_id) no configurado');
        }

        return true;
    }

    /**
     * Reembolsar pago
     */
    public function refund(PaymentProviderTicket $paymentTicket, string $reason = ''): bool
    {
        try {
            $paymentTicket->update([
                'status' => 'refunded',
                'response_data' => array_merge(
                    $paymentTicket->response_data ?? [],
                    [
                        'refund_reason' => $reason,
                        'refunded_at' => now()->toIso8601String(),
                    ]
                ),
            ]);

            Log::info('MercadoPagoPoint: Pago reembolsado', ['payment_ticket_id' => $paymentTicket->id]);
            return true;

        } catch (\Exception $e) {
            Log::error('MercadoPagoPoint: Error al reembolsar', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Obtener estado actual del pago
     */
    public function getPaymentStatus(PaymentProviderTicket $paymentTicket): string
    {
        try {
            if (!$paymentTicket->transaction_id) {
                return $paymentTicket->status ?? 'pending';
            }

            // Consultar estado en el servicio (que puede chequear API + BD)
            $result = $this->getTerminalService()->getOrderStatus($paymentTicket->transaction_id);
            return $result['status'] ?? $paymentTicket->status ?? 'pending';

        } catch (\Exception $e) {
            Log::error('MercadoPagoPoint: Error obteniendo status', [
                'error' => $e->getMessage(),
            ]);
            return $paymentTicket->status ?? 'pending';
        }
    }

    /**
     * Obtener lista de terminales disponibles
     */
    public function getAvailableTerminals(): array
    {
        try {
            // En una implementación real:
            // GET /devices?store_id=...

            // Por ahora, retornar las configuradas
            $terminalId = $this->provider->getConfig('terminal_id');

            if (empty($terminalId)) {
                return [];
            }

            return [
                [
                    'id' => $terminalId,
                    'name' => 'Terminal ' . substr($terminalId, -4),
                    'status' => 'available',
                ]
            ];

        } catch (\Exception $e) {
            Log::error('MercadoPagoPoint: Error obteniendo terminales', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
