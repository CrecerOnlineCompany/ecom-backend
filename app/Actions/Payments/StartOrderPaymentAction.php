<?php

namespace App\Actions\Payments;

use App\Models\Order;
use App\Models\Screening;
use App\Services\SeatInventoryService;
use App\Services\PaymentProviders\PaymentProviderManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Inicia un pago order-first (cuando seat_inventory feature está activado)
 * 
 * Flow:
 * 1. Verificar screening existe
 * 2. Crear Order (reservation status)
 * 3. Asegurar que screening_seats existen en inventory
 * 4. Reservar asientos (atómicamente)
 * 5. Iniciar pago vinculado a Order (sin crear tickets aún)
 * 
 * Retorna array con:
 * - success: bool
 * - order_id, order_number
 * - transaction_id, redirect_url, etc del payment
 * 
 * Los tickets se crean SOLO en webhook cuando payment es aprobado
 */
class StartOrderPaymentAction
{
    protected PaymentProviderManager $paymentManager;
    protected SeatInventoryService $inventoryService;

    public function __construct(
        PaymentProviderManager $paymentManager,
        SeatInventoryService $inventoryService
    ) {
        $this->paymentManager = $paymentManager;
        $this->inventoryService = $inventoryService;
    }

    /**
     * Manejar pago single (1 asiento)
     */
    public function handleSingle(array $validated, Request $request): array
    {
        return DB::transaction(function () use ($validated, $request) {
            return $this->processPayment($validated, $request, false);
        });
    }

    /**
     * Manejar pago batch (múltiples asientos)
     */
    public function handleBatch(array $validated, Request $request): array
    {
        return DB::transaction(function () use ($validated, $request) {
            return $this->processPayment($validated, $request, true);
        });
    }

    /**
     * Procesar el pago (single o batch)
     */
    private function processPayment(array $validated, Request $request, bool $isBatch): array
    {
        try {
            // Step 1: Obtener screening
            $screening = Screening::findOrFail($validated['screening_id']);
            
            // Normalizar seat_ids (batch = array, single = 1 elemento)
            $seatIds = $isBatch ? $validated['seat_ids'] : [$validated['seat_id']];
            
            Log::info("StartOrderPaymentAction: Processing payment", [
                'screening_id' => $screening->id,
                'seat_count' => count($seatIds),
                'is_batch' => $isBatch,
            ]);

            // Step 2: Crear Order (cabecera)
            $totalPrice = count($seatIds) * $screening->price;
            
            $order = Order::create([
                'uuid' => Str::uuid(),
                'order_number' => \App\Services\OrderNumberGenerator::generate(),
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'user_id' => 1, // user_id fijo
                'screening_id' => $screening->id,
                'total_amount' => $totalPrice,
                'currency' => 'ARS',
                'status' => Order::STATUS_RESERVED,
                'purchase_device' => 'web',
                'ip_address' => $request->ip(),
                'reserved_until' => now()->addMinutes(6), // 6 min TTL
            ]);

            Log::info("Order created in StartOrderPaymentAction", [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            // Step 3: Asegurar que existen screening_seats en inventory
            try {
                $this->inventoryService->ensureScreeningSeats($screening->id);
            } catch (\Exception $e) {
                Log::error("Failed to ensure screening seats", ['error' => $e->getMessage()]);
                // Rollback via transaction
                throw $e;
            }

            // Step 4: Reservar asientos (atómicamente)
            $reservationResult = $this->inventoryService->reserveSeats(
                screening_id: $screening->id,
                seat_ids: $seatIds,
                holder_type: 'user',
                holder_id: '1', // user_id=1 fijo
                ttl_seconds: 360, // 6 minutos
                order_id: $order->id
            );

            if (!$reservationResult['success']) {
                Log::warning("Seat reservation failed in StartOrderPaymentAction", [
                    'order_id' => $order->id,
                    'failed_seats' => $reservationResult['failed'],
                ]);
                
                // Cancelar order (transaction hará rollback automático)
                throw new \Exception(
                    'Seat reservation failed: ' . json_encode($reservationResult['failed'])
                );
            }

            Log::info("Seats reserved successfully", [
                'order_id' => $order->id,
                'reserved_count' => count($reservationResult['reserved']),
            ]);

            // Step 5: Iniciar pago vinculado a Order (sin crear tickets)
            $additionalData = [
                'total_price' => $totalPrice,
                'seat_count' => count($seatIds),
                'seat_ids' => $seatIds,
            ];

            try {
                $paymentResult = $this->paymentManager->initiateOrderPayment(
                    $order,
                    $validated['payment_provider_id'],
                    $additionalData
                );

                if (!$paymentResult['success']) {
                    Log::warning("Payment initiation failed", [
                        'order_id' => $order->id,
                        'message' => $paymentResult['message'] ?? 'Unknown',
                    ]);
                    throw new \Exception(
                        'Payment initiation failed: ' . ($paymentResult['message'] ?? 'Unknown')
                    );
                }

                Log::info("Payment initiated successfully in StartOrderPaymentAction", [
                    'order_id' => $order->id,
                    'transaction_id' => $paymentResult['transaction_id'] ?? 'N/A',
                ]);

                // Step 6: Retornar respuesta
                return [
                    'success' => true,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'seats_count' => count($seatIds),
                    'total_price' => $totalPrice,
                    'transaction_id' => $paymentResult['transaction_id'] ?? null,
                    'payment_ticket_id' => $paymentResult['payment_ticket_id'] ?? null,
                    'redirect_url' => $paymentResult['redirect_url'] ?? null,
                    'requires_redirect' => $paymentResult['requires_redirect'] ?? false,
                    'message' => $paymentResult['message'] ?? 'Payment initiated',
                ];

            } catch (\Exception $e) {
                Log::error("Error initiating payment", ['error' => $e->getMessage()]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error("Error in StartOrderPaymentAction", [
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
}
