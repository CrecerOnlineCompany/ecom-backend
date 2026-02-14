<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Screening;
use App\Models\PaymentProviderTicket;
use App\Services\PaymentProviders\PaymentProviderManager;
use App\Services\PaymentMethods\PaymentMethodService;
use App\Services\SeatInventoryService;
use App\Services\OrderNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class PaymentController extends Controller
{
    protected PaymentProviderManager $paymentManager;

    public function __construct(PaymentProviderManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
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

    public function processBatchPayment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'screening_id' => 'required|exists:screenings,id',
                'seat_ids' => 'required|array|min:1',
                'seat_ids.*' => 'required|exists:seats,id',
                'payment_provider_id' => 'required|exists:payment_providers,id',
                'customer_email' => 'required|email',
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'nullable|string|max:20',
                'additional_data' => 'array',
            ]);

            $user = auth()->user();
            $userId = $user?->id ?? 1;
            
            Log::info("Batch payment initiated", [
                'user_id' => $userId,
                'screening_id' => $validated['screening_id'],
                'seat_count' => count($validated['seat_ids']),
            ]);

            DB::beginTransaction();

            try {
                $screening = Screening::find($validated['screening_id']);
                $useSeatInventory = (bool)config('features.seat_inventory', false);
                
                $orderId = null;
                $orderNumber = null;
                $totalPrice = 0;
                $paymentTicketId = null;

                // ====================================================================
                // Order-first path (NEW): When seat_inventory feature is enabled
                // Calculate price first (for all scenarios)
                // ====================================================================
                $totalPrice = count($validated['seat_ids']) * $screening->price;

                if ($useSeatInventory) {
                    $inventoryService = app(SeatInventoryService::class);
                    
                    // Ensure screening seats exist
                    try {
                        $inventoryService->ensureScreeningSeats($validated['screening_id']);
                    } catch (\Exception $e) {
                        Log::error("ensureScreeningSeats failed", ['error' => $e->getMessage()]);
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Error checking seat availability.',
                            'error_code' => 'INVENTORY_ERROR',
                        ], 500);
                    }

                    // Create Order as cabinet for seat reservations
                    try {
                        $order = Order::create([
                            'uuid' => \Illuminate\Support\Str::uuid(),
                            'order_number' => OrderNumberGenerator::generate(),
                            'customer_name' => $validated['customer_name'],
                            'customer_email' => $validated['customer_email'],
                            'customer_phone' => $validated['customer_phone'] ?? null,
                            'user_id' => $userId,
                            'screening_id' => $validated['screening_id'],
                            'total_amount' => $totalPrice,
                            'currency' => 'ARS',
                            'status' => Order::STATUS_RESERVED,
                            'ip_address' => $request->ip(),
                            'reserved_until' => now()->addMinutes(6),
                        ]);
                        
                        $orderId = $order->id;
                        $orderNumber = $order->order_number;
                        
                        Log::info("Order created for batch", ['order_id' => $orderId, 'order_number' => $orderNumber]);
                    } catch (\Exception $e) {
                        Log::error("Order creation failed", ['error' => $e->getMessage()]);
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Error creating order.',
                            'error_code' => 'ORDER_ERROR',
                        ], 500);
                    }

                    // Reserve all seats atomically
                    $reservationResult = $inventoryService->reserveSeats(
                        screening_id: $validated['screening_id'],
                        seat_ids: $validated['seat_ids'],
                        holder_type: 'user',
                        holder_id: (string)$userId,
                        ttl_seconds: 360,
                        order_id: $orderId
                    );

                    if (!$reservationResult['success']) {
                        Log::warning("Batch seat reservation failed", ['failed' => $reservationResult['failed']]);
                        
                        // Mark order as cancelled (failed reservation)
                        $order->update([
                            'status' => Order::STATUS_CANCELLED,
                            'cancelled_at' => now(),
                        ]);
                        
                        DB::rollBack();
                        
                        $failedSeats = $this->buildFailedSeatsResponse($reservationResult['failed']);
                        
                        return response()->json([
                            'success' => false,
                            'message' => 'Some seats unavailable.',
                            'error_code' => 'SEATS_UNAVAILABLE',
                            'failed_seats' => $failedSeats,
                        ], 422);
                    }
                    
                    Log::info("Batch seat reservation successful", ['count' => count($reservationResult['reserved'])]);

                } else {
                    // ====================================================================
                    // Legacy path: When seat_inventory is disabled
                    // Still create pre-payment tickets (old behavior)
                    // ====================================================================
                    foreach ($validated['seat_ids'] as $seatId) {
                        $existingTicket = $screening->tickets()
                            ->where('seat_id', $seatId)
                            ->whereIn('status', ['confirmed', 'pending_payment', 'processing'])
                            ->first();
                        
                        if ($existingTicket) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Seat {$seatId} booked or processing.",
                                'error_code' => 'SEAT_UNAVAILABLE',
                            ], 422);
                        }
                    }
                }

                // ====================================================================
                // Initiate payment (NO TICKETS CREATED YET in seat_inventory mode)
                // ====================================================================
                if ($useSeatInventory) {
                    // Order-first: Initiate payment linked to order
                    $result = $this->paymentManager->initiateOrderPayment(
                        Order::find($orderId),
                        $validated['payment_provider_id'],
                        [
                            'seat_ids' => $validated['seat_ids'],
                            'seat_count' => count($validated['seat_ids']),
                            'total_price' => $totalPrice,
                        ]
                    );
                    
                    if ($result['payment_ticket_id'] ?? false) {
                        $paymentTicketId = $result['payment_ticket_id'];
                    }
                } else {
                    // Legacy: Create pre-payment tickets then initiate
                    $tickets = [];
                    foreach ($validated['seat_ids'] as $seatId) {
                        $ticket = Ticket::create([
                            'screening_id' => $validated['screening_id'],
                            'seat_id' => $seatId,
                            'user_id' => $userId,
                            'order_id' => $orderId,
                            'ticket_number' => null,
                            'price' => $screening->price,
                            'customer_email' => $validated['customer_email'],
                            'customer_name' => $validated['customer_name'],
                            'customer_phone' => $request->input('customer_phone'),
                            'status' => 'pending_payment',
                            'ip_address' => $request->ip(),
                        ]);
                        $tickets[] = ['id' => $ticket->id, 'seat_id' => $seatId, 'price' => $ticket->price];
                    }
                    
                    Log::info("Legacy batch tickets created", ['count' => count($tickets)]);
                    
                    // Legacy initiation via first ticket
                    $firstTicket = Ticket::find($tickets[0]['id']);
                    $additionalData = [
                        'total_price' => $totalPrice,
                        'seat_count' => count($validated['seat_ids']),
                        'all_ticket_ids' => array_column($tickets, 'id'),
                    ];
                    
                    $result = $this->paymentManager->initiatePayment(
                        $firstTicket,
                        $validated['payment_provider_id'],
                        $additionalData
                    );
                    
                    if ($result['payment_ticket_id'] ?? false) {
                        $paymentTicketId = $result['payment_ticket_id'];
                    }
                }

                if (!$result['success']) {
                    Log::warning("Batch payment initiation failed", ['result' => $result['message'] ?? 'Unknown']);
                    
                    // Cleanup on payment failure
                    if ($useSeatInventory && $orderId) {
                        $inventoryService->releaseSeatsByOrder($orderId, 'payment_failed');
                    }
                    
                    if ($orderId) {
                        Order::find($orderId)->update([
                            'status' => Order::STATUS_PAYMENT_FAILED,
                            'cancelled_at' => now(),
                        ]);
                    }
                    
                    DB::rollBack();
                    
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'] ?? 'Payment initiation failed.',
                        'error_code' => 'PAYMENT_ERROR',
                    ], 422);
                }

                DB::commit();
                
                Log::info("Batch payment initiated successfully", [
                    'order_id' => $orderId ?? 'N/A',
                    'transaction_id' => $result['transaction_id'] ?? 'N/A',
                ]);

                $response = [
                    'success' => true,
                    'total_price' => $totalPrice,
                    'seats_count' => count($validated['seat_ids']),
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'payment_ticket_id' => $paymentTicketId,
                    'redirect_url' => $result['redirect_url'] ?? null,
                    'requires_redirect' => $result['requires_redirect'] ?? false,
                    'message' => $result['message'] ?? 'Payment initiated.',
                ];
                
                if ($useSeatInventory && $orderId) {
                    $response['order_id'] = $orderId;
                    $response['order_number'] = $orderNumber;
                }

                return response()->json($response);

            } catch (QueryException $e) {
                DB::rollBack();
                Log::error("Database error in batch payment", ['code' => $e->getCode()]);
                
                if ($e->getCode() == 23000) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Seat processing conflict. Try again.',
                        'error_code' => 'DUPLICATE_BOOKING',
                    ], 422);
                }
                throw $e;
            }
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error("Batch payment exception", ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    }

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
            ]);

            $user = auth()->user();
            $userId = $user?->id ?? 1;
            
            Log::info("Single payment initiated", [
                'user_id' => $userId,
                'screening_id' => $validated['screening_id'],
                'seat_id' => $validated['seat_id'],
            ]);

            DB::beginTransaction();

            try {
                $screening = Screening::find($validated['screening_id']);
                $useSeatInventory = (bool)config('features.seat_inventory', false);
                
                $orderId = null;
                $orderNumber = null;
                $paymentTicketId = null;

                // ====================================================================
                // Order-first path (NEW): When seat_inventory feature is enabled
                // ====================================================================
                if ($useSeatInventory) {
                    $inventoryService = app(SeatInventoryService::class);
                    
                    // Ensure screening seats exist
                    try {
                        $inventoryService->ensureScreeningSeats($validated['screening_id']);
                    } catch (\Exception $e) {
                        Log::error("ensureScreeningSeats failed", ['error' => $e->getMessage()]);
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Error checking seat availability.',
                            'error_code' => 'INVENTORY_ERROR',
                        ], 500);
                    }

                    // Create Order as cabinet for seat reservation
                    try {
                        $order = Order::create([
                            'uuid' => \Illuminate\Support\Str::uuid(),
                            'order_number' => OrderNumberGenerator::generate(),
                            'customer_name' => $validated['customer_name'],
                            'customer_email' => $validated['customer_email'],
                            'customer_phone' => $validated['customer_phone'] ?? null,
                            'user_id' => $userId,
                            'screening_id' => $validated['screening_id'],
                            'total_amount' => $screening->price,
                            'currency' => 'ARS',
                            'status' => Order::STATUS_RESERVED,
                            'ip_address' => $request->ip(),
                            'reserved_until' => now()->addMinutes(6),
                        ]);
                        
                        $orderId = $order->id;
                        $orderNumber = $order->order_number;
                        
                        Log::info("Order created", ['order_id' => $orderId, 'order_number' => $orderNumber]);
                    } catch (\Exception $e) {
                        Log::error("Order creation failed", ['error' => $e->getMessage()]);
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Error creating order.',
                            'error_code' => 'ORDER_ERROR',
                        ], 500);
                    }

                    // Reserve seat atomically
                    $reservationResult = $inventoryService->reserveSeats(
                        screening_id: $validated['screening_id'],
                        seat_ids: [$validated['seat_id']],
                        holder_type: 'user',
                        holder_id: (string)$userId,
                        ttl_seconds: 360,
                        order_id: $orderId
                    );

                    if (!$reservationResult['success']) {
                        Log::warning("Seat reservation failed", ['failed' => $reservationResult['failed']]);
                        
                        // Mark order as cancelled (failed reservation)
                        $order->update([
                            'status' => Order::STATUS_CANCELLED,
                            'cancelled_at' => now(),
                        ]);
                        
                        DB::rollBack();
                        
                        $failedSeats = $this->buildFailedSeatsResponse($reservationResult['failed']);
                        
                        return response()->json([
                            'success' => false,
                            'message' => 'Seat unavailable.',
                            'error_code' => 'SEAT_UNAVAILABLE',
                            'failed_seats' => $failedSeats,
                        ], 422);
                    }
                    
                    Log::info("Seat reservation successful");

                } else {
                    // ====================================================================
                    // Legacy path: When seat_inventory is disabled
                    // ====================================================================
                    $existingTicket = $screening->tickets()
                        ->where('seat_id', $validated['seat_id'])
                        ->whereIn('status', ['confirmed', 'pending_payment', 'processing'])
                        ->first();
                    
                    if ($existingTicket) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Seat already booked or processing.',
                            'error_code' => 'SEAT_UNAVAILABLE',
                        ], 422);
                    }
                }

                // ====================================================================
                // Initiate payment (NO TICKET CREATED YET in seat_inventory mode)
                // ====================================================================
                if ($useSeatInventory) {
                    // Order-first: Initiate payment linked to order
                    $result = $this->paymentManager->initiateOrderPayment(
                        Order::find($orderId),
                        $validated['payment_provider_id'],
                        [
                            'seat_ids' => [$validated['seat_id']],
                            'seat_count' => 1,
                            'total_price' => $screening->price,
                        ]
                    );
                    
                    if ($result['payment_ticket_id'] ?? false) {
                        $paymentTicketId = $result['payment_ticket_id'];
                    }
                } else {
                    // Legacy: Create pre-payment ticket then initiate
                    $ticket = Ticket::create([
                        'screening_id' => $validated['screening_id'],
                        'seat_id' => $validated['seat_id'],
                        'user_id' => $userId,
                        'order_id' => $orderId,
                        'ticket_number' => null,
                        'price' => $screening->price,
                        'customer_email' => $validated['customer_email'],
                        'customer_name' => $validated['customer_name'],
                        'customer_phone' => $request->input('customer_phone'),
                        'status' => 'pending_payment',
                        'ip_address' => $request->ip(),
                    ]);

                    Log::info("Legacy ticket created", ['ticket_id' => $ticket->id]);

                    $additionalData = [
                        'total_price' => $screening->price,
                        'seat_count' => 1,
                        'all_ticket_ids' => [$ticket->id],
                    ];
                    
                    $result = $this->paymentManager->initiatePayment(
                        $ticket,
                        $validated['payment_provider_id'],
                        $additionalData
                    );
                    
                    if ($result['payment_ticket_id'] ?? false) {
                        $paymentTicketId = $result['payment_ticket_id'];
                    }
                }

                if (!$result['success']) {
                    Log::warning("Payment initiation failed", ['result' => $result['message'] ?? 'Unknown']);
                    
                    // Cleanup on payment failure
                    if ($useSeatInventory && $orderId) {
                        $inventoryService->releaseSeatsByOrder($orderId, 'payment_failed');
                    }
                    
                    if ($orderId) {
                        Order::find($orderId)->update([
                            'status' => Order::STATUS_PAYMENT_FAILED,
                            'cancelled_at' => now(),
                        ]);
                    }
                    
                    DB::rollBack();
                    
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'] ?? 'Payment initiation failed.',
                        'error_code' => 'PAYMENT_ERROR',
                    ], 422);
                }

                DB::commit();
                
                Log::info("Payment initiated successfully", [
                    'order_id' => $orderId ?? 'N/A',
                    'transaction_id' => $result['transaction_id'] ?? 'N/A',
                ]);

                $response = [
                    'success' => true,
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'payment_ticket_id' => $paymentTicketId,
                    'redirect_url' => $result['redirect_url'] ?? null,
                    'requires_redirect' => $result['requires_redirect'] ?? false,
                    'message' => $result['message'] ?? 'Payment initiated.',
                ];
                
                if ($useSeatInventory && $orderId) {
                    $response['order_id'] = $orderId;
                    $response['order_number'] = $orderNumber;
                }

                return response()->json($response);

            } catch (QueryException $e) {
                DB::rollBack();
                Log::error("Database error", ['code' => $e->getCode(), 'error' => $e->getMessage()]);
                
                if ($e->getCode() == 23000) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Seat processing conflict. Try again.',
                        'error_code' => 'DUPLICATE_BOOKING',
                    ], 422);
                }
                throw $e;
            }
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error("Payment processing exception", ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function webhook(string $hash, Request $request): JsonResponse
    {
        try {
            $success = $this->paymentManager->processWebhook($hash, $request);
            
            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Webhook processing failed',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Payment webhook error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
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

    public function status(int $paymentTicketId): JsonResponse
    {
        try {
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
     */
    public function processQrPayment(Request $request): JsonResponse
    {
        try {
            Log::info("=== INICIO PROCESS QR PAYMENT ===");
            Log::info("Request data: " . json_encode($request->all()));
            
            $validated = $request->validate([
                'screening_id' => 'required|exists:screenings,id',
                'seat_ids' => 'required|array|min:1',
                'seat_ids.*' => 'exists:seats,id',
                'payment_provider_id' => 'required|exists:payment_providers,id',
                'customer_email' => 'required|email',
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'nullable|string|max:20',
                'additional_data' => 'array',
            ]);

            $user = auth()->user();
            $userId = $user?->id ?? 1;
            
            Log::info("QR Payment validated successfully");
            Log::info("User ID: {$userId}, Email: {$validated['customer_email']}, Seats: " . implode(',', $validated['seat_ids']) . ", Screening: {$validated['screening_id']}");

            DB::beginTransaction();
            Log::info("Database transaction started");

            try {
                $screening = Screening::find($validated['screening_id']);
                Log::info("Screening found: {$screening->id}, Price: {$screening->price}");
                
                $tickets = [];
                $totalPrice = 0;

                // Verify all seats are available
                Log::info("Verifying " . implode(',', $validated['seat_ids']) . " seats availability");
                foreach ($validated['seat_ids'] as $seatId) {
                    // Buscar tickets confirmados (vendidos) para este asiento
                    $confirmedTicket = $screening->tickets()
                        ->where('seat_id', $seatId)
                        ->where('status', 'confirmed')
                        ->first();
                    
                    if ($confirmedTicket) {
                        Log::warning("Seat {$seatId} is already booked (confirmed)");
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "El asiento {$seatId} ya está vendido.",
                            'error_code' => 'SEAT_UNAVAILABLE',
                        ], 422);
                    }
                    
                    // Buscar tickets pendientes o procesando para este asiento
                    $pendingTicket = $screening->tickets()
                        ->where('seat_id', $seatId)
                        ->whereIn('status', ['pending_payment', 'processing'])
                        ->first();
                    
                    if ($pendingTicket && $pendingTicket->user_id !== $userId) {
                        // Es de otro usuario
                        Log::warning("Seat {$seatId} is being processed by another user");
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "El asiento {$seatId} está siendo procesado. Por favor intenta de nuevo en unos momentos.",
                            'error_code' => 'SEAT_PROCESSING',
                        ], 422);
                    }
                    
                    // Si es del mismo usuario, eliminar el anterior y permitir nuevo intento
                    if ($pendingTicket && $pendingTicket->user_id === $userId) {
                        Log::info("Deleting previous pending ticket {$pendingTicket->id} for seat {$seatId} from user {$userId}");
                        $pendingTicket->delete();
                    }
                }
                Log::info("All seats are available");

                // Create tickets for each seat
                Log::info("Creating " . count($validated['seat_ids']) . " tickets");
                foreach ($validated['seat_ids'] as $seatId) {
                    $ticket = Ticket::create([
                        'screening_id' => $validated['screening_id'],
                        'seat_id' => $seatId,
                        'user_id' => $userId,
                        'ticket_number' => null,  // Generated when payment is approved
                        'price' => $screening->price,
                        'customer_email' => $validated['customer_email'],
                        'customer_name' => $validated['customer_name'],
                        'customer_phone' => $request->input('customer_phone'),
                        'status' => 'pending_payment',
                    ]);

                    $tickets[] = [
                        'id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'price' => $ticket->price,
                        'seat_id' => $seatId,
                    ];
                    $totalPrice += $ticket->price;
                    Log::info("Created QR ticket ID: {$ticket->id}, Seat: {$seatId}");
                }
                Log::info("All " . count($validated['seat_ids']) . " tickets created, Total Price: {$totalPrice}");

                // Process payment with QR handler using first ticket
                $firstTicket = Ticket::find($tickets[0]['id']);
                $additionalData = $validated['additional_data'] ?? [];
                $additionalData['total_price'] = $totalPrice;
                $additionalData['seat_count'] = count($validated['seat_ids']);
                $additionalData['all_ticket_ids'] = array_column($tickets, 'id');
                
                Log::info("Initiating QR payment with PaymentProviderManager");
                Log::info("Additional data: " . json_encode($additionalData));
                
                $result = $this->paymentManager->initiatePaymentWithMethod(
                    $firstTicket,
                    $validated['payment_provider_id'],
                    'qr',
                    $additionalData
                );
                
                Log::info("Payment manager returned: " . json_encode($result));

                if (!$result['success']) {
                    Log::warning("QR payment processing failed: " . ($result['message'] ?? 'Unknown error'));
                    foreach ($tickets as $t) {
                        Ticket::find($t['id'])->update(['status' => 'payment_failed']);
                    }
                    DB::rollBack();
                    
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'] ?? 'QR payment processing failed',
                    ], 422);
                }

                DB::commit();
                Log::info("Database transaction committed successfully");
                Log::info("=== QR PAYMENT PROCESS COMPLETED SUCCESSFULLY ===");

                return response()->json([
                    'success' => true,
                    'tickets' => $tickets,
                    'total_price' => $totalPrice,
                    'tickets_count' => count($tickets),
                    'qr_code' => $result['qr_data'] ?? $result['qr_code'] ?? null,
                    'qr_data' => $result['qr_data'] ?? null,
                    'qr_type' => $result['qr_type'] ?? null,
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'payment_ticket_id' => $result['payment_ticket_id'] ?? null,
                    'requires_polling' => $result['requires_polling'] ?? true,
                    'message' => $result['message'] ?? 'QR payment initiated successfully',
                ]);

            } catch (QueryException $e) {
                DB::rollBack();
                Log::error("Database error during QR payment: " . $e->getMessage());
                
                if ($e->getCode() == 23000) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Uno o más asientos ya están siendo procesados. Por favor intenta de nuevo.',
                        'error_code' => 'DUPLICATE_BOOKING',
                    ], 422);
                }
                
                throw $e;
            }
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error("QR payment processing error: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesar pago por terminal (Mercado Pago Smart Point)
     */
    public function processTerminalPayment(Request $request): JsonResponse
    {
        try {
            Log::info("=== INICIO PROCESS TERMINAL PAYMENT ===");
            Log::info("Request data: Screening ID, Seats Count, Email (no PII logging)");
            
            $validated = $request->validate([
                'screening_id' => 'required|exists:screenings,id',
                'seat_ids' => 'required|array|min:1',
                'seat_ids.*' => 'exists:seats,id',
                'payment_provider_id' => 'required|exists:payment_providers,id',
                'customer_email' => 'required|email',
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'nullable|string|max:20',
                'additional_data' => 'array',
                'terminal_id' => 'nullable|string|max:50',  // For holder identification
            ]);

            $user = auth()->user();
            $userId = $user?->id ?? null;
            
            // Determine holder for seat inventory
            $holderType = 'terminal';
            $holderId = $validated['terminal_id'] ?? 'session_' . session()->getId();
            
            if ($userId) {
                $holderType = 'user';
                $holderId = (string)$userId;
            }
            
            Log::info("Terminal payment started", [
                'screening_id' => $validated['screening_id'],
                'seat_count' => count($validated['seat_ids']),
                'holder_type' => $holderType,
                'feature_seat_inventory' => (bool)config('features.seat_inventory'),
            ]);

            DB::beginTransaction();
            Log::info("Database transaction started");

            try {
                $screening = Screening::find($validated['screening_id']);
                Log::info("Screening found", ['screening_id' => $screening->id]);
                
                $tickets = [];
                $totalPrice = 0;
                $orderId = null;
                $orderNumber = null;

                // ====================================================================
                // FEATURE FLAG: Use new seat inventory system if enabled
                // ====================================================================
                $useSeatInventory = (bool)config('features.seat_inventory', false);
                
                if ($useSeatInventory) {
                    Log::info("Using seat inventory system (FEATURE_SEAT_INVENTORY enabled)");
                    
                    // Initialize seat inventory service
                    $inventoryService = app(\App\Services\SeatInventoryService::class);
                    
                    // Step 1: Ensure screening seats exist
                    try {
                        $ensureResult = $inventoryService->ensureScreeningSeats($validated['screening_id']);
                        Log::info("Screening seats ensured", [
                            'created' => $ensureResult['created'],
                            'existing' => $ensureResult['existing'],
                        ]);
                    } catch (\Exception $e) {
                        Log::error("Failed to ensure screening seats", ['error' => $e->getMessage()]);
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'No se pudo verificar la disponibilidad de asientos.',
                            'error_code' => 'INVENTORY_ERROR',
                        ], 500);
                    }

                    // Step 2: Create or get Order (cabinet for seats)
                    try {
                        $orderClass = \App\Models\Order::class;
                        $order = $orderClass::create([
                            'uuid' => \Illuminate\Support\Str::uuid(),
                            'order_number' => \App\Services\OrderNumberGenerator::generate(),
                            'customer_name' => $validated['customer_name'],
                            'customer_email' => $validated['customer_email'],
                            'customer_phone' => $validated['customer_phone'] ?? null,
                            'user_id' => $userId,
                            'screening_id' => $validated['screening_id'],
                            'total_amount' => 0,  // Will update after reservation
                            'currency' => 'ARS',
                            'status' => 'reserved',
                            'purchase_device' => 'terminal',
                            'ip_address' => $request->ip(),
                            'reserved_until' => now()->addMinutes(6),  // 6 minutes TTL
                        ]);
                        
                        $orderId = $order->id;
                        $orderNumber = $order->order_number;
                        
                        Log::info("Order created for seat reservation", [
                            'order_id' => $orderId,
                            'order_number' => $orderNumber,
                        ]);
                    } catch (\Exception $e) {
                        Log::error("Failed to create order", ['error' => $e->getMessage()]);
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Error creating order. Please try again.',
                            'error_code' => 'ORDER_CREATION_ERROR',
                        ], 500);
                    }

                    // Step 3: Reserve seats atomically
                    Log::info("Attempting to reserve seats", ['count' => count($validated['seat_ids'])]);
                    
                    // TTL for seat reservation: 6 minutes (360 seconds) - reasonable for terminal checkout
                    $seatReservationTTL = 360;
                    
                    $reservationResult = $inventoryService->reserveSeats(
                        screening_id: $validated['screening_id'],
                        seat_ids: $validated['seat_ids'],
                        holder_type: $holderType,
                        holder_id: $holderId,
                        ttl_seconds: $seatReservationTTL,
                        order_id: $orderId
                    );

                    if (!$reservationResult['success']) {
                        Log::warning("Seat reservation failed", [
                            'failed_seats' => $reservationResult['failed'],
                        ]);
                        
                        // Clean up: Release the order
                        $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                        
                        DB::rollBack();
                        
                        // Build detailed error message
                        $failureDetails = [];
                        foreach ($reservationResult['failed'] as $seatId => $reason) {
                            $failureDetails[] = [
                                'seat_id' => $seatId,
                                'reason' => $reason,
                                'error_code' => $reason === 'Sold' ? 'SEAT_SOLD' : 'SEAT_RESERVED',
                            ];
                        }
                        
                        return response()->json([
                            'success' => false,
                            'message' => 'Algunos asientos no están disponibles.',
                            'error_code' => 'SEATS_UNAVAILABLE',
                            'failed_seats' => $failureDetails,
                        ], 422);
                    }

                    Log::info("Seats reserved successfully", [
                        'reserved_count' => count($reservationResult['reserved']),
                    ]);

                } else {
                    // ====================================================================
                    // LEGACY: Use old ticket-based checking
                    // ====================================================================
                    Log::info("Using legacy ticket-based system (seat inventory disabled)");
                    
                    foreach ($validated['seat_ids'] as $seatId) {
                        // Buscar tickets confirmados (vendidos) para este asiento
                        $confirmedTicket = $screening->tickets()
                            ->where('seat_id', $seatId)
                            ->where('status', 'confirmed')
                            ->first();
                        
                        if ($confirmedTicket) {
                            Log::warning("Legacy check: Seat already booked", ['seat_id' => $seatId]);
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "El asiento {$seatId} ya está vendido.",
                                'error_code' => 'SEAT_UNAVAILABLE',
                            ], 422);
                        }
                        
                        // Buscar tickets pendientes o procesando para este asiento
                        $pendingTicket = $screening->tickets()
                            ->where('seat_id', $seatId)
                            ->whereIn('status', ['pending_payment', 'processing'])
                            ->first();
                        
                        if ($pendingTicket && $pendingTicket->user_id !== $userId) {
                            Log::warning("Legacy check: Seat being processed by another user", ['seat_id' => $seatId]);
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "El asiento {$seatId} está siendo procesado. Por favor intenta de nuevo en unos momentos.",
                                'error_code' => 'SEAT_PROCESSING',
                            ], 422);
                        }
                        
                        if ($pendingTicket && $pendingTicket->user_id === $userId) {
                            Log::info("Legacy check: Deleting previous pending ticket", ['seat_id' => $seatId]);
                            $pendingTicket->delete();
                        }
                    }
                }

                // ====================================================================
                // Create tickets (same flow regardless of inventory system)
                // ====================================================================
                Log::info("Creating tickets", ['count' => count($validated['seat_ids'])]);
                
                foreach ($validated['seat_ids'] as $seatId) {
                    $ticket = Ticket::create([
                        'screening_id' => $validated['screening_id'],
                        'seat_id' => $seatId,
                        'user_id' => $userId,
                        'order_id' => $orderId,  // Link to Order if created
                        'ticket_number' => null,  // Generated when payment is approved
                        'price' => $screening->price,
                        'customer_email' => $validated['customer_email'],
                        'customer_name' => $validated['customer_name'],
                        'customer_phone' => $request->input('customer_phone'),
                        'status' => 'pending_payment',
                        'purchase_device' => 'terminal',
                        'ip_address' => $request->ip(),
                    ]);

                    $tickets[] = [
                        'id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'price' => $ticket->price,
                        'seat_id' => $seatId,
                        'order_id' => $orderId,
                    ];
                    $totalPrice += $ticket->price;
                    Log::info("Ticket created", ['ticket_id' => $ticket->id]);
                }

                // Update order total if created
                if ($orderId) {
                    \App\Models\Order::find($orderId)->update(['total_amount' => $totalPrice]);
                }

                // ====================================================================
                // Process payment (unchanged logic)
                // ====================================================================
                $firstTicket = Ticket::find($tickets[0]['id']);
                $additionalData = $validated['additional_data'] ?? [];
                $additionalData['total_price'] = $totalPrice;
                $additionalData['seat_count'] = count($validated['seat_ids']);
                $additionalData['all_ticket_ids'] = array_column($tickets, 'id');
                $additionalData['order_id'] = $orderId;
                
                Log::info("Initiating terminal payment with PaymentProviderManager");
                
                $result = $this->paymentManager->initiatePaymentWithMethod(
                    $firstTicket,
                    $validated['payment_provider_id'],
                    'terminal',
                    $additionalData
                );
                
                Log::info("Payment manager result received");

                if (!$result['success']) {
                    Log::warning("Terminal payment processing failed", ['message' => $result['message'] ?? 'Unknown']);
                    
                    // Release seats if using inventory system
                    if ($useSeatInventory && $orderId) {
                        $inventoryService->releaseSeatsByOrder($orderId, 'payment_failed');
                    }
                    
                    foreach ($tickets as $t) {
                        Ticket::find($t['id'])->update(['status' => 'payment_failed']);
                    }
                    
                    if ($orderId) {
                        \App\Models\Order::find($orderId)->update([
                            'status' => 'payment_failed',
                            'cancelled_at' => now(),
                        ]);
                    }
                    
                    DB::rollBack();
                    
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'] ?? 'Terminal payment processing failed',
                    ], 422);
                }

                DB::commit();
                Log::info("Database transaction committed");
                Log::info("=== TERMINAL PAYMENT PROCESS COMPLETED SUCCESSFULLY ===");

                // Build response
                $response = [
                    'success' => true,
                    'tickets' => $tickets,
                    'total_price' => $totalPrice,
                    'tickets_count' => count($tickets),
                    'terminal_id' => $result['terminal_id'] ?? null,
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'payment_ticket_id' => $result['payment_ticket_id'] ?? null,
                    'requires_polling' => $result['requires_polling'] ?? true,
                    'polling_interval' => $result['polling_interval'] ?? 3000,
                    'message' => $result['message'] ?? 'Terminal payment initiated successfully',
                ];
                
                // Add order info if using inventory system
                if ($useSeatInventory && $orderId) {
                    $response['order_id'] = $orderId;
                    $response['order_number'] = $orderNumber;
                }

                return response()->json($response);

            } catch (QueryException $e) {
                DB::rollBack();
                Log::error("Database error during terminal payment", ['error' => $e->getCode()]);
                
                if ($e->getCode() == 23000) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Uno o más asientos ya están siendo procesados. Por favor intenta de nuevo.',
                        'error_code' => 'DUPLICATE_BOOKING',
                    ], 422);
                }
                
                throw $e;
            }
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error("Terminal payment processing error", ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancelar un intento de pago pendiente
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

            DB::beginTransaction();

            try {
                $useSeatInventory = (bool)config('features.seat_inventory', false);
                
                // Get tickets associated with this payment
                $tickets = Ticket::where('payment_provider_ticket_id', $paymentTicketId)->get();
                
                Log::info("Cancelling payment", [
                    'payment_ticket_id' => $paymentTicketId,
                    'ticket_count' => $tickets->count(),
                    'use_seat_inventory' => $useSeatInventory,
                ]);

                // ====================================================================
                // Order-first cancellation (NEW): When seat_inventory is enabled
                // ====================================================================
                if ($useSeatInventory) {
                    $inventoryService = app(SeatInventoryService::class);
                    $orderIds = [];
                    
                    // Group tickets by order
                    foreach ($tickets as $ticket) {
                        if ($ticket->order_id && !in_array($ticket->order_id, $orderIds)) {
                            $orderIds[] = $ticket->order_id;
                        }
                    }
                    
                    // Release seats and mark orders/tickets
                    foreach ($orderIds as $orderId) {
                        $order = Order::find($orderId);
                        if ($order) {
                            // Release seats back to inventory
                            $inventoryService->releaseSeatsByOrder($orderId, 'payment_cancelled');
                            
                            // Mark order as cancelled
                            $order->update([
                                'status' => Order::STATUS_CANCELLED,
                                'cancelled_at' => now(),
                            ]);
                            
                            Log::info("Order released and marked cancelled", [
                                'order_id' => $orderId,
                                'seats_released' => true,
                            ]);
                        }
                    }
                    
                    // Mark associated tickets as cancelled (don't delete - keep audit trail)
                    foreach ($tickets as $ticket) {
                        $ticket->update(['status' => 'cancelled']);
                    }
                    
                } else {
                    // ====================================================================
                    // Legacy cancellation: Delete tickets directly
                    // ====================================================================
                    foreach ($tickets as $ticket) {
                        $ticket->delete();
                    }
                }

                // Update payment ticket status
                $paymentTicket->update(['status' => 'cancelled']);

                DB::commit();

                Log::info("Payment cancelled successfully", [
                    'payment_ticket_id' => $paymentTicketId,
                    'affected_tickets' => count($tickets),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment cancelled successfully.',
                    'cancelled_tickets' => count($tickets),
                ]);

            } catch (QueryException $e) {
                DB::rollBack();
                Log::error("Database error cancelling payment", ['code' => $e->getCode()]);
                throw $e;
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment attempt not found.',
                'error_code' => 'NOT_FOUND',
            ], 404);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error("Error cancelling payment", ['error' => $e->getMessage()]);
            
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
                'seat_id' => 'nullable|exists:seats,id',
                'hours' => 'nullable|integer|min:0',
            ]);

            $useSeatInventory = (bool)config('features.seat_inventory', false);
            
            Log::info("Cleanup started", [
                'use_seat_inventory' => $useSeatInventory,
                'screening_id' => $validated['screening_id'] ?? 'all',
                'hours' => $validated['hours'] ?? 'all',
            ]);

            DB::beginTransaction();

            try {
                // ====================================================================
                // Order-first cleanup (NEW): When seat_inventory is enabled
                // ====================================================================
                if ($useSeatInventory) {
                    $inventoryService = app(SeatInventoryService::class);
                    
                    // Find expired orders
                    $orderQuery = Order::query()
                        ->where(function ($q) {
                            // Orders with reserved status where TTL has passed
                            $q->where('status', Order::STATUS_RESERVED)
                              ->whereNotNull('reserved_until')
                              ->where('reserved_until', '<', now());
                        })
                        ->orWhere(function ($q) {
                            // Orders in payment_failed status (can be cleaned after some time)
                            $q->where('status', Order::STATUS_PAYMENT_FAILED)
                              ->whereNotNull('cancelled_at')
                              ->where('cancelled_at', '<', now()->subHours(1));
                        });

                    // Apply screening filter if provided
                    if ($validated['screening_id'] ?? null) {
                        $orderQuery->where('screening_id', $validated['screening_id']);
                    }

                    // Apply hours filter if provided
                    if ($validated['hours'] ?? null) {
                        $threshold = now()->subHours($validated['hours']);
                        $orderQuery->where('created_at', '<', $threshold);
                    }

                    $expiredOrders = $orderQuery->get();

                    $clearedOrders = [];
                    $expiredTickets = [];

                    foreach ($expiredOrders as $order) {
                        Log::info("Cleanup: Processing expired order", [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'status' => $order->status,
                        ]);

                        // Release seats back to inventory
                        $releasedCount = $inventoryService->releaseSeatsByOrder(
                            $order->id,
                            $order->status === Order::STATUS_PAYMENT_FAILED ? 'payment_failed_timeout' : 'reservation_expired'
                        );

                        // Mark order as expired (if it wasn't already failed)
                        if ($order->status === Order::STATUS_RESERVED) {
                            $order->update([
                                'status' => Order::STATUS_EXPIRED,
                                'cancelled_at' => now(),
                            ]);
                            $orderStatus = Order::STATUS_EXPIRED;
                        } else {
                            $orderStatus = $order->status;
                        }

                        // Update associated tickets
                        $orderTickets = $order->tickets()
                            ->whereIn('status', ['processing', 'pending_payment', 'payment_failed'])
                            ->get();

                        foreach ($orderTickets as $ticket) {
                            // If order is being expired due to TTL, mark ticket as expired
                            // If order is payment_failed, mark ticket as payment_failed (it already is, but ensure consistency)
                            $newTicketStatus = $orderStatus === Order::STATUS_EXPIRED ? 'expired' : 'payment_failed';
                            
                            if ($ticket->status !== $newTicketStatus) {
                                $ticket->update(['status' => $newTicketStatus]);
                            }

                            $expiredTickets[] = [
                                'id' => $ticket->id,
                                'screening_id' => $ticket->screening_id,
                                'seat_id' => $ticket->seat_id,
                                'status' => $newTicketStatus,
                                'order_id' => $order->id,
                            ];
                        }

                        $clearedOrders[] = [
                            'id' => $order->id,
                            'order_number' => $order->order_number,
                            'status' => $orderStatus,
                            'seats_released' => $releasedCount,
                        ];
                    }

                    DB::commit();

                    Log::info("Cleanup completed (seat inventory mode)", [
                        'cleared_orders' => count($clearedOrders),
                        'expired_tickets' => count($expiredTickets),
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => "Cleanup completed: " . count($clearedOrders) . " order(s) cleared, "
                                   . count($expiredTickets) . " ticket(s) marked expired.",
                        'cleared_orders_count' => count($clearedOrders),
                        'expired_tickets_count' => count($expiredTickets),
                        'cleared_orders' => $clearedOrders,
                        'expired_tickets' => $expiredTickets,
                    ]);

                } else {
                    // ====================================================================
                    // Legacy cleanup: Delete incomplete tickets
                    // ====================================================================
                    $ticketQuery = Ticket::whereIn('status', ['pending_payment', 'processing', 'payment_failed']);

                    if ($validated['screening_id'] ?? null) {
                        $ticketQuery->where('screening_id', $validated['screening_id']);
                    }

                    if ($validated['seat_id'] ?? null) {
                        $ticketQuery->where('seat_id', $validated['seat_id']);
                    }

                    if ($validated['hours'] ?? null) {
                        $threshold = now()->subHours($validated['hours']);
                        $ticketQuery->where('created_at', '<', $threshold);
                    }

                    $incompleteTickets = $ticketQuery->get();

                    if ($incompleteTickets->isEmpty()) {
                        DB::commit();
                        
                        Log::info("Cleanup: No incomplete tickets found");
                        return response()->json([
                            'success' => true,
                            'message' => 'No incomplete tickets to clean.',
                            'deleted_count' => 0,
                        ]);
                    }

                    $deletedTickets = [];
                    
                    foreach ($incompleteTickets as $ticket) {
                        Log::info("Cleanup: Deleting ticket", [
                            'ticket_id' => $ticket->id,
                            'screening_id' => $ticket->screening_id,
                            'seat_id' => $ticket->seat_id,
                        ]);

                        $deletedTickets[] = [
                            'id' => $ticket->id,
                            'screening_id' => $ticket->screening_id,
                            'seat_id' => $ticket->seat_id,
                            'status' => $ticket->status,
                        ];

                        $ticket->delete();
                    }

                    DB::commit();

                    Log::info("Cleanup completed (legacy mode)", [
                        'deleted_count' => count($deletedTickets),
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => "Deleted " . count($deletedTickets) . " incomplete ticket(s).",
                        'deleted_count' => count($deletedTickets),
                        'deleted_tickets' => $deletedTickets,
                    ]);
                }

            } catch (QueryException $e) {
                DB::rollBack();
                Log::error("Database error during cleanup", ['code' => $e->getCode()]);
                throw $e;
            }

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
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

    /**
     * Helper: Build detailed failed seats response for inventory reservation errors
     * 
     * @param array $failed [seat_id => reason]
     * @return array
     */
    private function buildFailedSeatsResponse(array $failed): array
    {
        $failedSeats = [];
        foreach ($failed as $seatId => $reason) {
            $failedSeats[] = [
                'seat_id' => $seatId,
                'reason' => $reason,
                'error_code' => match($reason) {
                    'Sold' => 'SEAT_SOLD',
                    'Reserved by someone else' => 'SEAT_RESERVED',
                    default => 'SEAT_UNAVAILABLE'
                },
            ];
        }
        return $failedSeats;
    }
}
