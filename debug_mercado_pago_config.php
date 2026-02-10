<?php

use App\Models\PaymentProvider;

// Script para debugguear la configuración de Mercado Pago

$provider = PaymentProvider::where('name', 'mercado_pago')->first();

if (!$provider) {
    echo "❌ Provider 'mercado_pago' no encontrado\n";
    exit;
}

echo "✅ Provider encontrado: {$provider->name}\n\n";

echo "--- RAW CONFIG (como se guarda en BD) ---\n";
$raw = $provider->getRawOriginal('config');
var_dump($raw);
echo "\n";

echo "--- CASTED CONFIG (después de cast a array) ---\n";
var_dump($provider->config);
echo "\n";

echo "--- INTENTAR ACCEDER A access_token ---\n";
$token = $provider->config['access_token'] ?? 'NO ENCONTRADO';
echo "Token: " . substr($token, 0, 20) . (strlen($token) > 20 ? '...' : '') . "\n\n";

echo "--- JSON_DECODE MANUAL ---\n";
if (is_string($provider->config)) {
    echo "⚠️  Config es STRING, necesita json_decode\n";
    $decoded = json_decode($provider->config, true);
    var_dump($decoded);
    $token2 = $decoded['access_token'] ?? 'NO ENCONTRADO';
    echo "Token: " . substr($token2, 0, 20) . (strlen($token2) > 20 ? '...' : '') . "\n";
} else {
    echo "✅ Config es ARRAY (cast funcionó bien)\n";
}

echo "\n--- VERIFICAR BD DIRECTAMENTE ---\n";
echo "SELECT query:\n";
echo "SELECT id, name, config FROM payment_providers WHERE name = 'mercado_pago';\n";
