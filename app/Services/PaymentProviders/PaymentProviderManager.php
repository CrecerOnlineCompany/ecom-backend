<?php

namespace App\Services\PaymentProviders;

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
     * Iniciar pago
     */
    public function initiatePayment(Ticket $ticket, int $providerId, array $additionalData = []): array
    {
        $provider = PaymentProvider::findOrFail($providerId);
        
        if (!$provider->is_active) {
            throw new \Exception("Payment provider is inactive");
        }
        
        // Crear registro de pago
        $paymentTicket = PaymentProviderTicket::create([
            'ticket_id' => $ticket->id,
            'payment_provider_id' => $provider->id,
            'status' => 'pending',
        ]);
        
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

        // Crear registro de pago
        $paymentTicket = PaymentProviderTicket::create([
            'ticket_id' => $ticket->id,
            'payment_provider_id' => $provider->id,
            'status' => 'pending',
        ]);

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
     */
    protected function getHandlerForMethod(PaymentProvider $provider, string $paymentMethod): PaymentProviderHandler
    {
        return match($paymentMethod) {
            'qr' => new MercadoPagoQrHandler($provider),
            'terminal' => new MercadoPagoPointHandler($provider),
            default => $this->getHandler($provider), // redirect o manual
        };
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
