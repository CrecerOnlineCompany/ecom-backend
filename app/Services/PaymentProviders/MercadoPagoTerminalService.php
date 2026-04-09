<?php

namespace App\Services\PaymentProviders;

use App\Models\PaymentProvider;
use App\Models\MpTerminalOrder;
use App\Models\PaymentProviderTicket;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * MercadoPagoTerminalService
 * 
 * Servicio global para:
 * - Detectar órdenes activas en terminal (via API + BD)
 * - Cancelarlas automáticamente antes de crear nuevas
 * - Persistir tracking en mp_terminal_orders
 * - Soportar flujo de retries inteligentes
 * 
 * Usado por: MercadoPagoPointHandler (terminal y QR)
 */
class MercadoPagoTerminalService
{
    /**
     * Configuración (puede venir de config/services.php o env)
     */
    private const MP_API_BASE = 'https://api.mercadopago.com/v1/orders';
    private const ENABLE_AUTO_CANCEL_QUEUED = true;
    private const AUTO_CANCEL_LOOKBACK_MINUTES = 120;
    private const ENABLE_AUTO_CANCEL_ON_ANY_METHOD = true; // Cancela incluso en QR
    private const API_TIMEOUT = 10; // segundos

    private PaymentProvider $provider;

    public function __construct(PaymentProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Pre-check antes de crear CUALQUIER pago (terminal, QR, etc)
     * 
     * Busca órdenes activas en la terminal y las cancela si es necesario.
     * No es fatal si falla la cancelación - devuelve array con estado.
     * 
     * @return array {
     *   'has_active_order' => bool,
     *   'cancelled_order_id' => string|null,
     *   'cancel_success' => bool,
     *   'cancel_error' => string|null,
     *   'can_proceed' => bool
     * }
     */
    public function guardTerminalBeforePayment(string $terminalId): array
    {
        $result = [
            'has_active_order' => false,
            'cancelled_order_id' => null,
            'cancel_success' => false,
            'cancel_error' => null,
            'can_proceed' => true,
        ];

        if (!self::ENABLE_AUTO_CANCEL_QUEUED && !self::ENABLE_AUTO_CANCEL_ON_ANY_METHOD) {
            Log::debug('MercadoPagoTerminal: Auto-cancel deshabilitado, saltando guard', [
                'terminal_id' => $terminalId,
            ]);
            return $result;
        }

        try {
            // 1. Buscar orden activa: primero en BD, luego en API
            $activeOrder = $this->findActiveOrderByTerminal($terminalId);

            if (!$activeOrder) {
                Log::debug('MercadoPagoTerminal: No hay orden activa en el terminal', [
                    'terminal_id' => $terminalId,
                ]);
                return $result;
            }

            $result['has_active_order'] = true;
            $result['cancelled_order_id'] = $activeOrder['order_id'];

            Log::warning('MercadoPagoTerminal: Orden activa encontrada, iniciando auto-cancel', [
                'terminal_id' => $terminalId,
                'order_id' => $activeOrder['order_id'],
                'source' => $activeOrder['source'], // 'db' o 'api'
            ]);

            // 2. Intentar cancelarla
            $cancelResult = $this->cancelOrderFromApi($activeOrder['order_id'], $activeOrder['idempotency_key'] ?? null);

            if (!$cancelResult['success']) {
                // Verificar si el error es por estado no cancelable
                $errorCode = $cancelResult['error_code'] ?? null;
                
                if ($errorCode === 'cannot_cancel_order') {
                    $errorBody = $cancelResult['error'] ?? '';
                    $parsedStatus = null;
                    $apiMessage = null;

                    if (is_string($errorBody) && $errorBody !== '') {
                        $decoded = json_decode($errorBody, true);
                        if (is_array($decoded)) {
                            $apiMessage = $decoded['errors'][0]['message'] ?? null;
                            if (is_string($apiMessage) && preg_match("/status[^']*'([^']+)'/i", $apiMessage, $matches)) {
                                $parsedStatus = strtolower($matches[1] ?? '');
                            }
                        }
                    }

                    // Caso controlado: MP indica 'processed', no es cancelable pero tampoco bloqueante.
                    if ($parsedStatus === 'processed') {
                        $result['cancel_error'] = null;
                        $result['can_proceed'] = true;
                        $result['non_blocking_cancel_error'] = 'cannot_cancel_order_processed';
                        $result['non_blocking_order_status'] = $parsedStatus;

                        Log::warning('MercadoPagoTerminal: Orden no cancelable pero flujo continúa', [
                            'order_id' => $activeOrder['order_id'],
                            'error_code' => $errorCode,
                            'order_status' => $parsedStatus,
                            'api_message' => $apiMessage,
                        ]);

                        return $result;
                    }

                    // Caso controlado: MP indica 'expired', no es cancelable y no debe bloquear.
                    if ($parsedStatus === 'expired') {
                        $result['cancel_error'] = null;
                        $result['can_proceed'] = true;
                        $result['non_blocking_cancel_error'] = 'cannot_cancel_order_expired';
                        $result['non_blocking_order_status'] = $parsedStatus;

                        $mpOrder = MpTerminalOrder::where('order_id', $activeOrder['order_id'])->first();
                        if ($mpOrder) {
                            $mpOrder->markExpired();
                        }

                        Log::warning('MercadoPagoTerminal: Orden expirada, flujo continua y se marca en BD', [
                            'order_id' => $activeOrder['order_id'],
                            'error_code' => $errorCode,
                            'order_status' => $parsedStatus,
                            'api_message' => $apiMessage,
                        ]);

                        return $result;
                    }

                    // Para otros estados no cancelables, mantener bloqueo.
                    $result['cancel_error'] = $errorBody;
                    $result['can_proceed'] = false;
                    $result['retry_after_seconds'] = 600; // 10 minutos (tiempo de expiración aproximado)
                    
                    Log::warning('MercadoPagoTerminal: Orden en estado no cancelable (bloqueante)', [
                        'order_id' => $activeOrder['order_id'],
                        'order_status' => $parsedStatus,
                        'error' => $errorBody,
                        'error_code' => $errorCode,
                    ]);
                    
                    return $result;
                }
                
                // Para otros errores, retornar el error normal
                $result['cancel_error'] = $cancelResult['error'];
                $result['can_proceed'] = false;

                Log::error('MercadoPagoTerminal: Fallo cancelación de orden activa', [
                    'order_id' => $activeOrder['order_id'],
                    'error' => $cancelResult['error'],
                    'status_code' => $cancelResult['status_code'] ?? null,
                ]);

                return $result;
            }

            $result['cancel_success'] = true;

            // 3. Persistir el cancel en BD
            $this->markOrderCancelled(
                $activeOrder['order_id'],
                'user_changed_method'
            );

            Log::info('MercadoPagoTerminal: Orden activa cancelada exitosamente', [
                'terminal_id' => $terminalId,
                'order_id' => $activeOrder['order_id'],
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('MercadoPagoTerminal: Excepción en guardTerminalBeforePayment', [
                'error' => $e->getMessage(),
                'terminal_id' => $terminalId,
                'exception' => get_class($e),
            ]);

            $result['cancel_error'] = $e->getMessage();
            $result['can_proceed'] = false;

            return $result;
        }
    }

    /**
     * Encontrar orden activa en terminal
     * 
     * Busca en este orden:
     * 1. mp_terminal_orders (nuestro tracking)
     * 2. API de MP (consulta directa)
     * 3. payment_provider_tickets (fallback)
     */
    private function findActiveOrderByTerminal(string $terminalId): ?array
    {
        // 1. Buscar en nuestra tabla de tracking
        $mpTerminalOrder = MpTerminalOrder::getActiveOrderByTerminal(
            $this->provider->id,
            $terminalId
        );

        if ($mpTerminalOrder) {
            Log::debug('MercadoPagoTerminal: Orden activa encontrada en BD (mp_terminal_orders)', [
                'order_id' => $mpTerminalOrder->order_id,
                'status' => $mpTerminalOrder->status,
            ]);

            // Obtener idempotency_key del ticket si existe
            $idempotencyKey = null;
            if ($mpTerminalOrder->payment_provider_ticket_id) {
                $ticket = PaymentProviderTicket::find($mpTerminalOrder->payment_provider_ticket_id);
                $idempotencyKey = $ticket?->response_data['idempotency_key'] ?? null;
            }

            return [
                'order_id' => $mpTerminalOrder->order_id,
                'source' => 'db',
                'ticket_id' => $mpTerminalOrder->payment_provider_ticket_id,
                'idempotency_key' => $idempotencyKey,
            ];
        }

        // 2. Consultar API de MP si la BD no tiene nada reciente
        $apiOrder = $this->findActiveOrderFromApi($terminalId);

        if ($apiOrder) {
            Log::warning('MercadoPagoTerminal: Orden activa encontrada en API (no registrada en BD!)', [
                'order_id' => $apiOrder['id'],
                'terminal_id' => $terminalId,
            ]);

            // Registrar en nuestra BD para future tracking
            MpTerminalOrder::firstOrCreate(
                [
                    'payment_provider_id' => $this->provider->id,
                    'order_id' => $apiOrder['id'],
                ],
                [
                    'terminal_id' => $terminalId,
                    'status' => 'active',
                    'external_reference' => $apiOrder['external_reference'] ?? null,
                    'response_data' => $apiOrder,
                ]
            );

            return [
                'order_id' => $apiOrder['id'],
                'source' => 'api',
                'ticket_id' => null,
            ];
        }

        // 3. Fallback: buscar en payment_provider_tickets por terminal_id
        $ticket = PaymentProviderTicket::where('payment_provider_id', $this->provider->id)
            ->whereIn('status', ['queued', 'processing', 'pending'])
            ->where('response_data->terminal_id', '=', $terminalId)
            ->whereNotNull('transaction_id')
            ->where('created_at', '>=', now()->subMinutes(self::AUTO_CANCEL_LOOKBACK_MINUTES))
            ->orderByDesc('id')
            ->first();

        if ($ticket && $ticket->transaction_id) {
            Log::debug('MercadoPagoTerminal: Orden encontrada en payment_provider_tickets', [
                'order_id' => $ticket->transaction_id,
                'ticket_id' => $ticket->id,
            ]);

            // Registrar en mp_terminal_orders también
            MpTerminalOrder::firstOrCreate(
                [
                    'payment_provider_id' => $this->provider->id,
                    'order_id' => $ticket->transaction_id,
                ],
                [
                    'terminal_id' => $terminalId,
                    'payment_provider_ticket_id' => $ticket->id,
                    'status' => 'active',
                    'external_reference' => $ticket->response_data['external_reference'] ?? null,
                    'response_data' => $ticket->response_data,
                ]
            );

            return [
                'order_id' => $ticket->transaction_id,
                'source' => 'db',
                'ticket_id' => $ticket->id,
                'idempotency_key' => $ticket->response_data['idempotency_key'] ?? null,
            ];
        }

        Log::debug('MercadoPagoTerminal: No se encontró orden activa para terminal', [
            'terminal_id' => $terminalId,
        ]);

        return null;
    }

    /**
     * Buscar orden activa directamente en API de Mercado Pago
     * 
     * Nota: Las órdenes Point creadas via POST /v1/orders con type=point
     * pueden consultarse con GET /v1/orders/{id}, pero no hay endpoint
     * "listar órdenes por terminal" documentado públicamente.
     * 
     * Para una solución más robusta, podría necesitarse:
     * - Webhook que persista órdenes en tiempo real
     * - Custom endpoint en MP (si lo soportan)
     * - Tracking local exhaustivo (recomendado)
     */
    private function findActiveOrderFromApi(string $terminalId): ?array
    {
        try {
            $accessToken = $this->provider->getConfig('access_token');

            if (empty($accessToken)) {
                Log::warning('MercadoPagoTerminal: Access token no disponible para consulta API', [
                    'terminal_id' => $terminalId,
                ]);
                return null;
            }

            // Nota: Este endpoint es ilustrativo.
            // La API de MP no expone un "get by terminal", entonces es mejor
            // confiar en BD + webhook + polling.
            // Por ahora, retornamos null para fuerza el uso de BD.

            /* Descomentar si MP expone endpoint de búsqueda:
            $response = Http::withToken($accessToken)
                ->timeout(self::API_TIMEOUT)
                ->get(self::MP_API_BASE, [
                    'terminal_id' => $terminalId,
                    'status' => 'pending,processing,queued',
                    'sort' => '-created_at',
                ]);

            if ($response->successful()) {
                $orders = $response->json();
                if (!empty($orders[0])) {
                    return $orders[0];
                }
            }
            */

            return null;

        } catch (\Exception $e) {
            Log::error('MercadoPagoTerminal: Error consultando API por orden activa', [
                'error' => $e->getMessage(),
                'terminal_id' => $terminalId,
            ]);
            return null;
        }
    }

    /**
     * Cancelar orden en la API de Mercado Pago
     * IMPORTANTE: Usa un idempotency_key DIFERENTE para cancelación (no reutiliza el de creación)
     */
    private function cancelOrderFromApi(string $orderId, string $idempotencyKey = null): array
    {
        try {
            $accessToken = $this->provider->getConfig('access_token');

            if (empty($accessToken)) {
                return [
                    'success' => false,
                    'error' => 'Access token no configurado',
                ];
            }

            // CRITIAL: Para cancelación, SIEMPRE generar un nuevo key
            // El key de creación ya está usado en MP, no se puede reutilizar
            $cancelIdempotencyKey = \Illuminate\Support\Str::uuid()->toString();

            $url = self::MP_API_BASE . "/{$orderId}/cancel";

            Log::debug('MercadoPagoTerminal: Enviando POST cancel a API', [
                'order_id' => $orderId,
                'url' => $url,
                'cancel_idempotency_key' => $cancelIdempotencyKey,
                'original_idempotency_key' => $idempotencyKey,
            ]);

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Idempotency-Key' => $cancelIdempotencyKey,
                ])
                ->timeout(self::API_TIMEOUT)
                ->post($url);

            $statusCode = $response->status();

            if ($response->successful()) {
                Log::info('MercadoPagoTerminal: Cancelación exitosa en API', [
                    'order_id' => $orderId,
                    'status_code' => $statusCode,
                ]);

                return [
                    'success' => true,
                ];
            }

            // Parsear el error para extraer el código específico
            $errorCode = null;
            $errorBody = $response->body();
            
            try {
                $errorData = json_decode($errorBody, true);
                if (isset($errorData['errors'][0]['code'])) {
                    $errorCode = $errorData['errors'][0]['code'];
                }
            } catch (\Exception $e) {
                // Si no es JSON válido, mantener null
            }

            // Idempotencia: si ya estaba cancelada, considerar éxito
            if ($errorCode === 'order_already_canceled') {
                Log::info('MercadoPagoTerminal: Orden ya cancelada en API (idempotente)', [
                    'order_id' => $orderId,
                    'status_code' => $statusCode,
                ]);

                return [
                    'success' => true,
                    'already_canceled' => true,
                ];
            }

            return [
                'success' => false,
                'error' => $errorBody,
                'error_code' => $errorCode,
                'status_code' => $statusCode,
            ];

        } catch (\Exception $e) {
            Log::error('MercadoPagoTerminal: Excepción cancelando orden', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Marcar orden como cancelada en mp_terminal_orders + payment_provider_tickets
     */
    private function markOrderCancelled(string $orderId, string $reason = null): void
    {
        try {
            // Actualizar mp_terminal_orders
            $mpOrder = MpTerminalOrder::where('order_id', $orderId)->first();
            if ($mpOrder) {
                $mpOrder->markCancelled($reason);
            }

            // Actualizar payment_provider_tickets si existe
            $ticket = PaymentProviderTicket::where('payment_provider_id', $this->provider->id)
                ->where('transaction_id', $orderId)
                ->first();

            if ($ticket) {
                $ticket->update([
                    'status' => 'cancelled',
                    'response_data' => array_merge(
                        $ticket->response_data ?? [],
                        [
                            'auto_cancelled_at' => now()->toIso8601String(),
                            'auto_cancel_reason' => $reason,
                        ]
                    ),
                ]);

                Log::info('MercadoPagoTerminal: Ticket marcado como cancelled en BD', [
                    'ticket_id' => $ticket->id,
                    'order_id' => $orderId,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('MercadoPagoTerminal: Error marcando orden como cancelled', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Registrar nueva orden en mp_terminal_orders cuando se crea exitosamente
     */
    public function trackNewOrder(
        string $terminalId,
        string $orderId,
        string $externalReference,
        ?int $ticketId = null,
        ?array $responseData = null
    ): MpTerminalOrder {
        // Marcar cualquier orden previa como expirada
        MpTerminalOrder::where('payment_provider_id', $this->provider->id)
            ->where('terminal_id', $terminalId)
            ->whereIn('status', ['active', 'pending', 'processing'])
            ->update([
                'status' => 'expired',
                'cancelled_at' => now(),
            ]);

        // Crear nuevo registro
        $mpOrder = MpTerminalOrder::create([
            'payment_provider_id' => $this->provider->id,
            'payment_provider_ticket_id' => $ticketId,
            'terminal_id' => $terminalId,
            'order_id' => $orderId,
            'external_reference' => $externalReference,
            'status' => 'active',
            'response_data' => $responseData ?? [],
            'created_at' => now(),
        ]);

        Log::info('MercadoPagoTerminal: Nueva orden trackeada', [
            'order_id' => $orderId,
            'terminal_id' => $terminalId,
            'external_reference' => $externalReference,
        ]);

        return $mpOrder;
    }

    /**
     * Obtener status actual de una orden
     */
    public function getOrderStatus(string $orderId): array
    {
        try {
            $mpOrder = MpTerminalOrder::where('order_id', $orderId)->first();

            if ($mpOrder) {
                return [
                    'order_id' => $orderId,
                    'status' => $mpOrder->status,
                    'source' => 'local',
                ];
            }

            // Fallback a API si no está en BD
            $accessToken = $this->provider->getConfig('access_token');
            if ($accessToken) {
                $url = self::MP_API_BASE . "/{$orderId}";
                $response = Http::withToken($accessToken)
                    ->timeout(self::API_TIMEOUT)
                    ->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'order_id' => $orderId,
                        'status' => $data['status'] ?? 'unknown',
                        'source' => 'api',
                        'full_data' => $data,
                    ];
                }
            }

            return [
                'order_id' => $orderId,
                'status' => 'unknown',
                'source' => 'none',
            ];

        } catch (\Exception $e) {
            Log::error('MercadoPagoTerminal: Error obteniendo status de orden', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'order_id' => $orderId,
                'status' => 'unknown',
                'error' => $e->getMessage(),
            ];
        }
    }
}
