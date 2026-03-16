# Webhook Payment Debugging Guide

## Quick Start

### 1. Obtener las URLs del Webhook

Para ver todas tus URLs de webhook configuradas:

```bash
php artisan payment:providers
```

Esto te mostrará algo como:
```
ID: 1
Name: mercadopago
Display Name: Mercado Pago
Active: Yes
✓ Webhook URL: http://tu-app.com/api/webhooks/payment/abc123def456...
```

**Esta es la URL que debes configurar en Mercado Pago**: `http://tu-app.com/api/webhooks/payment/{webhook_secret}`

### 2. Verificar Pagos Existentes

Ver los últimos pagos:
```bash
php artisan payment:check
```

Ver pagos con status específico:
```bash
php artisan payment:check --status=approved
php artisan payment:check --status=pending
php artisan payment:check --status=declined
```

Ver pagos de las últimas N horas:
```bash
php artisan payment:check --recent=24      # Últimas 24 horas
php artisan payment:check --recent=12      # Últimas 12 horas
php artisan payment:check --recent=1       # Última hora
```

### 3. Probar el Webhook

#### Opción A: Usando el comando de test (RECOMENDADO)

```bash
php artisan payment:test-webhook mercadopago --status=approved
```

Esto enviará un webhook de prueba al endpoint y mostrará:
- URL del webhook
- Payload enviado
- Respuesta del servidor
- Instrucciones para ver los logs

#### Opción B: Usando cURL

```bash
curl -X POST "http://tu-app.com/api/webhooks/payment/WEBHOOK_SECRET" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "payment.created",
    "data": {
      "id": "1234567890",
      "status": "approved",
      "external_reference": "CINEA-1-testABC123"
    }
  }' \
  -v
```

### 4. Monitorear Logs en Tiempo Real

Abre una terminal y ejecuta:

```bash
# Ver todos los logs de webhook
tail -f storage/logs/laravel.log | grep -i webhook

# Ver solo logs de MercadoPago QR
tail -f storage/logs/laravel.log | grep 'MercadoPagoQR'

# Ver logs con colores
tail -f storage/logs/laravel.log | grep --color=always 'MercadoPagoQR\|ERROR\|WARNING'
```

## Estructura de URLs

La ruta del webhook es: `/api/webhooks/payment/{webhook_secret}`

**Ejemplo completo:**
```
https://cinea.example.com/api/webhooks/payment/5f2a8c3b-4d1e-9c6f-2a7b-8e3f1c9d5a2b
```

Donde {webhook_secret} es un hash único generado para cada provider.

## Verificar Órdenes y Pagos

### Ver todas las órdenes y si tienen pago:

```bash
php artisan order:check-payments
```

Mostrará una tabla con:
- Número de orden
- Cine y película
- Usuario
- Monto
- Estado del pago
- Proveedor de pago
- Fecha de creación

### Ver órdenes SIN pago:

```bash
php artisan order:check-payments --without-payment
```

⚠️ **Importante:** Si tienes órdenes sin pago, significa que no se completó el flujo de pago.

### Ver órdenes con pago APROBADO:

```bash
php artisan order:check-payments --payment-status=approved
```

### Ver órdenes PENDIENTES:

```bash
php artisan order:check-payments --payment-status=pending
```

### Ver órdenes de un cine específico:

```bash
php artisan order:check-payments --cinema="Nombre del Cine"
```

### Ver órdenes de las últimas N horas:

```bash
php artisan order:check-payments --recent=24     # Últimas 24 horas
php artisan order:check-payments --recent=1      # Última hora
```

### Ver detalles completos:

El comando te permitirá seleccionar una orden para ver:
- Tickets asociados
- Información del pago
- Data de respuesta del proveedor
- Detalles de usuario

---


Listar todos los proveedores de pago y sus webhooks

```bash
php artisan payment:providers              # Lista básica
php artisan payment:providers --detailed   # Con más detalles
```

### payment:check
Verificar estado de pagos

```bash
php artisan payment:check                          # Últimos pagos
php artisan payment:check --status=approved        # Filtrar por status
php artisan payment:check --provider=mercadopago   # Filtrar por provider
php artisan payment:check --recent=24              # Últimas 24 horas
php artisan payment:check --limit=50               # Límite de resultados
```

Combinaciones útiles:
```bash
php artisan payment:check --status=pending --recent=1    # Pendientes de última hora
php artisan payment:check --status=declined --recent=24   # Rechazados hoy
```

### order:check-payments
Revisar órdenes y sus pagos asociados

```bash
php artisan order:check-payments                           # Todas las órdenes
php artisan order:check-payments --without-payment         # Órdenes sin pago
php artisan order:check-payments --payment-status=approved # Órdenes con pago aprobado
php artisan order:check-payments --cinema=NombreCine       # Filtrar por cine
php artisan order:check-payments --status=completed        # Filtrar por estado de orden
php artisan order:check-payments --recent=24               # Últimas 24 horas
php artisan order:check-payments --limit=50                # Mostrar más resultados
```

Combinaciones útiles:
```bash
php artisan order:check-payments --without-payment --recent=1     # Órdenes sin pagar hoy
php artisan order:check-payments --payment-status=pending         # Pagos pendientes
php artisan order:check-payments --cinema=SalaDelaSur --recent=7  # Por cine y semana
```

### payment:test-webhook
Enviar webhook de prueba

```bash
php artisan payment:test-webhook mercadopago --status=approved
php artisan payment:test-webhook mercadopago --status=pending
php artisan payment:test-webhook mercadopago --status=declined
php artisan payment:test-webhook mercadopago --payment-id=12345
php artisan payment:test-webhook mercadopago --external-ref=CINEA-1-TEST123
```

## Troubleshooting

### El webhook no me llega

1. **Verifica la URL:**
   ```bash
   php artisan payment:providers
   ```
   - Confirma que el `webhook_secret` no sea NULL
   - La URL debe ser públicamente accesible (no localhost si usas Mercado Pago production)

2. **Prueba la URL con cURL:**
   ```bash
   curl -X POST "TU_WEBHOOK_URL" \
     -H "Content-Type: application/json" \
     -d '{"type":"payment","data":{"id":"123","status":"approved"}}' \
     -v
   ```

3. **Monitorea los logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep 'MercadoPagoQR'
   ```
   Busca mensajes como:
   - `Webhook recibido` - El webhook llegó
   - `ID externo extraído` - Se encontró el payment ID
   - `PaymentProviderTicket encontrado` - Se encontró el pago
   - `Status obtenido` - Se obtuvo el estado

4. **Verifica PaymentProvider.webhook_secret:**
   ```bash
   php artisan tinker
   ```
   
   Luego en el prompt:
   ```php
   App\Models\PaymentProvider::where('name', 'like', '%mercado%')->first()
   ```
   
   Debe devolver un objeto con `webhook_secret` NO NULL.

### El webhook recibe pero dice "PaymentProviderTicket no encontrado"

Esto significa que el sistema no puede encontrar el pago en la BD.

1. Verifica que el `external_reference` sea correcto:
   ```bash
   php artisan tinker
   App\Models\PaymentProviderTicket::where('transaction_id', 'PAYMENT_ID')->first()
   ```

2. Los logs te dirán exactamente qué está buscando:
   ```
   MercadoPagoQR: PaymentProviderTicket NO ENCONTRADO tras todos los intentos
   external_id: 1234567890
   external_reference: CINEA-1-abc123
   all_payment_tickets_sample: [...]
   ```

3. Verifica que el payment tenga una orden vinculada:
   ```bash
   php artisan tinker
   $pt = App\Models\PaymentProviderTicket::find(ID);
   $pt->order  // Debe retornar una Order, no NULL
   ```

### El webhook llega pero el pago no se aprueba

1. Revisa si hay errores en `approve()`:
   ```bash
   tail -f storage/logs/laravel.log | grep 'Error.*aprobar\|Error al finalizar'
   ```

2. Verifica que la orden tenga estado válido:
   ```bash
   php artisan tinker
   $order = App\Models\Order::find(ORDER_ID);
   $order->status
   ```

3. Revisa que los tickets tengan asientos válidos:
   ```bash
   $order->tickets()->with('inventories')->get()
   ```

## Script de Testing Automático

Ejecuta el script completo de testing:

```bash
./test-webhook.sh
```

Este script:
1. Obtiene las URLs de webhook
2. Muestra pagos recientes
3. Genera estadísticas
4. Te da instrucciones detalladas para probar

## Variables de Webhook

El endpoint recibe webhooks con esta estructura básica:

```json
{
  "type": "payment.created",
  "data": {
    "id": "1234567890",
    "status": "approved|pending|rejected",
    "external_reference": "CINEA-1-abc123def456"
  },
  "live_mode": false,
  "date_created": "2024-01-01T12:00:00Z"
}
```

El sistema busca el pago usando:
1. `data.id` como transaction_id
2. `external_reference` en response_data
3. Si no encuentra, consulta la API de Mercado Pago

## Logs y Debugging

Los logs se guardan en `storage/logs/laravel.log`

Busca estas claves:
- `webhook_id` - ID único para rastrear un webhook
- `payment_ticket_id` - ID del pago
- `external_id` - ID del payment en Mercado Pago
- `status` - Estado del pago

Ejemplo de log completo:

```
MercadoPagoQR: Webhook recibido
webhook_id: a1b2c3d4-e5f6-7890-abcd-ef1234567890
data_keys: [type, data, ...]

MercadoPagoQR: ID externo extraído exitosamente
external_id: 1234567890

MercadoPagoQR: PaymentProviderTicket encontrado
payment_ticket_id: 42

MercadoPagoQR: Status obtenido y mapeado
original_status: approved
mapped_status: approved

MercadoPagoQR: Pago QR aprobado exitosamente
payment_ticket_id: 42
order_id: 15
```

## Configuración en Mercado Pago

En el panel de Mercado Pago:

1. Ve a Configuración → Notificaciones Webhooks
2. Agregar notificación:
   - URL: `https://tu-app.com/api/webhooks/payment/WEBHOOK_SECRET`
   - Eventos: Pagos (payment.created, payment.updated)
3. Hacer clic en "Guardar"
4. Probar con "Enviar notificación de prueba"

## Desarrollo Local

Si desarrollas localmente, usa **ngrok**:

```bash
# Terminal 1: Inicia ngrok
ngrok http 80

# Verás algo como:
# Forwarding: https://abc123.ngrok.io -> http://localhost

# Terminal 2: Usa esa URL en tu webhook config
# https://abc123.ngrok.io/api/webhooks/payment/WEBHOOK_SECRET

# Terminal 3: Monitorea logs
tail -f storage/logs/laravel.log | grep 'MercadoPagoQR'
```

## Preguntas Frecuentes

**P: ¿Cómo sé si mi webhook está configurado correctamente?**
R: Ejecuta `php artisan payment:test-webhook mercadopago` y revisa los logs.

**P: ¿Por qué dice "PaymentProviderTicket no encontrado"?**
R: El external_reference o transaction_id no coinciden con lo que está en la BD. Revisa los logs para ver qué está buscando.

**P: ¿Puedo probar sin Mercado Pago?**
R: Sí, usa `php artisan payment:test-webhook` para enviar webhooks de prueba.

**P: ¿Dónde veo todos los detalles del webhook que llegó?**
R: En los logs con el `webhook_id`. Busca ese UUID en `storage/logs/laravel.log`.
