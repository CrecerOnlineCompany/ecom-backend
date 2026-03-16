#!/bin/bash

# WEBHOOK TESTING GUIDE
# Ejecuta este script para obtener y probar las URLs del webhook

set -e

echo "======================================"
echo "PAYMENT WEBHOOK DEBUGGING GUIDE"
echo "======================================"
echo ""

# Colores
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# 1. Obtener URLs de webhook
echo -e "${BLUE}1. Obteniendo URLs de webhook...${NC}"
echo ""
php artisan payment:providers

echo ""
echo -e "${BLUE}2. Verificando órdenes y sus pagos...${NC}"
echo ""
php artisan order:check-payments --recent=24 --limit=10

echo ""
echo -e "${BLUE}3. Estadísticas de órdenes sin pago...${NC}"
echo ""
php artisan order:check-payments --without-payment --limit=5

echo ""
echo -e "${GREEN}=====================================${NC}"
echo -e "${YELLOW}CÓMO PROBAR EL WEBHOOK:${NC}"
echo -e "${GREEN}=====================================${NC}"
echo ""
echo -e "${BLUE}Opción A: Usando el comando de test${NC}"
echo "php artisan payment:test-webhook mercadopago --status=approved"
echo ""
echo -e "${BLUE}Opción B: Usando curl directamente${NC}"
echo ""
echo "Primero, obtén la URL del webhook ejecutando:"
echo "  php artisan payment:providers"
echo ""
echo "Luego usa curl con esta estructura:"
echo ""
echo 'curl -X POST "WEBHOOK_URL" \'
echo '  -H "Content-Type: application/json" \'
echo '  -H "User-Agent: MercadoPago/1.0" \'
echo '  -d '"'"'{
echo '    "type": "payment.created",
echo '    "data": {
echo '      "id": "PAYMENT_ID_AQUI",
echo '      "status": "approved",
echo '      "external_reference": "CINEA-1-test123"
echo '    }
echo '  }'"'"' \'
echo '  -v'
echo ""
echo -e "${BLUE}Opción C: Usando ngrok para exponer localhost${NC}"
echo ""
echo "Si la app está en localhost:"
echo "1. Instala ngrok: https://ngrok.com/download"
echo "2. En otra terminal: ngrok http 80"
echo "3. Usa la URL generada en tu webhook config de Mercado Pago"
echo ""
echo -e "${GREEN}=====================================${NC}"
echo -e "${YELLOW}MONITOREAR LOGS:${NC}"
echo -e "${GREEN}=====================================${NC}"
echo ""
echo "Abre otra terminal y ejecuta:"
echo -e "${BLUE}tail -f storage/logs/laravel.log | grep -i 'webhook\\|mercadopago'${NC}"
echo ""
echo "O para ver solo MercadoPago QR:"
echo -e "${BLUE}tail -f storage/logs/laravel.log | grep 'MercadoPagoQR'${NC}"
echo ""
echo -e "${GREEN}=====================================${NC}"
echo -e "${YELLOW}COMANDOS ÚTILES:${NC}"
echo -e "${GREEN}=====================================${NC}"
echo ""
echo "Ver todos los proveedores de pago:"
echo -e "${BLUE}php artisan payment:providers --detailed${NC}"
echo ""
echo "Ver pagos pendientes:"
echo -e "${BLUE}php artisan payment:check --status=pending${NC}"
echo ""
echo "Ver pagos aprobados en las últimas 12 horas:"
echo -e "$órdenes y sus pagos:"
echo -e "${BLUE}php artisan order:check-payments${NC}"
echo ""
echo "Ver órdenes sin pago:"
echo -e "${BLUE}php artisan order:check-payments --without-payment${NC}"
echo ""
echo "Ver órdenes con pago aprobado:"
echo -e "${BLUE}php artisan order:check-payments --payment-status=approved${NC}"
echo ""
echo "Ver órdenes de un cine específico:"
echo -e "${BLUE}php artisan order:check-payments --cinema=MiCine
echo "Enviar webhook de prueba con status 'pending':"
echo -e "${BLUE}php artisan payment:test-webhook mercadopago --status=pending${NC}"
echo ""
echo -e "${GREEN}=====================================${NC}"
echo -e "${YELLOW}TROUBLESHOOTING:${NC}"
echo -e "${GREEN}=====================================${NC}"
echo ""
echo "Si el webhook no llega:"
echo "1. ✓ Verifica la URL en 'payment:providers'"
echo "2. ✓ Confirma que la URL es publicamente accesible"
echo "3. ✓ Prueba con curl para verificar que responde"
echo "4. ✓ Lee los logs mientras envías un webhook"
echo "5. ✓ Verifica que el webhook_secret no sea NULL"
echo ""
echo "Si MercadoPago no encuentra la orden:"
echo "1. Revisa que payment_ticket.order_id exista"
echo "2. Verifica que order tenga un screening válido"
echo "3. Revisa que el external_reference sea correcto"
echo ""
