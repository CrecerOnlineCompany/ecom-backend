<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PaymentProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'icon_url',
        'is_active',
        'config',
        'redirect_url',
        'webhook_path',
        'webhook_secret',
        'requires_redirect',
        'supports_webhook',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'requires_redirect' => 'boolean',
        'supports_webhook' => 'boolean',
    ];

    /**
     * Procesar config: si llega como string JSON, convertir a array
     */
    public function setConfigAttribute($value)
    {
        if (is_string($value)) {
            $this->attributes['config'] = json_encode(json_decode($value, true));
        } else if (is_array($value)) {
            $this->attributes['config'] = json_encode($value);
        } else {
            $this->attributes['config'] = $value;
        }
    }

    /**
     * Relación con los tickets de pago
     */
    public function paymentTickets(): HasMany
    {
        return $this->hasMany(PaymentProviderTicket::class);
    }

    /**
     * Generar webhook secret único
     */
    public static function generateWebhookSecret(): string
    {
        return Str::random(32);
    }

    /**
     * Obtener la URL completa del webhook
     */
    public function getWebhookUrl(): string
    {
        return route('api.webhook.payment', ['hash' => $this->webhook_secret]);
    }

    /**
     * Obtener configuración de forma segura
     */
    public function getConfig(string $key = null, $default = null)
    {
        $config = $this->config ?? [];
        
        if ($key === null) {
            return $config;
        }

        return $config[$key] ?? $default;
    }

    /**
     * Actualizar configuración
     */
    public function setConfig(string $key, $value): void
    {
        $config = $this->config ?? [];
        $config[$key] = $value;
        $this->config = $config;
    }
}
