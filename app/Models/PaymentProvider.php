<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
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
     * Bootstrap the model
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            Log::info('==== MODEL EVENTO: SAVING ====');
            
            // Si es Mercado Pago, necesitamos reconstruir el config desde el request
            if ($model->name === 'mercado_pago' && request()->has('config')) {
                Log::info('✓ Mercado Pago en SAVING - reconstruyendo config desde request');
                
                $requestConfig = request('config');
                
                if (is_array($requestConfig)) {
                    Log::info('✓ Config del request es ARRAY:', $requestConfig);
                    
                    // Construir el config completo con lógica correcta
                    $config = [];
                    foreach ($requestConfig as $key => $value) {
                        if ($value === '1' || $value === 'on' || $value === true) {
                            $config[$key] = true;
                        } elseif ($value === '0' || $value === '' || $value === false || $value === null) {
                            $config[$key] = false;
                        } else {
                            $config[$key] = $value;
                        }
                    }
                    
                    // Construir supported_methods
                    $supportedMethods = [];
                    if ($config['supports_redirect'] ?? false) $supportedMethods[] = 'redirect';
                    if ($config['supports_qr'] ?? false) $supportedMethods[] = 'qr';
                    if ($config['supports_terminal'] ?? false) $supportedMethods[] = 'terminal';
                    
                    if (!empty($supportedMethods)) {
                        $config['supported_methods'] = $supportedMethods;
                    }
                    
                    Log::info('✓ Config reconstruido en SAVING:', $config);
                    
                    // ESTABLECER directamente en attributes (sin pasar por el mutator)
                    $model->attributes['config'] = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                    Log::info('✓ Config guardado como JSON en attributes');
                }
            }
            
            Log::info('Atributos finales a guardar:', [
                'config_tipo' => gettype($model->attributes['config'] ?? null),
                'config_preview' => substr(json_encode($model->attributes['config'] ?? null), 0, 150)
            ]);
        });

        static::saved(function ($model) {
            Log::info('==== MODEL EVENTO: SAVED ====');
            Log::info('Config guardado en BD:', ['config' => $model->config]);
        });
    }

    /**
     * Obtener el config como array (cast automático)
     */
    public function getConfigAttribute($value)
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }
        return is_array($value) ? $value : [];
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

    /**
     * Obtener métodos de pago soportados por este proveedor
     */
    public function getPaymentMethods(): array
    {
        $config = $this->config ?? [];
        
        // Si los campos booleanos están definidos, usar esos
        if (isset($config['supports_redirect']) || isset($config['supports_qr']) || isset($config['supports_terminal'])) {
            $methods = [];
            if ($config['supports_redirect'] ?? false) $methods[] = 'redirect';
            if ($config['supports_qr'] ?? false) $methods[] = 'qr';
            if ($config['supports_terminal'] ?? false) $methods[] = 'terminal';
            return $methods;
        }
        
        // Si no, usar el array de supported_methods o valores por defecto
        return $this->getConfig('supported_methods', $this->getDefaultPaymentMethods());
    }

    /**
     * Métodos por defecto según el nombre del proveedor
     */
    private function getDefaultPaymentMethods(): array
    {
        return match($this->name) {
            'mercado_pago' => ['redirect', 'qr', 'terminal'],
            'paypal' => ['redirect'],
            'cash' => ['manual'],
            default => [],
        };
    }

    /**
     * Verificar si soporta pago por QR
     */
    public function supportsQr(): bool
    {
        return in_array('qr', $this->getPaymentMethods());
    }

    /**
     * Verificar si soporta terminal smart
     */
    public function supportsTerminal(): bool
    {
        return in_array('terminal', $this->getPaymentMethods());
    }

    /**
     * Verificar si soporta redirección
     */
    public function supportsRedirect(): bool
    {
        return in_array('redirect', $this->getPaymentMethods());
    }

    /**
     * Obtener métodos activos/disponibles (con configuración válida)
     */
    public function getAvailableMethods(): array
    {
        $methods = [];
        $supported = $this->getPaymentMethods();

        if (in_array('redirect', $supported) && $this->requires_redirect) {
            $methods[] = 'redirect';
        }

        if (in_array('qr', $supported) && $this->isQrConfigured()) {
            $methods[] = 'qr';
        }

        if (in_array('terminal', $supported) && $this->isTerminalConfigured()) {
            $methods[] = 'terminal';
        }

        if (in_array('manual', $supported)) {
            $methods[] = 'manual';
        }

        return $methods;
    }

    /**
     * Verificar si QR está configurado correctamente
     */
    public function isQrConfigured(): bool
    {
        return !empty($this->getConfig('access_token')) &&
               !empty($this->getConfig('store_id', $this->getConfig('pos_id')));
    }

    /**
     * Verificar si Terminal está configurada correctamente
     */
    public function isTerminalConfigured(): bool
    {
        return !empty($this->getConfig('access_token')) &&
               !empty($this->getConfig('store_id')) &&
               !empty($this->getConfig('terminal_id'));
    }
}
