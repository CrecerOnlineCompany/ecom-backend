<?php

namespace App\Services\PaymentProviders;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\PaymentProviderTicket;
use App\Models\Ticket;
use App\Services\PaymentProviders\Handlers\MercadoPagoHandler;
use App\Services\PaymentProviders\Handlers\MercadoPagoQrHandler;
use App\Services\PaymentProviders\Handlers\MercadoPagoPointHandler;
use App\Services\PaymentProviders\Handlers\PayPalHandler;
use App\Services\PaymentProviders\Handlers\CashHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentProviderManager
{
    protected array $handlers = [
        'mercado_pago' => MercadoPagoHandler::class,
        'mercado_pago_qr' => MercadoPagoQrHandler::class,
        'mercado_pago_terminal' => MercadoPagoPointHandler::class,
        'paypal' => PayPalHandler::class,
        'cash' => CashHandler::class,
    ];

    /**
     * Obtener handler para un payment provider
     */
    public function getHandler(PaymentProvider $provider): PaymentProviderHandler
    {
        $handlerClass = $this->handlers[$provider->name] ?? null;
        
        if (!$handlerClass) {
            throw new \Exception("No handler found for provider: {$provider->name}");
        }
        
        return new $handlerClass($provider);
    }

    /**
     * Obtener todos los providers activos
     */
    public function getActiveProviders(): array
    {
        return PaymentProvider::where('is_active', true)
            ->get()
            ->map(function ($provider) {
                $handler = $this->getHandler($provider);
                return $handler->getInfo();
            })
            ->toArray();
    }

    /**
     * Obtener información de un provider específico
     */
    public function getProviderInfo(int $providerId): ?array
    {
        $provider = PaymentProvider::find($providerId);
        
        if (!$provider || !$provider->is_active) {
            return null;
        }
        
        $handler = $this->getHandler($provider);
        return $handler->getInfo();
    }

    /**
     * Iniciar pago para una Order (Order-first flow)
     * 
     * En el flujo order-first:
     * - La orden ya está creada con asientos reservados
     * - Se crea una PaymentProviderTicket vinculada a la orden (sin ticket individual)
     * - El webhook  creará los tickets cuando confirme el pago
     * 
     * @param \App\Models\Order $order
     * @param int $providerId
     * @param array $additionalData Ej: ['seat_ids' => [...], 'seat_count' => int, 'total_price' => decimal]
     * @return array Resultado con success, redirect_url, transaction_id, etc
     */
    public function initiateOrderPayment(\App\Models\Order $order, int $providerId, array $additionalData = []): array
    {
        $provider = PaymentProvider::findOrFail($providerId);
        
        if (!$provider->is_active) {
            throw new \Exception("Payment provider is inactive");
        }

        // PASO 0: Obtener o generar idempotency_key
        // Buscar si existe un payment_provider_ticket anterior en esta orden
        $previousPayment = $order->paymentProviderTickets()
            ->latest()
            ->first();
        
        $idempotencyKey = $previousPayment?->response_data['idempotency_key'] ?? \Illuminate\Support\Str::uuid()->toString();
        
        Log::info('Order payment: idempotency_key', [
            'order_id' => $order->id,
            'idempotency_key' => $idempotencyKey,
            'reusing_from_previous' => $previousPayment ? true : false,
        ]);

        // Crear PaymentProviderTicket vinculada a la orden (sin ticket individual pre-existente)
        $paymentTicketData = [
            'order_id' => $order->id,
            'payment_provider_id' => $provider->id,
            'status' => 'pending',
            'response_data' => [
                'idempotency_key' => $idempotencyKey,
                'created_at' => now()->toIso8601String(),
            ],
            // No ticket_id en este flujo order-first
        ];
        
        $paymentTicket = PaymentProviderTicket::create($paymentTicketData);
        
        Log::info('Order payment initiated', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_ticket_id' => $paymentTicket->id,
            'provider_id' => $providerId,
            'payment_method' => $additionalData['payment_method'] ?? 'redirect',
            'idempotency_key' => $idempotencyKey,
        ]);

        try {
            // PASO 1: Seleccionar handler según el método de pago
            $paymentMethod = $additionalData['payment_method'] ?? 'redirect';
            $handler = $this->getHandlerForMethod($provider, $paymentMethod);
            
            // PASO 2: Enriquecer data con información de la orden
            $enrichedData = array_merge($additionalData, [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer_email' => $order->customer_email,
                'customer_name' => $order->customer_name,
                'total_amount' => $order->total_amount,
                'total_price' => $order->total_amount,
                'idempotency_key' => $idempotencyKey,
            ]);
            
            // PASO 3: ORDER-FIRST: processPayment recibe PaymentProviderTicket con order_id (sin ticket_id)
            // El handler obtendrá screening desde la orden
            $result = $handler->processPayment($paymentTicket, $enrichedData);

            if (!($result['success'] ?? false)) {
                Log::warning('Order payment initiation failed', [
                    'order_id' => $order->id,
                    'payment_method' => $paymentMethod,
                    'result' => $result,
                ]);
                $paymentTicket->delete();
                return $result;
            }

            Log::info('Order payment initiated successfully', [
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'payment_ticket_id' => $paymentTicket->id,
                'transaction_id' => $result['transaction_id'] ?? 'N/A',
            ]);

            return array_merge($result, [
                'payment_ticket_id' => $paymentTicket->id,
            ]);

        } catch (\Exception $e) {
            $paymentTicket->delete();
            Log::error('Error initiating order payment', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Iniciar pago
     */
    public function initiatePayment(Ticket $ticket, int $providerId, array $additionalData = []): array
    {
        $provider = PaymentProvider::findOrFail($providerId);
        
        if (!$provider->is_active) {
            throw new \Exception("Payment provider is inactive");
        }
        
        // Crear registro de pago (con order_id si existe en additionalData)
        $paymentTicketData = [
            'ticket_id' => $ticket->id,
            'payment_provider_id' => $provider->id,
            'status' => 'pending',
        ];
        
        // Agregar order_id si existe en additionalData
        if (isset($additionalData['order_id'])) {
            $paymentTicketData['order_id'] = $additionalData['order_id'];
        }
        
        $paymentTicket = PaymentProviderTicket::create($paymentTicketData);
        
        // Procesar pago
        $handler = $this->getHandler($provider);
        $result = $handler->processPayment($paymentTicket, $additionalData);
        
        if (!$result['success']) {
            $paymentTicket->delete();
            return $result;
        }
        
        return array_merge($result, [
            'payment_ticket_id' => $paymentTicket->id,
        ]);
    }

    /**
     * Procesar webhook genérico
     */
    public function processWebhook(string $webhookHash, Request $request): bool
    {
        $provider = PaymentProvider::where('webhook_secret', $webhookHash)->firstOrFail();
        
        $handler = $this->getHandler($provider);
        return $handler->handleWebhook($request);
    }

    /**
     * Refund de un pago
     */
    public function refundPayment(int $paymentTicketId, string $reason = ''): bool
    {
        $paymentTicket = PaymentProviderTicket::findOrFail($paymentTicketId);
        $provider = $paymentTicket->paymentProvider;
        
        $handler = $this->getHandler($provider);
        return $handler->refund($paymentTicket, $reason);
    }

    /**
     * Obtener estado de un pago
     */
    public function getPaymentStatus(int $paymentTicketId): string
    {
        $paymentTicket = PaymentProviderTicket::findOrFail($paymentTicketId);
        $provider = $paymentTicket->paymentProvider;
        
        $handler = $this->getHandler($provider);
        return $handler->getPaymentStatus($paymentTicket);
    }

    /**
     * Registrar handler personalizado
     */
    public function registerHandler(string $name, string $handlerClass): void
    {
        if (!class_exists($handlerClass) || !is_subclass_of($handlerClass, PaymentProviderHandler::class)) {
            throw new \Exception("Invalid handler class");
        }

        $this->handlers[$name] = $handlerClass;
    }

    /**
     * Iniciar pago con método específico (redirect, qr, terminal)
     */
    public function initiatePaymentWithMethod(
        Ticket $ticket,
        int $providerId,
        string $paymentMethod = 'redirect',
        array $additionalData = []
    ): array {
        $provider = PaymentProvider::findOrFail($providerId);

        if (!$provider->is_active) {
            throw new \Exception("Payment provider is inactive");
        }

        // Verificar que el proveedor soporte este método
        $availableMethods = $provider->getAvailableMethods();
        if (!in_array($paymentMethod, $availableMethods)) {
            throw new \Exception("Payment method '{$paymentMethod}' not supported by this provider");
        }

        // Crear registro de pago (con order_id si existe)
        $paymentTicketData = [
            'ticket_id' => $ticket->id,
            'payment_provider_id' => $provider->id,
            'status' => 'pending',
        ];
        
        // Agregar order_id si existe en additionalData
        if (isset($additionalData['order_id'])) {
            $paymentTicketData['order_id'] = $additionalData['order_id'];
        }
        
        $paymentTicket = PaymentProviderTicket::create($paymentTicketData);

        try {
            // Obtener el handler correcto según el método
            $handler = $this->getHandlerForMethod($provider, $paymentMethod);
            $result = $handler->processPayment($paymentTicket, $additionalData);

            if (!$result['success'] ?? false) {
                $paymentTicket->delete();
                return $result;
            }

            return array_merge($result, [
                'payment_ticket_id' => $paymentTicket->id,
                'payment_method' => $paymentMethod,
            ]);

        } catch (\Exception $e) {
            $paymentTicket->delete();
            Log::error('PaymentProviderManager: Error initiating payment', [
                'error' => $e->getMessage(),
                'provider_id' => $providerId,
                'method' => $paymentMethod,
            ]);
            throw $e;
        }
    }

    /**
     * Obtener el handler correcto para un método específico
     * 
     * Mapeo:
     * - redirect -> mercado_pago (o provider de redirect)
     * - qr -> mercado_pago_qr
     * - terminal -> mercado_pago_terminal (Point)
     */
    protected function getHandlerForMethod(PaymentProvider $provider, string $paymentMethod = 'redirect'): PaymentProviderHandler
    {
        // Si el provider es específico (mercado_pago_qr, etc), usar directamente
        if (in_array($provider->name, ['mercado_pago_qr', 'mercado_pago_terminal', 'paypal', 'cash'])) {
            return $this->getHandler($provider);
        }

        // Si el provider es genérico (mercado_pago), seleccionar según el method
        if ($provider->name === 'mercado_pago') {
            $providerName = match($paymentMethod) {
                'qr' => 'mercado_pago_qr',
                'terminal' => 'mercado_pago_terminal',
                default => 'mercado_pago', // redirect
            };

            // Primero, intentar buscar un provider específico en BD
            $specificProvider = PaymentProvider::where('name', $providerName)
                ->where('is_active', true)
                ->first();

            if ($specificProvider) {
                Log::debug("Using specific payment provider from DB", [
                    'original_provider' => $provider->name,
                    'specific_provider' => $providerName,
                    'payment_method' => $paymentMethod,
                ]);
                return $this->getHandler($specificProvider);
            }

            // Si no existe provider específico en BD, instanciar el handler directamente
            Log::debug("No specific provider in DB, using handler directly", [
                'original_provider' => $provider->name,
                'requested_handler' => $providerName,
                'payment_method' => $paymentMethod,
            ]);

            return match($paymentMethod) {
                'qr' => new MercadoPagoQrHandler($provider),
                'terminal' => new MercadoPagoPointHandler($provider),
                default => new MercadoPagoHandler($provider),
            };
        }

        // Para otros providers, usar el handler default
        return $this->getHandler($provider);
    }

    /**
     * Iniciar pago por QR
     */
    public function initiateQrPayment(Ticket $ticket, int $providerId, array $additionalData = []): array
    {
        return $this->initiatePaymentWithMethod($ticket, $providerId, 'qr', $additionalData);
    }

    /**
     * Iniciar pago por Terminal Smart
     */
    public function initiateTerminalPayment(Ticket $ticket, int $providerId, array $additionalData = []): array
    {
        return $this->initiatePaymentWithMethod($ticket, $providerId, 'terminal', $additionalData);
    }

    /**
     * Obtener proveedores con sus métodos disponibles
     */
    public function getProvidersWithMethods(): array
    {
        return PaymentProvider::where('is_active', true)
            ->get()
            ->map(function ($provider) {
                $handler = $this->getHandler($provider);
                $info = $handler->getInfo();
                $info['available_methods'] = $provider->getAvailableMethods();
                $info['is_qr_configured'] = $provider->isQrConfigured();
                $info['is_terminal_configured'] = $provider->isTerminalConfigured();
                return $info;
            })
            ->toArray();
    }

    /**
     * Obtener lista de terminales disponibles (si aplica)
     */
    public function getAvailableTerminals(int $providerId): array
    {
        $provider = PaymentProvider::findOrFail($providerId);

        if (!$provider->supportsTerminal()) {
            return [];
        }

        try {
            $handler = new MercadoPagoPointHandler($provider);
            return $handler->getAvailableTerminals();
        } catch (\Exception $e) {
            Log::error('PaymentProviderManager: Error getting terminals', [
                'error' => $e->getMessage(),
                'provider_id' => $providerId,
            ]);
            return [];
        }
    }
}
