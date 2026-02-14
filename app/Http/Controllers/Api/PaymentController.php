<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Screening;
use App\Models\PaymentProviderTicket;
use App\Services\PaymentProviders\PaymentProviderManager;
use App\Services\PaymentMethods\PaymentMethodService;
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
            
            Log::info("Batch payment request from user: {$userId}, seats: " . count($validated['seat_ids']) . ", screening: {$validated['screening_id']}");

            DB::beginTransaction();

            try {
                $screening = Screening::find($validated['screening_id']);
                $tickets = [];
                $totalPrice = 0;
                
                foreach ($validated['seat_ids'] as $seatId) {
                    // Buscar tickets confirmados (vendidos) para este asiento
                    $confirmedTicket = $screening->tickets()
                        ->where('seat_id', $seatId)
                        ->where('status', 'confirmed')
                        ->first();
                    
                    if ($confirmedTicket) {
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

                foreach ($validated['seat_ids'] as $seatId) {
                    $ticket = Ticket::create([
                        'screening_id' => $validated['screening_id'],
                        'seat_id' => $seatId,
                        'user_id' => $userId,
                        'ticket_number' => 'TKT-' . time() . '-' . uniqid(),
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
                }

                Log::info("Created " . count($tickets) . " tickets for batch payment, total price: {$totalPrice}");

                $firstTicket = Ticket::find($tickets[0]['id']);
                $additionalData = $validated['additional_data'] ?? [];
                $additionalData['total_price'] = $totalPrice;
                $additionalData['seat_count'] = count($validated['seat_ids']);
                $additionalData['all_ticket_ids'] = array_column($tickets, 'id');
                
                $result = $this->paymentManager->initiatePayment(
                    $firstTicket,
                    $validated['payment_provider_id'],
                    $additionalData
                );

                if (!$result['success']) {
                    foreach ($tickets as $t) {
                        Ticket::find($t['id'])->update(['status' => 'payment_failed']);
                    }
                    DB::rollBack();
                    
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'] ?? 'Payment processing failed',
                    ], 422);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'tickets' => $tickets,
                    'total_price' => $totalPrice,
                    'tickets_count' => count($tickets),
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'redirect_url' => $result['redirect_url'] ?? null,
                    'requires_redirect' => $result['requires_redirect'] ?? false,
                    'message' => $result['message'] ?? 'Batch payment initiated successfully',
                ]);

            } catch (QueryException $e) {
                DB::rollBack();
                Log::error("Database error during batch payment: " . $e->getMessage());
                
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
            Log::error("Batch payment processing error: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
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
            
            Log::info("Payment request from user: {$userId}, email: {$validated['customer_email']}, seat: {$validated['seat_id']}, screening: {$validated['screening_id']}");

            DB::beginTransaction();

            try {
                $screening = Screening::find($validated['screening_id']);
                
                $isBooked = $screening->tickets()
                    ->where('seat_id', $validated['seat_id'])
                    ->whereIn('status', ['confirmed', 'pending_payment', 'processing'])
                    ->exists();
                
                if ($isBooked) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Este asiento ya está reservado o en proceso de compra. Por favor selecciona otro asiento.',
                        'error_code' => 'SEAT_UNAVAILABLE',
                    ], 422);
                }

                $ticket = Ticket::create([
                    'screening_id' => $validated['screening_id'],
                    'seat_id' => $validated['seat_id'],
                    'user_id' => $userId,
                    'ticket_number' => 'TKT-' . time() . '-' . uniqid(),
                    'price' => $screening->price,
                    'status' => 'pending_payment',
                ]);

                Log::info("Created ticket: {$ticket->ticket_number} for payment processing");

                $result = $this->paymentManager->initiatePayment(
                    $ticket,
                    $validated['payment_provider_id'],
                    $validated['additional_data'] ?? []
                );

                if (!$result['success']) {
                    $ticket->update(['status' => 'payment_failed']);
                    DB::rollBack();
                    
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'] ?? 'Payment processing failed',
                    ], 422);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'ticket_number' => $ticket->ticket_number,
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'redirect_url' => $result['redirect_url'] ?? null,
                    'requires_redirect' => $result['requires_redirect'] ?? false,
                    'message' => $result['message'] ?? 'Payment initiated successfully',
                ]);

            } catch (QueryException $e) {
                DB::rollBack();
                Log::error("Database error during payment: " . $e->getMessage());
                
                if ($e->getCode() == 23000) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Este asiento ya está siendo procesado. Por favor intenta otro asiento o espera unos momentos.',
                        'error_code' => 'DUPLICATE_BOOKING',
                    ], 422);
                }
                
                throw $e;
            }
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error("Payment processing error: " . $e->getMessage());
            
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
                        'ticket_number' => 'TKT-' . time() . '-' . uniqid(),
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
                    Log::info("Created QR ticket ID: {$ticket->id}, Number: {$ticket->ticket_number}, Seat: {$seatId}");
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
            
            Log::info("Terminal Payment validated successfully");
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
                Log::info("Creating " . count($validated['seat_ids']) . " terminal tickets");
                foreach ($validated['seat_ids'] as $seatId) {
                    $ticket = Ticket::create([
                        'screening_id' => $validated['screening_id'],
                        'seat_id' => $seatId,
                        'user_id' => $userId,
                        'ticket_number' => 'TKT-' . time() . '-' . uniqid(),
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
                    Log::info("Created terminal ticket ID: {$ticket->id}, Number: {$ticket->ticket_number}, Seat: {$seatId}");
                }
                Log::info("All tickets created, Total Price: {$totalPrice}");

                // Process payment with terminal handler using first ticket
                $firstTicket = Ticket::find($tickets[0]['id']);
                $additionalData = $validated['additional_data'] ?? [];
                $additionalData['total_price'] = $totalPrice;
                $additionalData['seat_count'] = count($validated['seat_ids']);
                $additionalData['all_ticket_ids'] = array_column($tickets, 'id');
                
                Log::info("Initiating terminal payment with PaymentProviderManager");
                Log::info("Additional data: " . json_encode($additionalData));
                
                $result = $this->paymentManager->initiatePaymentWithMethod(
                    $firstTicket,
                    $validated['payment_provider_id'],
                    'terminal',
                    $additionalData
                );
                
                Log::info("Payment manager returned: " . json_encode($result));

                if (!$result['success']) {
                    Log::warning("Terminal payment processing failed: " . ($result['message'] ?? 'Unknown error'));
                    foreach ($tickets as $t) {
                        Ticket::find($t['id'])->update(['status' => 'payment_failed']);
                    }
                    DB::rollBack();
                    
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'] ?? 'Terminal payment processing failed',
                    ], 422);
                }

                DB::commit();
                Log::info("Database transaction committed successfully");
                Log::info("=== TERMINAL PAYMENT PROCESS COMPLETED SUCCESSFULLY ===");

                return response()->json([
                    'success' => true,
                    'tickets' => $tickets,
                    'total_price' => $totalPrice,
                    'tickets_count' => count($tickets),
                    'terminal_id' => $result['terminal_id'] ?? null,
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'payment_ticket_id' => $result['payment_ticket_id'] ?? null,
                    'order_id' => $result['order_id'] ?? null,
                    'requires_polling' => $result['requires_polling'] ?? true,
                    'polling_interval' => $result['polling_interval'] ?? 3000,
                    'message' => $result['message'] ?? 'Terminal payment initiated successfully',
                ]);

            } catch (QueryException $e) {
                DB::rollBack();
                Log::error("Database error during terminal payment: " . $e->getMessage());
                
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
            Log::error("Terminal payment processing error: " . $e->getMessage());
            
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

            // Solo permitir cancelar si está en estado procesando o pendiente
            if (!in_array($paymentTicket->status, ['processing', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => "No se puede cancelar un pago en estado: {$paymentTicket->status}",
                    'error_code' => 'INVALID_STATUS',
                ], 422);
            }

            // Actualizar estado del pago a cancelado
            $paymentTicket->update(['status' => 'cancelled']);

            // Eliminar o cancelar los tickets asociados que estén en pending_payment
            $tickets = Ticket::where('payment_provider_ticket_id', $paymentTicketId)
                ->where('status', 'pending_payment')
                ->get();

            $deletedCount = 0;
            foreach ($tickets as $ticket) {
                $ticket->delete();
                $deletedCount++;
            }

            Log::info('Payment cancelled', [
                'payment_ticket_id' => $paymentTicketId,
                'deleted_tickets' => $deletedCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pago cancelado exitosamente',
                'deleted_tickets' => $deletedCount,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Intento de pago no encontrado',
                'error_code' => 'NOT_FOUND',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error cancelling payment: ' . $e->getMessage());
            
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

            $query = Ticket::whereIn('status', ['pending_payment', 'processing', 'payment_failed']);

            if ($validated['screening_id'] ?? null) {
                $query->where('screening_id', $validated['screening_id']);
            }
            if ($validated['seat_id'] ?? null) {
                $query->where('seat_id', $validated['seat_id']);
            }

            if ($validated['hours'] ?? null) {
                $threshold = now()->subHours($validated['hours']);
                $query->where('created_at', '<', $threshold);
            }

            $tickets = $query->get();

            if ($tickets->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No incomplete tickets found to clean',
                    'deleted_count' => 0,
                ]);
            }

            $count = $tickets->count();
            foreach ($tickets as $ticket) {
                Log::info("Cleanup: Deleting ticket {$ticket->ticket_number} (screening {$ticket->screening_id}, seat {$ticket->seat_id})");
                $ticket->delete();
            }

            Log::info("Cleanup completed: deleted {$count} incomplete tickets");

            return response()->json([
                'success' => true,
                'message' => "Deleted {$count} incomplete ticket(s)",
                'deleted_count' => $count,
                'deleted_tickets' => $tickets->map(fn($t) => [
                    'id' => $t->id,
                    'ticket_number' => $t->ticket_number,
                    'screening_id' => $t->screening_id,
                    'seat_id' => $t->seat_id,
                    'status' => $t->status,
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error("Cleanup error: " . $e->getMessage());
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
