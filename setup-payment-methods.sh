#!/bin/bash

# Script para configurar métodos de pago QR y Smart Point
# Uso: bash setup-payment-methods.sh

echo "🎥 ========== CONFIGURACIÓN DE MERCADO PAGO =========="
echo ""

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funciones
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

# Chequear dependencias
print_info "Verificando dependencias..."

# Chequear si composer está instalado
if ! command -v composer &> /dev/null; then
    print_error "Composer no está instalado"
    exit 1
fi
print_success "Composer instalado"

# Chequear si php está instalado
if ! command -v php &> /dev/null; then
    print_error "PHP no está instalado"
    exit 1
fi
print_success "PHP instalado"

# Chequear si npm está instalado
if ! command -v npm &> /dev/null; then
    print_error "NPM no está instalado"
    exit 1
fi
print_success "NPM instalado"

echo ""
echo "📦 Instalando/Actualizando dependencias..."

# Actualizar Composer
print_info "Actualizando dependencias de composer..."
composer update

# Instalar qrcode.js para el frontend
print_info "Instalando qrcode.js..."
npm install qrcode

print_success "Dependencias instaladas"

echo ""
echo "🏗️  Compilando frontend..."
npm run build

print_success "Frontend compilado"

echo ""
echo "🗄️  Ejecutando migraciones..."
php artisan migrate --force

print_success "Base de datos actualizada"

echo ""
echo "🔧 Creando semilla de Payment Providers..."

# Crear script de seeding
cat > database/seeders/PaymentProviderSeeder.php << 'EOF'
<?php

namespace Database\Seeders;

use App\Models\PaymentProvider;
use Illuminate\Database\Seeder;

class PaymentProviderSeeder extends Seeder
{
    public function run(): void
    {
        PaymentProvider::firstOrCreate(
            ['name' => 'mercado_pago'],
            [
                'display_name' => 'Mercado Pago',
                'description' => 'Paga con Mercado Pago de forma segura',
                'icon_url' => 'https://www.mercadopago.com/org-img/MP3.png',
                'is_active' => true,
                'requires_redirect' => true,
                'supports_webhook' => true,
                'config' => json_encode([
                    'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN', ''),
                    'store_id' => env('MERCADO_PAGO_STORE_ID', ''),
                    'terminal_id' => env('MERCADO_PAGO_TERMINAL_ID', ''),
                    'currency_id' => 'ARS',
                    'supported_methods' => ['redirect', 'qr', 'terminal'],
                    'qr_type' => 'custom',
                    'auto_send' => true,
                ]),
                'webhook_secret' => \App\Models\PaymentProvider::generateWebhookSecret(),
            ]
        );

        PaymentProvider::firstOrCreate(
            ['name' => 'cash'],
            [
                'display_name' => 'Efectivo',
                'description' => 'Paga en efectivo en taquilla',
                'icon_url' => null,
                'is_active' => true,
                'requires_redirect' => false,
                'supports_webhook' => false,
                'config' => json_encode(['manual' => true]),
                'webhook_secret' => null,
            ]
        );
    }
}
EOF

php artisan db:seed --class=PaymentProviderSeeder

print_success "Payment Providers creados"

echo ""
echo "✅ ========== CONFIGURACIÓN COMPLETADA =========="
echo ""
echo "📝 Próximos pasos:"
echo ""
echo "1️⃣  Configura las variables de entorno en .env:"
echo "   - MERCADO_PAGO_ACCESS_TOKEN=APP_USR-xxxxxxx"
echo "   - MERCADO_PAGO_STORE_ID=xxxxxxx"
echo "   - MERCADO_PAGO_TERMINAL_ID=TERMINAL_001"
echo ""
echo "2️⃣  Accede a Admin > Payment Providers"
echo "   para verificar y configurar los métodos"
echo ""
echo "3️⃣  Testa en checkout:"
echo "   - Selecciona Mercado Pago como proveedor"
echo "   - Prueba QR (escanea con teléfono)"
echo "   - Prueba Smart Point (terminal simulada)"
echo ""
echo "4️⃣  Consulta la documentación:"
echo "   - PAYMENT_METHODS_SETUP.md"
echo ""
echo "¡Listo! 🎉"
