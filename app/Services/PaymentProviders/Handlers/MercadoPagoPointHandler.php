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

    /**
     * Procesar pago por Terminal Smart Mercado Pago Punto
     */
    public function processPayment(PaymentProviderTicket $paymentTicket, array $additionalData = []): array
    {
        try {
            $this->validateConfiguration();

            // Obtener configuración
            $accessToken = $this->provider->getConfig('access_token');
            $terminalId = $this->provider->getConfig('terminal_id');

            $ticket = $paymentTicket->ticket()->with(['screening.movie'])->first();
            if (!$ticket) {
                throw new \Exception('Ticket no encontrado');
            }

            $price = floatval($additionalData['total_price'] ?? $ticket->price);
            $seatCount = intval($additionalData['seat_count'] ?? 1);
            $externalReference = $paymentTicket->generateExternalReference('POINT');
            $amountFormatted = number_format($price, 2, '.', '');

            // ===== PRE-CHECK: Auto-cancel global por terminal si está habilitado =====
            if (self::ENABLE_AUTO_CANCEL_QUEUED || self::ENABLE_AUTO_CANCEL_ON_ANY_METHOD) {
                $guardResult = $this->getTerminalService()->guardTerminalBeforePayment($terminalId);

                if (!$guardResult['can_proceed']) {
                    Log::warning('MercadoPagoPoint: Guard bloqueó la creación de orden', [
                        'reason' => $guardResult['cancel_error'],
                        'terminal_id' => $terminalId,
                    ]);

                    return [
                        'success' => false,
                        'error_code' => 'terminal_blocked',
                        'retryable' => true,
                        'error' => $guardResult['cancel_error'],
                    ];
                }
            }
            // =========================================================================

            // Enviar orden a la API de Mercado Pago
            $result = $this->sendToTerminal(
                $paymentTicket,
                $ticket->screening->movie->title,
                $price,
                $seatCount,
                $accessToken,
                $terminalId,
                $externalReference
            );

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error_code' => $result['error_code'] ?? 'unknown',
                    'retryable' => $result['retryable'] ?? false,
                    'error' => $result['error'] ?? 'Unknown error',
                ];
            }

            $orderId = $result['order_id'];

            $responseData = [
                'terminal_id' => $terminalId,
                'external_reference' => $externalReference,
                'order_id' => $orderId,
                'amount' => $amountFormatted,
                'payload' => [
                    'type' => 'point',
                    'external_reference' => $externalReference,
                    'description' => "Entradas - {$ticket->screening->movie->title} ({$seatCount} un.)",
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
                'error' => $e->getMessage(),
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
        string $externalReference
    ): array {
        $amountFormatted = number_format($amount, 2, '.', '');

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

        $idempotencyKey = $this->generateIdempotencyKey($paymentTicket->id);

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
                ];
            }
            return [
                'success' => false,
                'error' => 'No se recibió order_id en la respuesta de Mercado Pago',
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

            if ($errorCode === 'already_queued_order_on_terminal') {
                Log::warning('MercadoPagoPoint: 409 en sendToTerminal (race condition?)', [
                    'external_reference' => $externalReference,
                    'terminal_id' => $terminalId,
                ]);

                if (!self::ENABLE_AUTO_CANCEL_QUEUED) {
                    return [
                        'success' => false,
                        'error_code' => 'terminal_busy',
                        'retryable' => true,
                        'error' => 'La terminal tiene una orden en cola. Cancelala desde el Smart e intentá nuevamente.',
                    ];
                }

                // Usar el servicio para auto-cancel en este punto también
                $guardResult = $this->getTerminalService()->guardTerminalBeforePayment($terminalId);

                if (!$guardResult['can_proceed']) {
                    return [
                        'success' => false,
                        'error_code' => 'auto_cancel_failed',
                        'retryable' => true,
                        'error' => $guardResult['cancel_error'] ?? 'No se pudo cancelar la orden anterior',
                    ];
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
                        'error' => 'No se pudo crear orden incluso tras cancelar la anterior.',
                    ];
                }
            }
        }

        Log::error('MercadoPagoPoint: API Error', [
            'status' => $statusCode,
            'body' => $errorBody,
            'external_reference' => $externalReference,
        ]);

        return [
            'success' => false,
            'error' => "API de Mercado Pago: {$errorBody}",
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
     * Procesar webhook de confirmación de pago en terminal
     */
    public function handleWebhook(Request $request): bool
    {
        try {
            $data = $request->all();

            // El webhook debe contener el order_id en transaction_id
            $orderId = $data['id'] ?? $data['data']['id'] ?? null;
            $status = $data['status'] ?? $data['data']['status'] ?? null;

            if (!$orderId || !$status) {
                Log::warning('MercadoPagoPoint: Webhook incompleto', [
                    'has_order_id' => !empty($orderId),
                    'has_status' => !empty($status),
                ]);
                return false;
            }

            // Buscar PaymentProviderTicket por transaction_id o ID
            $paymentTicket = PaymentProviderTicket::findByTransactionOrId($orderId);

            if (!$paymentTicket) {
                Log::warning('MercadoPagoPoint: PaymentProviderTicket no encontrado', [
                    'order_id' => $orderId,
                ]);
                return false;
            }

            // Mapear estado según API de Mercado Pago
            $mappedStatus = match($status) {
                'approved' => 'approved',
                'pending' => 'pending',
                'payment_failure', 'declined', 'cancelled' => 'declined',
                default => 'pending',
            };

            // Actualizar ticket
            if ($mappedStatus === 'approved') {
                $paymentTicket->approve(array_merge($data, ['webhook_received_at' => now()->toIso8601String()]));
                Log::info('MercadoPagoPoint: Pago aprobado', ['payment_ticket_id' => $paymentTicket->id]);
            } else {
                $paymentTicket->update([
                    'status' => $mappedStatus,
                    'response_data' => array_merge(
                        $paymentTicket->response_data ?? [],
                        $data,
                        ['webhook_received_at' => now()->toIso8601String()]
                    ),
                ]);
                Log::info('MercadoPagoPoint: Estado actualizado', [
                    'payment_ticket_id' => $paymentTicket->id,
                    'status' => $mappedStatus,
                ]);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('MercadoPagoPoint: Error en webhook', ['error' => $e->getMessage()]);
            return false;
        }
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
