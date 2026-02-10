<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Screening;
use App\Models\PaymentProvider;
use App\Services\PaymentProviders\PaymentProviderManager;
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
                    $isBooked = $screening->tickets()
                        ->where('seat_id', $seatId)
                        ->whereIn('status', ['confirmed', 'pending_payment', 'processing'])
                        ->exists();
                    
                    if ($isBooked) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "Un asiento ya está reservado o en proceso de compra. Por favor intenta de nuevo.",
                            'error_code' => 'SEAT_UNAVAILABLE',
                        ], 422);
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
                        'customer_phone' => $validated['customer_phone'],
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
