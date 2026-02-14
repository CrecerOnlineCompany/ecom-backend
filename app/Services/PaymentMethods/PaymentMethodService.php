<?php

namespace App\Services\PaymentMethods;

use App\Models\PaymentProvider;
use Illuminate\Support\Facades\Log;

class PaymentMethodService
{
    /**
     * Obtener métodos de pago disponibles agrupados
     */
    public static function getGroupedMethods(): array
    {
        $providers = PaymentProvider::where('is_active', true)
            ->get()
            ->map(function ($provider) {
                return self::formatProvider($provider);
            })
            ->toArray();

        return [
            'redirect' => array_filter($providers, fn($p) => in_array('redirect', $p['available_methods'])),
            'qr' => array_filter($providers, fn($p) => in_array('qr', $p['available_methods'])),
            'terminal' => array_filter($providers, fn($p) => in_array('terminal', $p['available_methods'])),
            'manual' => array_filter($providers, fn($p) => in_array('manual', $p['available_methods'])),
        ];
    }

    /**
     * Obtener métodos de pago con información completa
     */
    public static function getMethodsWithDetails(): array
    {
        return [
            'redirect' => [
                'icon' => '🔄',
                'label' => 'Redirección Segura',
                'description' => 'Serás redirigido a la plataforma de pago segura',
                'badge' => 'SEGURO',
                'badge_color' => '#3498db',
            ],
            'qr' => [
                'icon' => '📱',
                'label' => 'Código QR',
                'description' => 'Escanea el código QR con tu teléfono para pagar',
                'badge' => 'RÁPIDO',
                'badge_color' => '#e74c3c',
            ],
            'terminal' => [
                'icon' => '💳',
                'label' => 'Terminal Smart Point',
                'description' => 'Paga con tu tarjeta en la terminal Mercado Pago Punto',
                'badge' => 'EN VENTA',
                'badge_color' => '#2ecc71',
            ],
            'manual' => [
                'icon' => '💵',
                'label' => 'Pago en Efectivo',
                'description' => 'Paga en efectivo en taquilla',
                'badge' => 'MANUAL',
                'badge_color' => '#95a5a6',
            ],
        ];
    }

    /**
     * Formatear provider para respuesta API
     */
    private static function formatProvider(PaymentProvider $provider): array
    {
        $methods = $provider->getAvailableMethods();
        $methodDetails = self::getMethodsWithDetails();

        return [
            'id' => $provider->id,
            'name' => $provider->name,
            'display_name' => $provider->display_name,
            'description' => $provider->description,
            'icon_url' => $provider->icon_url,
            'is_active' => $provider->is_active,
            'available_methods' => $methods,
            'methods_details' => array_intersect_key($methodDetails, array_flip($methods)),
            'requires_redirect' => $provider->requires_redirect,
            'supports_webhook' => $provider->supports_webhook,
        ];
    }

    /**
     * Verificar si un método está soportado globalmente
     */
    public static function isMethodSupported(string $method): bool
    {
        $providers = PaymentProvider::where('is_active', true)->get();
        
        foreach ($providers as $provider) {
            if (in_array($method, $provider->getAvailableMethods())) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Obtener proveedores que soportan un método específico
     */
    public static function getProvidersForMethod(string $method): array
    {
        return PaymentProvider::where('is_active', true)
            ->get()
            ->filter(function ($provider) use ($method) {
                return in_array($method, $provider->getAvailableMethods());
            })
            ->map(fn($p) => self::formatProvider($p))
            ->values()
            ->toArray();
    }

    /**
     * Obtener información de un método
     */
    public static function getMethodInfo(string $method): ?array
    {
        $details = self::getMethodsWithDetails();
        return $details[$method] ?? null;
    }
}
