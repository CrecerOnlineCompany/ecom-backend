<?php

namespace Database\Seeders;

use App\Models\PaymentProvider;
use Illuminate\Database\Seeder;

class PaymentProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $providers = [
            [
                'name' => 'mercado_pago',
                'display_name' => 'Mercado Pago',
                'description' => 'Paga con Mercado Pago - La plataforma de pagos más grande de Latinoamérica',
                'icon_url' => 'https://www.mercadopago.com.ar/-/media/project/marcositecore/mercado-pago/logo-mp.svg',
                'is_active' => true,
                'requires_redirect' => true,
                'supports_webhook' => true,
                'webhook_secret' => PaymentProvider::generateWebhookSecret(),
                'config' => [
                    'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN', 'APP_USR-106251339415095-022617-2fb2ec2c645d62c5ebfc7334e279f245-1835323571'),
                    'public_key' => env('MERCADO_PAGO_PUBLIC_KEY', 'APP_USR-b4b4d09a-57cc-4770-8b8d-da05359511c3'),
                    'notification_url' => null,
                ],
            ],
            [
                'name' => 'paypal',
                'display_name' => 'PayPal',
                'description' => 'Paga de forma segura con tu cuenta de PayPal',
                'icon_url' => 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/cc-badges-ppppc.png',
                'is_active' => true,
                'requires_redirect' => true,
                'supports_webhook' => true,
                'webhook_secret' => PaymentProvider::generateWebhookSecret(),
                'config' => [
                    'client_id' => env('PAYPAL_CLIENT_ID', ''),
                    'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),
                    'mode' => env('PAYPAL_MODE', 'sandbox'),
                ],
            ],
            [
                'name' => 'cash',
                'display_name' => 'Pago en Efectivo',
                'description' => 'Paga directamente en la boletería del cine',
                'icon_url' => 'https://cdn-icons-png.flaticon.com/512/1162/1162573.png',
                'is_active' => true,
                'requires_redirect' => false,
                'supports_webhook' => false,
                'webhook_secret' => PaymentProvider::generateWebhookSecret(),
                'config' => [
                    'instruction' => 'Presenta esta entrada en la boletería para proceder con el pago en efectivo',
                ],
            ],
        ];

        foreach ($providers as $provider) {
            PaymentProvider::updateOrCreate(
                ['name' => $provider['name']],
                $provider
            );
        }
    }
}
