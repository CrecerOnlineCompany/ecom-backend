<?php

namespace App\Services\PaymentProviders;

use App\Models\PaymentProvider;
use App\Models\PaymentProviderTicket;
use Illuminate\Http\Request;

abstract class PaymentProviderHandler
{
    protected PaymentProvider $provider;
    protected PaymentProviderTicket $paymentTicket;

    public function __construct(PaymentProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Obtener información del provider
     */
    public function getInfo(): array
    {
        return [
            'id' => $this->provider->id,
            'name' => $this->provider->name,
            'display_name' => $this->provider->display_name,
            'description' => $this->provider->description,
            'icon_url' => $this->provider->icon_url,
            'requires_redirect' => $this->provider->requires_redirect,
            'supports_webhook' => $this->provider->supports_webhook,
            'webhook_url' => $this->provider->supports_webhook ? $this->provider->getWebhookUrl() : null,
        ];
    }

    /**
     * Procesar pago inicial
     * Debe retornar array con redirect_url (si aplica) o success flag
     */
    abstract public function processPayment(PaymentProviderTicket $paymentTicket, array $additionalData = []): array;

    /**
     * Validar y procesar webhook
     */
    abstract public function handleWebhook(Request $request): bool;

    /**
     * Validar credenciales/configuración del provider
     */
    abstract public function validateConfiguration(): bool;

    /**
     * Reembolsar pago
     */
    abstract public function refund(PaymentProviderTicket $paymentTicket, string $reason = ''): bool;

    /**
     * Obtener estado actual del pago
     */
    abstract public function getPaymentStatus(PaymentProviderTicket $paymentTicket): string;
}
