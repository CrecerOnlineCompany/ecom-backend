<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Screening;
use App\Models\PaymentProviderTicket;
use App\Actions\Payments\StartOrderPaymentAction;
use App\Actions\Payments\FinalizeOrderPaymentAction;
use App\Actions\Payments\CancelOrderPaymentAction;
use App\Actions\Orders\ExpireOrdersAction;
use App\Services\PaymentProviders\PaymentProviderManager;
use App\Services\PaymentMethods\PaymentMethodService;
use App\Services\OrderNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected PaymentProviderManager $paymentManager;
    protected StartOrderPaymentAction $startOrderPayment;
    protected FinalizeOrderPaymentAction $finalizeOrderPayment;
    protected CancelOrderPaymentAction $cancelOrderPayment;
    protected ExpireOrdersAction $expireOrders;

    public function __construct(
        PaymentProviderManager $paymentManager,
        StartOrderPaymentAction $startOrderPayment,
        FinalizeOrderPaymentAction $finalizeOrderPayment,
        CancelOrderPaymentAction $cancelOrderPayment,
        ExpireOrdersAction $expireOrders
    ) {
        $this->paymentManager = $paymentManager;
        $this->startOrderPayment = $startOrderPayment;
        $this->finalizeOrderPayment = $finalizeOrderPayment;
        $this->cancelOrderPayment = $cancelOrderPayment;
        $this->expireOrders = $expireOrders;
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
                        'reserved_until' => now()->addMinutes(6),
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

                // PASO 3: Para terminal (smart), cancelar último payment_provider_ticket activo
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

                // PASO 4: Preparar additional_data con idempotency_key e info de pago
                $additionalData['total_price'] = $totalPrice;
                $additionalData['seat_count'] = count($seatIds);
                $additionalData['seat_ids'] = $seatIds;
                $additionalData['payment_method'] = $paymentMethod ?? 'redirect';
                
                if ($idempotencyKey) {
                    $additionalData['idempotency_key'] = $idempotencyKey;
                }

                // PASO 5: Iniciar pago
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
