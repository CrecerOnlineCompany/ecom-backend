<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Screening;
use App\Models\PaymentProviderTicket;
use App\Models\PaymentProvider;
use App\Actions\Payments\StartOrderPaymentAction;
use App\Actions\Payments\FinalizeOrderPaymentAction;
use App\Actions\Payments\CancelOrderPaymentAction;
use App\Actions\Orders\ExpireOrdersAction;
use App\Enums\PaymentStatus;
use App\Services\PaymentProviders\PaymentProviderManager;
use App\Services\PaymentMethods\PaymentMethodService;
use App\Services\OrderNumberGenerator;
use App\Services\OrderFinalizationService;
use App\Services\SeatInventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected PaymentProviderManager $paymentManager;
    protected StartOrderPaymentAction $startOrderPayment;
    protected FinalizeOrderPaymentAction $finalizeOrderPayment;
    protected CancelOrderPaymentAction $cancelOrderPayment;
    protected ExpireOrdersAction $expireOrders;
    protected SeatInventoryService $inventoryService;

    public function __construct(
        PaymentProviderManager $paymentManager,
        StartOrderPaymentAction $startOrderPayment,
        FinalizeOrderPaymentAction $finalizeOrderPayment,
        CancelOrderPaymentAction $cancelOrderPayment,
        ExpireOrdersAction $expireOrders,
        SeatInventoryService $inventoryService
    ) {
        $this->paymentManager = $paymentManager;
        $this->startOrderPayment = $startOrderPayment;
        $this->finalizeOrderPayment = $finalizeOrderPayment;
        $this->cancelOrderPayment = $cancelOrderPayment;
        $this->expireOrders = $expireOrders;
        $this->inventoryService = $inventoryService;
    }

    public function index(): JsonResponse
    {
        try {
            $providers = $this->paymentManager->getActiveProviders();
            
            return response()->json([
                'success' => true,
                'providers' => $providers,
                'count' => count($providers),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener métodos de pago agrupados
     */
    public function getMethods(): JsonResponse
    {
        try {
            $groupedMethods = PaymentMethodService::getGroupedMethods();
            
            return response()->json([
                'success' => true,
                'methods' => $groupedMethods,
                'details' => PaymentMethodService::getMethodsWithDetails(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener proveedores para un método específico
     */
    public function getMethodProviders(string $method): JsonResponse
    {
        try {
            if (!PaymentMethodService::isMethodSupported($method)) {
                return response()->json([
                    'success' => false,
                    'message' => "Método de pago '$method' no soportado",
                ], 404);
            }

            $providers = PaymentMethodService::getProvidersForMethod($method);

            return response()->json([
                'success' => true,
                'method' => $method,
                'providers' => $providers,
                'method_info' => PaymentMethodService::getMethodInfo($method),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $provider = $this->paymentManager->getProviderInfo($id);
            
            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment provider not found',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'provider' => $provider,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesar pago batch (múltiples asientos)
     * ORDER-FIRST: Crea orden si no hay activa, reutiliza si existe con mismo idempotency_key
     * 
     * ROBUSTO: Soporta idempotency_key y payment_method desde FE
     * Para terminal: cancela último payment_provider_ticket activo antes de crear nuevo
     */
    public function processBatchPayment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'screening_id' => 'required|exists:screenings,id',
                'seat_ids' => 'required|array|min:1',
                'seat_ids.*' => 'required|exists:seats,id',
                'payment_provider_id' => 'required|exists:payment_providers,id',
                'customer_email' => 'required|email',
                'customer_name' => 'nullable|string|max:255',
                'customer_phone' => 'nullable|string|max:20',
                'additional_data' => 'array',
                'additional_data.idempotency_key' => 'nullable|string|uuid',
                'additional_data.payment_method' => 'nullable|string|in:redirect,qr,terminal',
            ]);

            // Detectar payment_method desde additional_data
            $paymentMethod = $validated['additional_data']['payment_method'] ?? 'redirect';

            return response()->json(
                $this->handleOrderFirstPayment($validated, $request, true, $paymentMethod)
            );

        } catch (\Exception $e) {
            Log::error("processBatchPayment error", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesar pago single (un asiento)
     * ORDER-FIRST: Crea orden si no hay activa, reutiliza si existe con mismo idempotency_key
     * 
     * ROBUSTO: Soporta idempotency_key y payment_method desde FE
     * Para terminal: cancela último payment_provider_ticket activo antes de crear nuevo
     */
    public function processPayment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'screening_id' => 'required|exists:screenings,id',
                'seat_id' => 'required|exists:seats,id',
                'payment_provider_id' => 'required|exists:payment_providers,id',
                'customer_email' => 'required|email',
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'nullable|string|max:20',
                'additional_data' => 'array',
                'additional_data.idempotency_key' => 'nullable|string|uuid',
                'additional_data.payment_method' => 'nullable|string|in:redirect,qr,terminal',
            ]);

            // Detectar payment_method desde additional_data
            $paymentMethod = $validated['additional_data']['payment_method'] ?? 'redirect';

            return response()->json(
                $this->handleOrderFirstPayment($validated, $request, false, $paymentMethod)
            );

        } catch (\Exception $e) {
            Log::error("processPayment error", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * CORE: Manejar flujo order-first con idempotencia
     * 
     * Lógica:
     * 1. Si idempotency_key existe: buscar orden activa con ese key
     * 2. Si existe orden compatible (reserved/payment_processing): reutilizar
     * 3. Si no existe: crear nueva orden
     * 4. Para terminal: cancelar último payment_provider_ticket activo
     * 5. Iniciar pago con PaymentProviderManager
     * 
     * @param array $validated Datos validados del request
     * @param Request $request Http request
     * @param bool $isBatch Si es batch o single
     * @param string|null $paymentMethod qr, terminal, redirect, etc
     */
    private function handleOrderFirstPayment(
        array $validated,
        Request $request,
        bool $isBatch,
        ?string $paymentMethod = null
    ): array {
        try {
            return DB::transaction(function () use ($validated, $request, $isBatch, $paymentMethod) {
                $screening = Screening::findOrFail($validated['screening_id']);
                $seatIds = $isBatch ? $validated['seat_ids'] : [$validated['seat_id']];
                $additionalData = $validated['additional_data'] ?? [];
                $idempotencyKey = $additionalData['idempotency_key'] ?? null;
                $customerEmail = $validated['customer_email'];
                
                Log::info("handleOrderFirstPayment: Iniciando flujo order-first", [
                    'idempotency_key' => $idempotencyKey,
                    'payload_method' => $paymentMethod,
                    'seat_count' => count($seatIds),
                    'customer_email' => $customerEmail,
                ]);

                // PASO 1: Buscar orden activa por idempotency_key
                $activeOrder = null;
                if ($idempotencyKey) {
                    $activeOrder = Order::where('customer_email', $customerEmail)
                        ->where('screening_id', $validated['screening_id'])
                        ->whereIn('status', [
                            Order::STATUS_DRAFT,
                            Order::STATUS_RESERVED,
                            Order::STATUS_PAYMENT_PROCESSING,
                        ])
                        ->first();

                    // Verificar que el idempotency_key coincida en response_data de payment_provider_ticket
                    if ($activeOrder) {
                        $paymentTicket = $activeOrder->paymentProviderTickets()
                            ->where('status', '!=', 'declined')
                            ->where('status', '!=', 'refunded')
                            ->latest()
                            ->first();

                        if ($paymentTicket && isset($paymentTicket->response_data['idempotency_key'])) {
                            if ($paymentTicket->response_data['idempotency_key'] !== $idempotencyKey) {
                                // Idempotency_key no coincide, buscar nueva orden
                                $activeOrder = null;
                            }
                        }
                    }
                }

                // PASO 2: Reutilizar orden o crear nueva
                $totalPrice = count($seatIds) * $screening->price;
                
                if ($activeOrder && in_array($activeOrder->status, [
                    Order::STATUS_RESERVED,
                    Order::STATUS_PAYMENT_PROCESSING,
                ])) {
                    // Reutilizar orden existente
                    Log::info("Reutilizando orden activa", [
                        'order_id' => $activeOrder->id,
                        'order_number' => $activeOrder->order_number,
                    ]);
                    $order = $activeOrder;
                    $order->update([
                        'total_amount' => $totalPrice,
                        'reserved_until' => now()->addMinutes(5),
                    ]);
                } else {
                    // Crear nueva orden
                    $user = auth()->user();
                    $userId = $user?->id ?? 1;
                    
                    $order = Order::create([
                        'uuid' => Str::uuid(),
                        'order_number' => OrderNumberGenerator::generate(),
                        'customer_name' => $validated['customer_name'],
                        'customer_email' => $customerEmail,
                        'customer_phone' => $validated['customer_phone'] ?? null,
                        'user_id' => $userId,
                        'screening_id' => $validated['screening_id'],
                        'total_amount' => $totalPrice,
                        'currency' => 'ARS',
                        'status' => Order::STATUS_RESERVED,
                        'purchase_device' => $paymentMethod ?? 'web',
                        'ip_address' => $request->ip(),
                        'reserved_until' => now()->addMinutes(6),
                    ]);

                    Log::info("Nueva orden creada en handleOrderFirstPayment", [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                    ]);
                }

                // PASO 3: Garantizar inventario y reservar asientos para la orden
                $this->inventoryService->ensureScreeningSeats($screening->id);

                // Si se reutiliza orden, limpiar reservas anteriores antes de reservar nuevos asientos
                if ($activeOrder && in_array($activeOrder->status, [
                    Order::STATUS_RESERVED,
                    Order::STATUS_PAYMENT_PROCESSING,
                ])) {
                    $this->inventoryService->releaseSeatsByOrder(
                        $order->id,
                        'order_refreshed_before_new_reservation'
                    );
                }

                $reservationResult = $this->inventoryService->reserveSeats(
                    screening_id: $screening->id,
                    seat_ids: $seatIds,
                    holder_type: 'user',
                    holder_id: (string) ($order->user_id ?? 1),
                    ttl_seconds: 360,
                    order_id: $order->id
                );

                if (!$reservationResult['success']) {
                    Log::warning("Seat reservation failed in handleOrderFirstPayment", [
                        'order_id' => $order->id,
                        'screening_id' => $screening->id,
                        'seat_ids' => $seatIds,
                        'failed' => $reservationResult['failed'],
                    ]);

                    throw new \Exception(
                        'Seat reservation failed: ' . json_encode($reservationResult['failed'])
                    );
                }

                // PASO 4: Para terminal (smart), cancelar último payment_provider_ticket activo
                if ($paymentMethod === 'terminal') {
                    $lastActivePayment = PaymentProviderTicket::where('order_id', $order->id)
                        ->whereIn('status', ['processing', 'pending', 'queued'])
                        ->latest()
                        ->first();

                    if ($lastActivePayment) {
                        Log::info("Cancelando último payment activo para terminal", [
                            'payment_ticket_id' => $lastActivePayment->id,
                            'old_status' => $lastActivePayment->status,
                        ]);

                        $lastActivePayment->update([
                            'status' => 'cancelled',
                            'response_data' => array_merge(
                                $lastActivePayment->response_data ?? [],
                                ['cancelled_reason' => 'superseded_by_new_terminal_attempt']
                            ),
                            'completed_at' => now(),
                        ]);
                    }
                }

                // PASO 5: Preparar additional_data con idempotency_key e info de pago
                $additionalData['total_price'] = $totalPrice;
                $additionalData['seat_count'] = count($seatIds);
                $additionalData['seat_ids'] = $seatIds;
                $additionalData['payment_method'] = $paymentMethod ?? 'redirect';
                
                if ($idempotencyKey) {
                    $additionalData['idempotency_key'] = $idempotencyKey;
                }

                // PASO 6: Iniciar pago
                Log::info("Iniciando pago con PaymentProviderManager", [
                    'order_id' => $order->id,
                    'payment_provider_id' => $validated['payment_provider_id'],
                    'payment_method' => $paymentMethod,
                ]);

                $paymentResult = $this->paymentManager->initiateOrderPayment(
                    $order,
                    $validated['payment_provider_id'],
                    $additionalData
                );

                if (!$paymentResult['success']) {
                    Log::error("Payment initiation failed", [
                        'order_id' => $order->id,
                        'message' => $paymentResult['message'] ?? 'Unknown',
                        'error_code' => $paymentResult['error_code'] ?? 'UNKNOWN',
                    ]);

                    return [
                        'success' => false,
                        'message' => $paymentResult['message'] ?? 'Payment initiation failed',
                        'error_code' => $paymentResult['error_code'] ?? 'PAYMENT_ERROR',
                    ];
                }

                Log::info("Payment initiated successfully", [
                    'order_id' => $order->id,
                    'transaction_id' => $paymentResult['transaction_id'] ?? 'N/A',
                ]);

                return [
                    'success' => true,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'seats_count' => count($seatIds),
                    'total_price' => $totalPrice,
                    'reserved_until' => $order->reserved_until->toIso8601String(),
                    'expires_in_minutes' => 6,
                    'transaction_id' => $paymentResult['transaction_id'] ?? null,
                    'payment_ticket_id' => $paymentResult['payment_ticket_id'] ?? null,
                    'redirect_url' => $paymentResult['redirect_url'] ?? null,
                    'qr_data' => $paymentResult['qr_data'] ?? null,
                    'qr_code' => $paymentResult['qr_code'] ?? null,
                    'terminal_id' => $paymentResult['terminal_id'] ?? null,
                    'requires_redirect' => $paymentResult['requires_redirect'] ?? false,
                    'requires_polling' => $paymentResult['requires_polling'] ?? false,
                    'polling_interval' => $paymentResult['polling_interval'] ?? 3000,
                    'message' => $paymentResult['message'] ?? 'Payment initiated successfully',
                ];

            }, attempts: 3);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            Log::error("Error en handleOrderFirstPayment", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => class_basename($e),
            ];
        }
    }

    /**
     * Procesar webhook de payment provider
     * 
     * ORDER-FIRST:
     * - Si payment aprobado: Llama approve() que luego finaliza orden
     * - Si payment rechazado: Marca como declined
     * - Manejo robusto de errores con logging completo
     * 
     * @param string $hash Webhook secret hash
     * @param Request $request Payload del provider
     * @return JsonResponse Always return 200 to acknowledge (providers expect 2xx)
     */
    public function webhook(string $hash, Request $request): JsonResponse
    {
        $webhookId = \Illuminate\Support\Str::uuid(); // Para tracing
        
        try {
            Log::info("=== WEBHOOK INICIADO ===", [
                'webhook_id' => $webhookId,
                'webhook_hash' => substr($hash, 0, 10) . '***',
                'ip' => $request->ip(),
            ]);

            // Step 1: Obtener provider por webhook hash
            $provider = \App\Models\PaymentProvider::where('webhook_secret', $hash)->firstOrFail();
            
            Log::info("Webhook provider encontrado", [
                'webhook_id' => $webhookId,
                'provider_name' => $provider->name,
                'provider_id' => $provider->id,
            ]);

            // Step 2: Procesar webhook con handler
            $success = $this->paymentManager->processWebhook($hash, $request);

            // Step 3: Retornar respuesta (SIEMPRE 200 para que provider no reintente)
            if ($success) {
                Log::info("Webhook procesado exitosamente", [
                    'webhook_id' => $webhookId,
                    'provider' => $provider->name,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Webhook processed successfully',
                    'webhook_id' => $webhookId,
                ], 200);
            } else {
                Log::warning("Webhook procesado con retorno false", [
                    'webhook_id' => $webhookId,
                    'provider' => $provider->name,
                    'note' => 'Puede ser error técnico o dato incompleto',
                ]);

                // IMPORTANTE: Retornar 200 igual para evitar que el provider reintente
                // (El handler ya logueó la razón del fallo)
                return response()->json([
                    'success' => false,
                    'message' => 'Webhook processing returned false',
                    'webhook_id' => $webhookId,
                ], 200);
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning("Webhook: Provider no encontrado", [
                'webhook_id' => $webhookId,
                'webhook_hash' => substr($hash, 0, 10) . '***',
                'error' => 'Invalid or unknown webhook hash',
            ]);

            // Retornar 200 para no alertar al attacker
            return response()->json([
                'success' => false,
                'message' => 'Provider not found',
                'webhook_id' => $webhookId,
            ], 200);

        } catch (\Exception $e) {
            Log::error("Webhook: Error inesperado", [
                'webhook_id' => $webhookId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Retornar 200 para que provider saiba que recibimos su notificación
            // pero hubo un error de nuestra parte
            return response()->json([
                'success' => false,
                'message' => 'Internal server error processing webhook',
                'webhook_id' => $webhookId,
                'error' => app()->environment('production') ? 'Internal error' : $e->getMessage(),
            ], 200);
        }
    }

    public function refund(int $paymentTicketId, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $success = $this->paymentManager->refundPayment(
                $paymentTicketId,
                $validated['reason'] ?? 'Refund requested'
            );

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refund processing failed',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function status(int|string $paymentTicketId): JsonResponse
    {
        try {
            // Convert string to int if needed (for UUID or hash lookups)
            if (is_string($paymentTicketId)) {
                // Try to find PaymentProviderTicket by hash/uuid if numeric conversion fails
                $paymentTicket = PaymentProviderTicket::where('transaction_id', $paymentTicketId)
                    ->orWhereRaw('CAST(id AS CHAR) = ?', [$paymentTicketId])
                    ->first();
                if (!$paymentTicket) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment record not found',
                    ], 404);
                }
                $paymentTicketId = $paymentTicket->id;
            }
            
            $status = $this->paymentManager->getPaymentStatus($paymentTicketId);
            
            return response()->json([
                'success' => true,
                'status' => $status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener detalle completo de una orden por número de orden.
     * Incluye: orden, pagos relacionados y tickets relacionados.
     */
    public function orderDetails(string $orderNumber): JsonResponse
    {
        try {
            $order = Order::where('order_number', $orderNumber)
                ->with([
                    'screening.movie',
                    'screening.room.cinema',
                    'tickets' => function ($query) {
                        $query->with('details')->orderBy('id');
                    },
                    'paymentProviderTickets' => function ($query) {
                        $query->with('paymentProvider')->orderByDesc('id');
                    },
                ])
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Orden no encontrada',
                ], 404);
            }

            $payments = $order->paymentProviderTickets->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'status' => $payment->status,
                    'transaction_id' => $payment->transaction_id,
                    'reference_number' => $payment->reference_number,
                    'payment_provider_id' => $payment->payment_provider_id,
                    'payment_provider_name' => $payment->paymentProvider?->name,
                    'initiated_at' => $payment->initiated_at?->toIso8601String(),
                    'completed_at' => $payment->completed_at?->toIso8601String(),
                    'created_at' => $payment->created_at?->toIso8601String(),
                    'updated_at' => $payment->updated_at?->toIso8601String(),
                    'response_data' => $payment->response_data,
                ];
            });

            $tickets = $order->tickets->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'status' => $ticket->status,
                    'price' => $ticket->price,
                    'qr_code' => $ticket->qr_code,
                    'customer_name' => $ticket->customer_name,
                    'customer_email' => $ticket->customer_email,
                    'customer_phone' => $ticket->customer_phone,
                    'created_at' => $ticket->created_at?->toIso8601String(),
                    'updated_at' => $ticket->updated_at?->toIso8601String(),
                    'details' => $ticket->details->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'seat_id' => $detail->seat_id,
                            'seat_code' => $detail->seat_code,
                            'row_number' => $detail->row_number,
                            'seat_number' => $detail->seat_number,
                            'price' => $detail->price,
                            'status' => $detail->status,
                            'qr_code' => $detail->qr_code,
                            'used_at' => $detail->used_at?->toIso8601String(),
                        ];
                    })->values(),
                ];
            });

            return response()->json([
                'success' => true,
                'order' => [
                    'id' => $order->id,
                    'uuid' => $order->uuid,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_amount' => $order->total_amount,
                    'currency' => $order->currency,
                    'customer_name' => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'customer_phone' => $order->customer_phone,
                    'purchase_device' => $order->purchase_device,
                    'reserved_until' => $order->reserved_until?->toIso8601String(),
                    'paid_at' => $order->paid_at?->toIso8601String(),
                    'cancelled_at' => $order->cancelled_at?->toIso8601String(),
                    'created_at' => $order->created_at?->toIso8601String(),
                    'updated_at' => $order->updated_at?->toIso8601String(),
                    'screening' => [
                        'id' => $order->screening?->id,
                        'start_time' => $order->screening?->start_time?->toIso8601String(),
                        'format' => $order->screening?->format,
                        'movie' => [
                            'id' => $order->screening?->movie?->id,
                            'title' => $order->screening?->movie?->title,
                        ],
                        'room' => [
                            'id' => $order->screening?->room?->id,
                            'name' => $order->screening?->room?->name,
                            'cinema' => [
                                'id' => $order->screening?->room?->cinema?->id,
                                'name' => $order->screening?->room?->cinema?->name,
                            ],
                        ],
                    ],
                ],
                'payments' => $payments->values(),
                'payments_count' => $payments->count(),
                'tickets' => $tickets->values(),
                'tickets_count' => $tickets->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('orderDetails error', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo detalle de la orden',
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesar pago por código QR (Mercado Pago QR)
     * ORDER-FIRST: Crea orden si no hay activa, reutiliza si existe con mismo idempotency_key
     * 
     * Flujo:
     * 1. Detecta si hay orden activa usando idempotency_key
     * 2. Si existe y tiene estado compatible: reutiliza
     * 3. Si no existe: crea nueva orden
     * 4. Genera payment_provider_ticket con QR
     */
    public function processQrPayment(Request $request): JsonResponse
    {
        try {
            Log::info("=== INICIO PROCESS QR PAYMENT ===");
            
            $validated = $request->validate([
                'screening_id' => 'required|exists:screenings,id',
                'seat_ids' => 'required|array|min:1',
                'seat_ids.*' => 'exists:seats,id',
                'payment_provider_id' => 'required|exists:payment_providers,id',
                'customer_email' => 'required|email',
                'customer_name' => 'nullable|string|max:255',
                'customer_phone' => 'nullable|string|max:20',
                'additional_data' => 'array',
                'additional_data.idempotency_key' => 'nullable|string|uuid',
            ]);

            $validated['additional_data'] = $validated['additional_data'] ?? [];
            $validated['additional_data']['payment_method'] = 'qr';

            return response()->json(
                $this->handleOrderFirstPayment($validated, $request, true, 'qr')
            );

        } catch (\Exception $e) {
            Log::error("QR payment processing error", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesar pago por terminal (Mercado Pago Smart Point)
     * ORDER-FIRST: Crea orden si no hay activa, reutiliza si existe con mismo idempotency_key
     * 
     * Flujo:
     * 1. Detecta si hay orden activa usando idempotency_key
     * 2. Cancela último payment_provider_ticket activo si existe
     * 3. Crea nueva orden si no existe
     * 4. Genera payment_provider_ticket con terminal
     */
    public function processTerminalPayment(Request $request): JsonResponse
    {
        try {
            Log::info("=== INICIO PROCESS TERMINAL PAYMENT ===");
            
            $validated = $request->validate([
                'screening_id' => 'required|exists:screenings,id',
                'seat_ids' => 'required|array|min:1',
                'seat_ids.*' => 'exists:seats,id',
                'payment_provider_id' => 'required|exists:payment_providers,id',
                'customer_email' => 'required|email',
                'customer_name' => 'nullable|string|max:255',
                'customer_phone' => 'nullable|string|max:20',
                'additional_data' => 'array',
                'additional_data.idempotency_key' => 'nullable|string|uuid',
                'terminal_id' => 'nullable|string|max:50',
            ]);

            $validated['additional_data'] = $validated['additional_data'] ?? [];
            $validated['additional_data']['payment_method'] = 'terminal';
            $validated['additional_data']['terminal_id'] = $validated['terminal_id'] ?? null;

            return response()->json(
                $this->handleOrderFirstPayment($validated, $request, true, 'terminal')
            );

        } catch (\Exception $e) {
            Log::error("Terminal payment processing error", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancelar un intento de pago pendiente
     * Delega a CancelOrderPaymentAction para order-first
     */
    public function cancelPendingPayment(int $paymentTicketId): JsonResponse
    {
        try {
            $paymentTicket = PaymentProviderTicket::findOrFail($paymentTicketId);

            if (!in_array($paymentTicket->status, ['processing', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot cancel payment in status: {$paymentTicket->status}",
                    'error_code' => 'INVALID_STATUS',
                ], 422);
            }

            // ORDER-FIRST: Delega a Action
            $this->cancelOrderPayment->cancel(
                $paymentTicket,
                'User cancelled payment'
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment cancelled successfully',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
                'error_code' => 'NOT_FOUND',
            ], 404);
        } catch (\Exception $e) {
            Log::error("Error in cancelPendingPayment", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancelar una orden por order_number
     * 
     * Casos:
     * - Si la orden tiene tickets confirmados: retorna 422 con la orden y sus tickets (no se puede cancelar)
     * - Si no hay tickets ni pagos: libera asientos reservados y marca la orden como cancelled
     */
    public function cancelOrderByNumber(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_number' => 'required|string',
            ]);

            $orderNumber = $request->input('order_number');

            // Buscar la orden
            $order = Order::where('order_number', $orderNumber)
                ->with(['tickets', 'screening'])
                ->firstOrFail();

            Log::info("Order cancellation requested", [
                'order_number' => $orderNumber,
                'order_id' => $order->id,
                'ticket_count' => $order->tickets()->count(),
            ]);

            // Verificar si tiene tickets confirmados
            $confirmedTickets = $order->tickets()
                ->where('status', Ticket::STATUS_COMPLETED)
                ->get();

            // CASO 1: Tiene tickets - NO se puede cancelar
            if ($confirmedTickets->isNotEmpty()) {
                Log::warning("Order cancellation rejected - has confirmed tickets", [
                    'order_number' => $orderNumber,
                    'confirmed_tickets_count' => $confirmedTickets->count(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cancelar una orden que tiene tickets confirmados',
                    'error_code' => 'ORDER_HAS_TICKETS',
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'total_amount' => $order->total_amount,
                        'currency' => $order->currency,
                        'created_at' => $order->created_at,
                        'tickets_count' => $confirmedTickets->count(),
                        'tickets' => $confirmedTickets->map(fn($ticket) => [
                            'id' => $ticket->id,
                            'ticket_number' => $ticket->ticket_number,
                            'seat_code' => $ticket->seat_code,
                            'price' => $ticket->price,
                            'status' => $ticket->status,
                        ]),
                    ],
                ], 422);
            }

            // CASO 2: Sin tickets - Proceder con cancelación
            $inventoryService = $this->inventoryService;
            DB::transaction(function () use ($order, $inventoryService) {
                // Liberar asientos reservados
                $releasedSeats = $inventoryService->releaseSeatsByOrder(
                    (int)$order->id,
                    'order_cancellation'
                );

                Log::info("Seats released for order cancellation", [
                    'order_id' => $order->id,
                    'released_count' => $releasedSeats,
                ]);

                // Marcar la orden como cancelled
                $order->update([
                    'status' => Order::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);

                Log::info("Order cancelled successfully", [
                    'order_number' => $order->order_number,
                    'order_id' => $order->id,
                    'released_seats' => $releasedSeats,
                ]);
            }, attempts: 3);
            return response()->json([
                'success' => true,
                'message' => 'Orden cancelada correctamente',
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_amount' => $order->total_amount,
                    'currency' => $order->currency,
                    'cancelled_at' => $order->cancelled_at,
                    'created_at' => $order->created_at,
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning("Order not found for cancellation", [
                'order_number' => $request->input('order_number') ?? 'unknown',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada',
                'error_code' => 'ORDER_NOT_FOUND',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validación fallida',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error("Error in cancelOrderByNumber", [
                'error' => $e->getMessage(),
                'order_number' => $request->input('order_number') ?? 'unknown',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar la orden: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirmar pago QR (endpoint para escaneo)
     */
    public function confirmQrPayment(int $paymentTicketId, string $token): JsonResponse
    {
        try {
            $paymentTicket = PaymentProviderTicket::findOrFail($paymentTicketId);

            // Validar el token
            $responseData = $paymentTicket->response_data ?? [];
            if (($responseData['qr_token'] ?? null) !== $token) {
                Log::warning('QR payment token mismatch', [
                    'payment_ticket_id' => $paymentTicketId,
                    'expected_token' => $responseData['qr_token'] ?? 'none',
                    'provided_token' => $token,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido',
                    'error_code' => 'INVALID_TOKEN',
                ], 401);
            }

            // Verificar que aún está en estado procesando (no ya pagado)
            if ($paymentTicket->status !== 'processing') {
                return response()->json([
                    'success' => false,
                    'message' => "El pago ya fue procesado (estado: {$paymentTicket->status})",
                    'error_code' => 'ALREADY_PROCESSED',
                ], 422);
            }

            // Aprobar el pago
            $paymentTicket->approve([
                'payment_method' => 'qr',
                'confirmed_at' => now()->toIso8601String(),
                'confirmation_user_agent' => request()->header('User-Agent'),
                'confirmation_ip' => request()->ip(),
            ]);

            Log::info('QR payment confirmed', [
                'payment_ticket_id' => $paymentTicketId,
            ]);

            return response()->json([
                'success' => true,
                'message' => '¡Pago confirmado! Tu entrada ha sido registrada.',
                'payment_ticket_id' => $paymentTicketId,
            ]);

        } catch (\Exception $e) {
            Log::error('Error confirming QR payment: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar el pago: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Limpiar órdenes y tickets expirados
     * Delega a ExpireOrdersAction para order-first
     */
    public function cleanup(Request $request): JsonResponse
    {
        if (app()->environment('production') && !$request->header('X-Cleanup-Token')) {
            return response()->json([
                'success' => false,
                'message' => 'Not authorized',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'screening_id' => 'nullable|exists:screenings,id',
                'hours' => 'nullable|integer|min:0',
            ]);

            $useSeatInventory = (bool)config('features.seat_inventory', false);

            // ORDER-FIRST: Usar Action para limpiar órdenes expiradas
            if ($useSeatInventory) {
                $result = $this->expireOrders->cleanup(
                    $validated['screening_id'] ?? null,
                    $validated['hours'] ?? null
                );

                return response()->json([
                    'success' => true,
                    'message' => "Cleanup completed: {$result['total_orders']} order(s) expired, "
                               . "{$result['total_tickets']} ticket(s) marked expired.",
                    'expired_orders' => $result['expired_orders'],
                    'expired_tickets' => $result['expired_tickets'],
                    'total_orders' => $result['total_orders'],
                    'total_tickets' => $result['total_tickets'],
                ]);
            }

            // LEGACY: Limpiar tickets incompletos
            $ticketQuery = Ticket::whereIn('status', ['pending_payment', 'processing', 'payment_failed']);

            if ($validated['screening_id'] ?? null) {
                $ticketQuery->where('screening_id', $validated['screening_id']);
            }

            if ($validated['hours'] ?? null) {
                $threshold = now()->subHours($validated['hours']);
                $ticketQuery->where('created_at', '<', $threshold);
            }

            $incompleteTickets = $ticketQuery->get();

            if ($incompleteTickets->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No incomplete tickets to clean',
                    'deleted_count' => 0,
                ]);
            }

            $deletedCount = 0;
            foreach ($incompleteTickets as $ticket) {
                $ticket->delete();
                $deletedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Deleted {$deletedCount} incomplete ticket(s)",
                'deleted_count' => $deletedCount,
            ]);

        } catch (\Exception $e) {
            Log::error("Cleanup error", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verificación manual del estado de pago en Mercado Pago
     * 
     * Este endpoint permite al frontend verificar manualmente el estado de un pago
     * cuando el webhook podría haber fallado. Consulta directamente a Mercado Pago
     * y actualiza el estado en la base de datos si es necesario.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function manualCheckPaymentStatus(Request $request): JsonResponse
    {
        try {
            Log::info("=== MANUAL PAYMENT CHECK INICIADO ===", [
                'request_data' => $request->all(),
            ]);

            // Validar la solicitud
            $validated = $request->validate([
                'payment_ticket_id' => 'required|exists:payment_provider_tickets,id',
                'order_number' => 'required|exists:orders,order_number',
                'order_id' => 'required|exists:orders,id',
                'payment_provider_id' => 'required|exists:payment_providers,id',
            ]);

            // Obtener el PaymentProviderTicket
            $paymentTicket = PaymentProviderTicket::with([
                'order.screening.movie',
                'paymentProvider'
            ])->findOrFail($validated['payment_ticket_id']);

            // Validar que pertenezcan a la misma orden
            if ($paymentTicket->order_id != $validated['order_id']) {
                Log::warning('Manual check: Payment ticket does not belong to order', [
                    'payment_ticket_id' => $validated['payment_ticket_id'],
                    'payment_order_id' => $paymentTicket->order_id,
                    'requested_order_id' => $validated['order_id'],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'El pago no pertenece a la orden especificada',
                ], 422);
            }

            // Obtener la orden
            $order = $paymentTicket->order;

            if (!$order) {
                Log::warning('Manual check: Order not found', [
                    'payment_ticket_id' => $validated['payment_ticket_id'],
                    'order_id' => $validated['order_id'],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Orden no encontrada',
                ], 404);
            }

            // Validar que sea Mercado Pago
            if ($paymentTicket->paymentProvider?->name !== 'mercado_pago') {
                Log::warning('Manual check: Payment provider is not mercado_pago', [
                    'payment_ticket_id' => $validated['payment_ticket_id'],
                    'provider' => $paymentTicket->paymentProvider?->name,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Este endpoint solo funciona con Mercado Pago',
                ], 422);
            }

            // Obtener el transaction_id (orderId en Mercado Pago)
            $transactionId = $paymentTicket->transaction_id;

            if (empty($transactionId)) {
                Log::warning('Manual check: Payment has no transaction_id', [
                    'payment_ticket_id' => $validated['payment_ticket_id'],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'El pago no tiene ID de transacción en Mercado Pago',
                ], 422);
            }

            // Consultar estado en Mercado Pago
            Log::info("Manual check: Consultando estado en Mercado Pago", [
                'transaction_id' => $transactionId,
                'payment_ticket_id' => $validated['payment_ticket_id'],
            ]);

            $mpStatus = $this->getOrderStatusFromMercadoPago($transactionId);

            if ($mpStatus === null) {
                Log::warning('Manual check: No se pudo obtener estado de MP', [
                    'transaction_id' => $transactionId,
                    'payment_ticket_id' => $validated['payment_ticket_id'],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo verificar el estado en Mercado Pago',
                    'status' => 'unknown',
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                    ],
                    'payment' => [
                        'id' => $paymentTicket->id,
                        'status' => $paymentTicket->status,
                        'transaction_id' => $transactionId,
                        'provider' => 'mercado_pago',
                    ],
                ], 422);
            }

            // Mapear estado de Mercado Pago
            $newStatus = $this->mapMercadoPagoStatus($mpStatus);
            $oldStatus = $paymentTicket->status;
            $statusChanged = $newStatus !== $oldStatus;

            Log::info("Manual check: Estado obtenido de MP", [
                'transaction_id' => $transactionId,
                'mercado_pago_status' => $mpStatus,
                'mapped_status' => $newStatus,
                'old_status' => $oldStatus,
                'status_changed' => $statusChanged,
            ]);

            // Si el estado cambió, actualizar
            if ($statusChanged) {
                try {
                    // Obtener datos completos del pago de Mercado Pago
                    $paymentData = $this->getPaymentDataFromMercadoPago($transactionId);

                    // Actualizar PaymentProviderTicket
                    $paymentTicket->update([
                        'status' => $newStatus,
                        'completed_at' => $newStatus === PaymentStatus::STATUS_COMPLETED ? now() : null,
                    ]);

                    // Guardar info completa en order->payment_data
                    if ($order) {
                        $paymentDataInOrder = $order->payment_data ?? [];
                        
                        $paymentDataInOrder['mercadopago'] = [
                            'transaction_id' => $transactionId,
                            'status' => $newStatus,
                            'mercadopago_status' => $mpStatus,
                            'payment_data' => $paymentData,
                            'last_sync_at' => now()->toIso8601String(),
                            'manual_check_at' => now()->toIso8601String(),
                        ];
                        
                        $order->update([
                            'payment_data' => $paymentDataInOrder,
                        ]);

                        Log::debug('Manual check: Payment data saved to Order', [
                            'order_id' => $order->id,
                            'payment_ticket_id' => $paymentTicket->id,
                            'transaction_id' => $transactionId,
                        ]);
                    }

                    Log::info('Manual check: Status actualizado', [
                        'payment_ticket_id' => $paymentTicket->id,
                        'order_id' => $order->id,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                    ]);

                    // Si el pago está completado, finalizar la orden
                    if ($newStatus === PaymentStatus::STATUS_COMPLETED && $order->id) {
                        try {
                            $finalizationService = app(OrderFinalizationService::class);
                            $result = $finalizationService->finalizeOrderAfterApproval(
                                $order->id,
                                [
                                    'transaction_id' => $transactionId,
                                    'status' => $newStatus,
                                    'mercadopago_status' => $mpStatus,
                                    'manual_check' => true,
                                ]
                            );

                            if ($result['success']) {
                                Log::info('Manual check: Order finalized successfully', [
                                    'order_id' => $order->id,
                                    'payment_ticket_id' => $paymentTicket->id,
                                    'tickets_created' => $result['finalized_tickets'] ?? 0,
                                ]);
                            } else {
                                Log::warning('Manual check: Order finalization failed', [
                                    'order_id' => $order->id,
                                    'payment_ticket_id' => $paymentTicket->id,
                                    'message' => $result['message'] ?? 'Unknown error',
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Manual check: Exception finalizing order', [
                                'order_id' => $order->id,
                                'payment_ticket_id' => $paymentTicket->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                } catch (\Exception $e) {
                    Log::error('Manual check: Error actualizando estado', [
                        'payment_ticket_id' => $paymentTicket->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Error al actualizar el estado del pago',
                        'details' => app()->environment('production') ? null : $e->getMessage(),
                    ], 500);
                }
            }

            // Recargar orden con estado actualizado
            $order->refresh();
            $paymentTicket->refresh();

            return response()->json([
                'success' => true,
                'status' => $newStatus,
                'message' => match($newStatus) {
                    PaymentStatus::STATUS_COMPLETED => 'Pago confirmado en Mercado Pago',
                    PaymentStatus::STATUS_PROCESSING => 'Pago en proceso',
                    PaymentStatus::STATUS_PENDING => 'Pago pendiente',
                    PaymentStatus::STATUS_FAILED => 'Pago rechazado',
                    default => 'Estado del pago actualizado',
                },
                'status_changed' => $statusChanged,
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                ],
                'payment' => [
                    'id' => $paymentTicket->id,
                    'status' => $newStatus,
                    'transaction_id' => $transactionId,
                    'provider' => 'mercado_pago',
                    'approved_at' => $paymentTicket->completed_at?->toIso8601String(),
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Manual check payment status error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al verificar el estado del pago',
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estado de una orden en Mercado Pago
     * (Reutilizado del comando CheckMercadoPagoPending)
     */
    private function getOrderStatusFromMercadoPago(string $orderId): ?string
    {
        try {
            $accessToken = $this->getMercadoPagoToken();
            
            $response = Http::withToken($accessToken)
                ->get("https://api.mercadopago.com/v1/orders/{$orderId}");

            if (!$response->successful()) {
                Log::warning('getOrderStatusFromMercadoPago: Error en respuesta de API MP', [
                    'order_id' => $orderId,
                    'status_code' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $order = $response->json();

            // Buscar en transactions.payments (estructura de API MP)
            if (isset($order['transactions']['payments']) && !empty($order['transactions']['payments'])) {
                $lastPayment = collect($order['transactions']['payments'])->last();
                
                if ($lastPayment && isset($lastPayment['status'])) {
                    Log::info('getOrderStatusFromMercadoPago: Estado obtenido de transactions.payments', [
                        'order_id' => $orderId,
                        'payment_status' => $lastPayment['status'],
                        'status_detail' => $lastPayment['status_detail'] ?? null,
                    ]);
                    return $lastPayment['status'];
                }
            }

            // Fallback: Si no hay pagos, usar estado de la orden
            if (isset($order['status'])) {
                Log::info('getOrderStatusFromMercadoPago: Usando estado de la orden', [
                    'order_id' => $orderId,
                    'order_status' => $order['status'],
                ]);
                return $order['status'];
            }

            Log::warning('getOrderStatusFromMercadoPago: No se encontró status en respuesta de MP', [
                'order_id' => $orderId,
                'response_keys' => array_keys($order),
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('getOrderStatusFromMercadoPago: Error inesperado', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
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
            Log::error('getPaymentDataFromMercadoPago: Error obteniendo datos del pago', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function success(Request $request): RedirectResponse
    {
        return redirect('/payment-success?id=' . ($request->input('external_reference') ?? ''));
    }

    public function failure(Request $request): RedirectResponse
    {
        return redirect('/payment-failed?id=' . ($request->input('external_reference') ?? ''));
    }

    public function pending(Request $request): RedirectResponse
    {
        return redirect('/checkout?payment=pending&id=' . ($request->input('external_reference') ?? ''));
    }
}
