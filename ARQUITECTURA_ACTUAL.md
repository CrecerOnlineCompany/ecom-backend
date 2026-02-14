# Arquitectura CINEA - Estado Actual

**Fecha:** 14 de febrero de 2026  
**Versión:** 1.0

---

## 📊 Diagrama de Relaciones

```
┌─────────────────────────────────────────────────────────────────────┐
│                          CINE                                       │
│                    (Entities Maestras)                              │
└─────────────────────────────────────────────────────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
    [Cinema]             [Movie]              [User]
        │                     │                    │
        │                     │                    │
        └──────────┬──────────┘                    │
                   │                                │
                [Room]                              │
                   │                                │
                   │                                │
                [Seat]                              │
                   │                                │
    ┌──────────────┼──────────────┐                │
    │              │              │                │
[Screening]<─────┘              │                │
    │                           │                │
    └───────────┬───────────────┘                │
                │                                │
            [Ticket] ◄──────────────────────────┘
                │
    ┌───────────┘
    │
[TicketDetail]          [PaymentProvider]
                              │
                              │
                    [PaymentProviderTicket]
                              │
                   ┌──────────┴──────────┐
                   │                     │
            [MpTerminalOrder]     (Webhooks)
```

---

## 🔧 Modelos y Tablas

### **1. USUARIOS Y AUTENTICACIÓN**

#### `users` - Usuarios del Sistema
```
id (PK)
name
email (unique)
password (hashed)
remember_token
email_verified_at
created_at
updated_at
```
**Model:** `User extends Authenticatable`  
**Roles:** Clientes, administradores (se usa librerías de Admin externales)  
**Relaciones:**
- `HasMany` → Tickets
- `HasMany` → PaymentProviders (admin)

---

### **2. ESTRUCTURA DEL CINE**

#### `cinemas` - Cines/Sucursales
```
id (PK)
name
city
address
phone
email
latitude (float)
longitude (float)
description
is_active (boolean)
created_at, updated_at
```
**Model:** `Cinema`  
**Relaciones:**
- `HasMany` → Rooms
- `HasManyThrough` → Screenings (vía Rooms)

#### `rooms` - Salas de Proyección
```
id (PK)
cinema_id (FK)
number
name
total_seats (int)
type (enum: standard, imax, 4dx, etc)
rows (int)
columns (int)
description
is_active (boolean)
created_at, updated_at
```
**Model:** `Room`  
**Relaciones:**
- `BelongsTo` → Cinema
- `HasMany` → Seats
- `HasMany` → Screenings

#### `seats` - Asientos de Sala
```
id (PK)
room_id (FK)
row_number (int)
seat_number (int)
seat_code (string: "A1", "B5")
type (vip, standard, wheelchair, etc)
price_modifier (decimal: 1.5x, 0.5x)
is_active (boolean)
created_at, updated_at
```
**Model:** `Seat`  
**Relaciones:**
- `BelongsTo` → Room
- `HasMany` → Tickets (legacy)
- `HasMany` → TicketDetails

---

### **3. PELÍCULAS Y FUNCIONES**

#### `movies` - Películas en Cartelera
```
id (PK)
title
description (text)
genre
duration (minutes)
rating (PG, R, etc)
director
cast (json)
language
poster_url
poster_image (filename)
trailer_url
release_date (date)
end_date (date)
is_active (boolean)
created_at, updated_at
```
**Model:** `Movie`  
**Relaciones:**
- `HasMany` → Screenings
- `appends` → poster_image_url (computed)

#### `screenings` - Funciones de Películas
```
id (PK)
movie_id (FK)
room_id (FK)
start_time (datetime)
end_time (datetime)
price (decimal: precio base)
available_seats (int: desnormalizado)
format (2D, 3D, IMAX)
is_active (boolean)
created_at, updated_at
```
**Model:** `Screening`  
**Relaciones:**
- `BelongsTo` → Movie
- `BelongsTo` → Room
- `HasMany` → Tickets
- `Cinema()` → acceso vía Room.Cinema

---

### **4. ENTRADAS Y DETALLES**

#### `tickets` - Registro Principal de Tickets
```
id (PK)
screening_id (FK)
user_id (FK)
seat_id (FK nullable) [legacy, nuevo sistema usa TicketDetail]
ticket_number (unique)
price (decimal)
status (enum: pending_payment, processing, confirmed, payment_failed)
qr_code (string nullable)
used_at (datetime nullable)

--- Información Desnormalizada (para independencia de BD)
customer_name
customer_email
customer_phone
movie_title
room_name
cinema_name
screening_start_time (datetime)
screening_format

--- Metadatos Estadísticos
payment_method (mercado_pago, qr, terminal, etc)
original_price (sin descuentos)
discount_amount
discount_code
purchased_at (datetime)
purchase_device (web, mobile, kiosk)
ip_address

created_at, updated_at
```
**Índices:** 
- `(user_id, status)`
- `movie_title`, `cinema_name`, `screening_start_time`, `purchased_at`, `payment_method`, `status`

**Model:** `Ticket`  
**Relaciones:**
- `BelongsTo` → Screening, User, Seat
- `HasMany` → PaymentProviderTickets
- `HasMany` → TicketDetails (múltiples asientos)
- **Método:** `populateDenormalizedFields()` - rellena campos desnormalizados antes de guardar

#### `ticket_details` - Detalles de Entrada (un ticket = múltiples asientos)
```
id (PK)
ticket_id (FK)
screening_id (FK)
seat_id (FK)
seat_code
row_number
seat_number
price (decimal)
status (enum: pending, confirmed, used, cancelled)
qr_code (nullable)
used_at (datetime nullable)

created_at, updated_at
```
**Model:** `TicketDetail`  
**Relaciones:**
- `BelongsTo` → Ticket, Screening, Seat

**Propósito:** Permitir compras de múltiples entradas en una transacción. Cada fila es un asiento individual.

---

### **5. PAGOS - PROVEEDORES**

#### `payment_providers` - Proveedores de Pago
```
id (PK)
name (unique: mercado_pago, stripe, etc)
display_name
description
icon_url
is_active (boolean)

--- Configuración
config (JSON)
  {
    "access_token": "...",
    "refresh_token": "...",
    "terminal_id": "...",
    "client_id": "...",
    "client_secret": "...",
    "supports_redirect": boolean,
    "supports_qr": boolean,
    "supports_terminal": boolean,
    "supported_methods": [redirect, qr, terminal]
  }

--- Webhooks
redirect_url
webhook_path (ej: /api/payments/webhook/mercado_pago)
webhook_secret
requires_redirect (boolean)
supports_webhook (boolean)

created_at, updated_at
```
**Model:** `PaymentProvider`  
**Métodos:**
- `getConfig(key)` - obtener valor de config con fallback
- `setConfig(key, value)` - actualizar config
- **Event:** `saving()` - reconstruye `config` desde request y genera `supported_methods`

**Relaciones:**
- `HasMany` → PaymentProviderTickets
- `HasMany` → MpTerminalOrders (solo Mercado Pago)

---

### **6. PAGOS - TRANSACCIONES**

#### `payment_provider_tickets` - Historial de Intentos de Pago
```
id (PK)
ticket_id (FK)
payment_provider_id (FK)
status (enum: pending, processing, approved, declined, refunded, queued, cancelled, expired)
transaction_id (unique nullable) - ID del provider
reference_number (nullable)
response_data (JSON) - respuesta completa del provider con metadata

initiated_at (timestamp)
completed_at (timestamp)
created_at, updated_at

Constraint: unique(ticket_id, payment_provider_id)
```

**Model:** `PaymentProviderTicket`  
**Estados:**
- **pending** → creado, no iniciado aún
- **processing** → esperando confirmación
- **approved** → pagado exitosamente → confirma Ticket
- **declined** → rechazado por provider
- **refunded** → reembolsado
- **queued** → en cola en terminal (Mercado Pago Point)
- **cancelled** → cancelado manualmente
- **expired** → expiró el tiempo límite

**Métodos:**
- `approve(data)` - marca approved, actualiza Ticket a confirmed
- `decline(data)` - marca declined
- `refund(reason)` - marca refunded
- `markQueued(data)` - marca queued
- `markCancelled(reason)` - marca cancelled
- `markExpired(reason)` - marca expired
- `isApproved()`, `isPending()` - helpers
- `getResponseData(key, default)` - accece JSON response_data

**Relaciones:**
- `BelongsTo` → Ticket, PaymentProvider
- `HasOne` → MpTerminalOrder (Mercado Pago)

---

### **7. PAGOS - MERCADO PAGO POINT (Terminal)**

#### `mp_terminal_orders` - Tracking Global de Órdenes en Terminales
```
id (PK)
payment_provider_id (FK)
payment_provider_ticket_id (FK nullable)
terminal_id (string, index) - ej: PAX_A910__SMARTPOS_xxxxxxx
order_id (unique) - ID retornado por API MP
external_reference (nullable) - CINEA-POINT-{payment_ticket_id}

status (enum: active, pending, processing, approved, declined, cancelled, expired, unknown)
  - active: orden creada, esperando pago
  - pending: estado desconocido, pendiente verificación
  - processing: cliente pagando
  - approved: pagado
  - declined: rechazado
  - cancelled: cancelada (por auto-cancel o usuario)
  - expired: tiempo expirado (10min)
  - unknown: no se pudo determinar

response_data (JSON) - última respuesta de MP
cancel_reason (string nullable) - 'user_changed_method', 'auto_cancel', 'next_order_retry'

created_at, last_checked_at, cancelled_at
```

**Índices:**
- `(payment_provider_id, terminal_id, created_at)`
- `(terminal_id, status)`

**Model:** `MpTerminalOrder`  
**Métodos:**
- `getActiveOrderByTerminal(providerId, terminalId)` - obtiene última orden activa
- `getUnresolvedByTerminal(providerId, terminalId, lookbackMinutes)` - últimas N horas no resueltas
- `markActive()` - marca activa
- `markCancelled(reason)` - marca cancelada

**Propósito:** 
- **Auto-cancel global**: detectar órdenes colgadas en terminal
- **Guard antes de pago**: verificar si hay orden pendiente antes de crear nueva
- **Race condition handling**: manejar código 409 de MP
- **Auditoría**: registro independiente de intentos en terminal

---

## 🔄 Flujo de Compra de Entradas

### **1. Selección de Entradas (Frontend)**
```
User selects seats → /api/payments/process-terminal-payment
```

### **2. POST `/api/payments/process-terminal-payment`**
```
PaymentController::processTerminalPayment()

1. Validar request (screening_id, seat_ids[], payment_provider_id, etc)
2. DB::beginTransaction()
3. Verificar disponibilidad de asientos:
   - ¿Hay confirmed tickets? → error SEAT_UNAVAILABLE
   - ¿Hay pending/processing de otro usuario? → error SEAT_PROCESSING
   - ¿Hay pending/processing del mismo usuario? → DELETE anterior, reintentar
4. Crear Tickets (status: pending_payment)
   - Un Ticket parent
   - Un TicketDetail por asiento
5. Llamar PaymentProviderManager::initiatePaymentWithMethod()
   - method = 'terminal'
6. Si error → rollback, return error
7. Si éxito → commit
   - Update Ticket status → processing
   - Update PaymentProviderTicket
   - Track en MpTerminalOrders
8. Return {success, order_id, payment_ticket_id, requires_polling, polling_interval}
```

### **3. `PaymentProviderManager::initiatePaymentWithMethod()`**
```
1. Obtener PaymentProvider
2. Obtener Handler específico (MercadoPagoPointHandler)
3. Llamar handler->processPayment(paymentTicket, additionalData)
```

### **4. `MercadoPagoPointHandler::processPayment()`**
```
1. Guard (PRE-CHECK):
   IF ENABLE_AUTO_CANCEL_QUEUED:
     guardTerminalBeforePayment(terminal_id)
     - Busca últimas órdenes activas (últimas 120 min)
     - Si hay → intenta cancelarlas automáticamente
     - Si falla → retorna error, bloquea creación

2. Enviar orden a MP:
   POST https://api.mercadopago.com/v1/orders
   Headers:
     - Authorization: Bearer {access_token}
     - X-Idempotency-Key: CINEA-{payment_ticket_id}-{random}
   Body:
     {
       "type": "point",
       "external_reference": "CINEA-POINT-{payment_ticket_id}",
       "description": "Entradas - {movie} ({N} un.)",
       "expiration_time": "PT10M",
       "transactions": { "payments": [{ "amount": "XX.XX" }] },
       "config": { "point": { "terminal_id": "..." } }
     }

3. Si 200 OK:
   - Obtener order_id
   - Update PaymentProviderTicket(status: processing)
   - Track en MpTerminalOrders(status: active)
   - Return {success: true, order_id, ...}

4. Si 409 (already_queued_order_on_terminal):
   IF ENABLE_AUTO_CANCEL_QUEUED:
     - Llamar guardTerminalBeforePayment() nuevamente
     - Reintentar con NEW X-Idempotency-Key
     - Si 200 OK → Return {success, order_id, metadata: {canceled_order_id}}
     - Si error → Return {error: auto_cancel_failed}
   ELSE:
     - Return {error: terminal_busy}

5. Si otro error → Log, return error
```

### **5. Frontend: Polling para Confirmación**
```
Frontend polls every 3 segundos:
GET /api/payments/status/{payment_ticket_id}

PaymentController::status()
  → PaymentProviderManager::getPaymentStatus()
    → MercadoPagoPointHandler::getPaymentStatus()
      → Check PaymentProviderTicket.status
      → Optional: consultar MercadoPagoTerminalService::getOrderStatus()

Si status = approved:
  - Mostrar confirmación
  - Guardar tickets confirmados
  - Mostrar QR para validación

Si status = declined/expired:
  - Mostrar error
  - Permitir reintentar o cambiar método
```

### **6. Webhook de Mercado Pago**
```
POST /api/payments/webhook/mercado_pago

MercadoPagoPointHandler::handleWebhook()
1. Extraer order_id y status de payload
2. Buscar PaymentProviderTicket por transaction_id = order_id
3. Mapear status:
   - approved → approve() → Ticket.confirmed
   - pending → update status
   - declined/cancelled → decline()
4. Log y return true
```

---

## 📊 Estados de Pago

### **PaymentProviderTicket.status**
```
pending ──→ processing ──→ approved ✓
     └──→ declined ✗
     └──→ refunded (después de approved)
     └──→ queued (Mercado Pago Point espera)
     └──→ cancelled (usuario cancela)
     └──→ expired (timeout 10min)
```

### **Ticket.status**
```
pending_payment ──→ processing ──→ confirmed ✓
            └──→ payment_failed ✗
```

---

## 🔐 Seguridad y Validaciones

1. **Idempotencia**: `X-Idempotency-Key` en requests a MP
2. **Transacciones BD**: `DB::beginTransaction()` para atomicidad
3. **Validación de Asientos**: doble check (confirmed + pending)
4. **Auto-cancel Global**: detecta órdenes colgadas, intenta resolver
5. **Webhook Validation**: validar hash/signature del provider
6. **Rate Limiting**: aplicable a endpoints de pago
7. **Logs Detallados**: seguimiento completo de flujos en storage/logs

---

## 📈 Flujos Especiales

### **A. QR Payment** (`processQrPayment`)
Similar a Terminal, pero:
- Handler: `MercadoPagoQrHandler`
- Retorna: `qr_data` en lugar de `order_id`
- Endpoint confirmación: `confirmQrPayment(paymentTicketId, token)`

### **B. Batch Payment** (`processBatchPayment`)
- Múltiples asientos en una transacción
- Crea múltiples Tickets y TicketDetails
- Un único PaymentProviderTicket para toda la compra

### **C. Refund** (`refund`)
```
PaymentController::refund(paymentTicketId, reason)
  → PaymentProviderManager::refundPayment()
    → Handler->refund(paymentTicket, reason)
    
Mercado Pago:
  - Buscar transaction_id en MP API
  - Ejecutar POST /v1/payments/{id}/refunds
  - Update PaymentProviderTicket(status: refunded)
  - Update Tickets(status: cancelled)
```

### **D. Cleanup de Tickets Pendientes**
```
POST /api/payments/cleanup?hours=2

Elimina Tickets con status:
- pending_payment (> 2 horas)
- processing
- payment_failed
```

---

## 🏗️ Arquitectura de Servicios

### **PaymentProviderManager** (Orquestador)
- Obtiene proveedores activos
- Selecciona handler por tipo
- Delega procesamiento
- Gestiona webhooks globales

### **PaymentProviderHandler** (Interfaz)
```
interface PaymentProviderHandler {
  processPayment(paymentTicket, additionalData): array
  handleWebhook(request): bool
  refund(paymentTicket, reason): bool
  getPaymentStatus(paymentTicket): string
  validateConfiguration(): bool
}
```

### **Handlers Implementados**
1. **MercadoPagoPointHandler** - Terminal Smart
2. **MercadoPagoQrHandler** - Pago QR
3. **MercadoPagoRedirectHandler** - Redirect

### **MercadoPagoTerminalService** (Acciones Specificas)
- `guardTerminalBeforePayment()` - auto-cancel check
- `trackNewOrder()` - registra en mp_terminal_orders
- `getOrderStatus()` - consulta MP API

---

## 🔍 Queries Críticas Frecuentes

```sql
-- Asientos disponibles para función
SELECT * FROM seats s
WHERE s.room_id = ? AND s.is_active = 1
AND NOT EXISTS (
  SELECT 1 FROM tickets t 
  WHERE t.seat_id = s.id 
  AND t.screening_id = ?
  AND t.status = 'confirmed'
)

-- Órdenes activas en terminal (últimas 2 horas)
SELECT * FROM mp_terminal_orders
WHERE payment_provider_id = ? 
AND terminal_id = ?
AND status IN ('active', 'pending', 'processing')
AND created_at >= NOW() - INTERVAL 120 MINUTE
ORDER BY created_at DESC

-- Ventas por período
SELECT DATE(t.purchased_at) as date, COUNT(*) as tickets, SUM(t.price) as revenue
FROM tickets t
WHERE t.status = 'confirmed' 
AND t.purchased_at BETWEEN ? AND ?
GROUP BY DATE(t.purchased_at)

-- Métodos de pago más usados
SELECT t.payment_method, COUNT(*) as total, SUM(t.price) as revenue
FROM tickets t
WHERE t.status = 'confirmed'
GROUP BY t.payment_method
```

---

## 📋 Pendientes y Deuda Técnica

1. **Enum Types**: Usar ENUM en BD en lugar de string
2. **Cascading Deletes**: Revisar estrategia (soft deletes?)
3. **Archiving**: Tabla historical_tickets para datos antiguos
4. **Rate Limiting**: Implementar en endpoints críticos
5. **Cache**: Redis para disponibilidad de asientos en vivo
6. **Idempotencia**: Garantizar a nivel de BD (unique constraints mejor)
7. **Tests**: Suite completa de tests para flujos de pago
8. **Documentation**: OpenAPI/Swagger para API

---

## 🎯 Próximos Cambios Grandes (Recomendaciones)

Si planeas cambios significativos:

1. **Refactoring de PaymentProviderTicket**: considerar estado más flexible
2. **Multi-provider por pago**: permitir fallback automático
3. **Auditoría completa**: tabla audit_logs con todas las acciones
4. **Promociones**: tabla discounts y aplicarlas en checkout
5. **Analytics**: tabla analytics_events para datawarehouse
6. **Inventory Management**: reservas de asientos con TTL
7. **Queue Sistema**: jobs async para auto-cancel, webhooks
8. **Machine Learning**: detección de fraude

---

## 📚 Archivos Importantes

- Models: `/app/Models/`
- Migrations: `/database/migrations/`
- Controllers: `/app/Http/Controllers/Api/PaymentController.php`
- Payment Handlers: `/app/Services/PaymentProviders/Handlers/`
- Payment Manager: `/app/Services/PaymentProviders/PaymentProviderManager.php`
- Routes: `/routes/api.php`
