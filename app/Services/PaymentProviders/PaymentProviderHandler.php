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
        $config = is_array($this->provider->config) ? $this->provider->config : [];
        $toBool = static function ($value): bool {
            if (is_bool($value)) {
                return $value;
            }

            if (is_int($value) || is_float($value)) {
                return $value === 1;
            }

            if (is_string($value)) {
                $normalized = strtolower(trim($value));
                return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
            }

            return false;
        };

        $qrEnabled = $toBool($config['supports_qr'] ?? false) || $this->provider->supportsQr();
        $terminalEnabled = $toBool($config['supports_terminal'] ?? false) || $this->provider->supportsTerminal();
        $qrType = $config['qr_type'] ?? null;
        $qrType = is_string($qrType) && $qrType !== '' ? $qrType : null;

        return [
            'id' => $this->provider->id,
            'name' => $this->provider->name,
            'display_name' => $this->provider->display_name,
            'description' => $this->provider->description,
            'icon_url' => $this->provider->icon_url,
            'requires_redirect' => (bool) $this->provider->requires_redirect,
            'supports_webhook' => (bool) $this->provider->supports_webhook,
            'is_active' => (bool) $this->provider->is_active,
            'webhook_url' => $this->provider->supports_webhook ? $this->provider->getWebhookUrl() : null,
            'qr' => [
                'enabled' => $qrEnabled,
                'configured' => $this->provider->isQrConfigured(),
                'type' => $qrType,
                'store_id' => $config['store_id'] ?? $config['pos_id'] ?? null,
            ],
            'smartPoint' => [
                'enabled' => $terminalEnabled,
                'configured' => $this->provider->isTerminalConfigured(),
                'terminal_id' => $config['terminal_id'] ?? null,
                'store_id' => $config['store_id'] ?? null,
                'auto_send' => $toBool($config['auto_send'] ?? false),
            ],
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
