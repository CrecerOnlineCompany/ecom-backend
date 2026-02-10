<?php

namespace App\Services\PaymentProviders;

use App\Models\PaymentProvider;
use App\Models\PaymentProviderTicket;
use App\Models\Ticket;
use App\Services\PaymentProviders\Handlers\MercadoPagoHandler;
use App\Services\PaymentProviders\Handlers\PayPalHandler;
use App\Services\PaymentProviders\Handlers\CashHandler;
use Illuminate\Http\Request;

class PaymentProviderManager
{
    protected array $handlers = [
        'mercado_pago' => MercadoPagoHandler::class,
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
}
