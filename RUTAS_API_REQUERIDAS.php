<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

/**
 * RECOMENDACIÓN: Agregar estas rutas a routes/api.php
 * 
 * Si ya existen métodos similares, combinarlos siguiendo estos patrones
 */

/**
 * Payment Methods Routes
 * 
 * Ejemplo completo a agregar en routes/api.php:
 */

/*

// Métodos de pago disponibles
Route::get('/payment-methods', [PaymentController::class, 'getMethods'])
    ->name('payment.methods');

// Proveedores para un método específico
Route::get('/payment-methods/{method}/providers', [PaymentController::class, 'getMethodProviders'])
    ->name('payment.method-providers');

// Métodos de pago (EXISTENTE)
Route::get('/payment-providers', [PaymentController::class, 'index'])
    ->name('api.payment.index');

// Detalle de un proveedor (EXISTENTE)
Route::get('/payment-providers/{id}', [PaymentController::class, 'show'])
    ->name('api.payment.show');

// Procesar pago (batch) - EXISTENTE pero mejorado
Route::post('/payment-process-batch', [PaymentController::class, 'processBatchPayment'])
    ->name('api.payment.process-batch');

// ✨ NUEVO: Procesar pago por QR
Route::post('/payment-process-qr', [PaymentController::class, 'processQrPayment'])
    ->name('api.payment.process-qr');

// ✨ NUEVO: Procesar pago por Terminal Smart
Route::post('/payment-process-terminal', [PaymentController::class, 'processTerminalPayment'])
    ->name('api.payment.process-terminal');

// ✨ NUEVO: Estado de pago
Route::get('/payment-status/{payment_ticket_id}', [PaymentController::class, 'getPaymentStatus'])
    ->name('api.payment.status');

// Refund (EXISTENTE)
Route::post('/payment-refund/{payment_ticket_id}', [PaymentController::class, 'refundPayment'])
    ->name('api.payment.refund');

// Webhook para pagos
Route::post('/webhooks/payment/{hash}', [WebhookController::class, 'handlePayment'])
    ->name('api.webhook.payment')
    ->withoutMiddleware(['auth:sanctum']);

*/

/**
 * MÉTODOS A IMPLEMENTAR EN PaymentController.php
 * 
 * Los siguientes métodos deben agregarse/completarse:
 */

class PaymentControllerRequirements
{
    /**
     * ✨ NUEVO - Procesar pago por QR
     * 
     * POST /api/payment-process-qr
     * 
     * Request:
     * {
     *   "payment_provider_id": 1,
     *   "screening_id": 123,
     *   "seat_ids": [1, 2, 3],
     *   "total_price": 500.00,
     *   "seat_count": 3
     * }
     * 
     * Response:
     * {
     *   "success": true,
     *   "method": "qr",
     *   "qr_type": "native|custom",
     *   "qr_data": "...",
     *   "payment_ticket_id": 456,
     *   "amount": 500.00
     * }
     */
    public function processQrPayment(Request $request)
    {
        // Validar request
        // Crear tickets
        // Generar QR usando MercadoPagoQrHandler
        // Retornar datos del QR
    }

    /**
     * ✨ NUEVO - Procesar pago por Terminal Smart
     * 
     * POST /api/payment-process-terminal
     * 
     * Request:
     * {
     *   "payment_provider_id": 1,
     *   "screening_id": 123,
     *   "seat_ids": [1, 2, 3],
     *   "total_price": 500.00,
     *   "seat_count": 3
     * }
     * 
     * Response:
     * {
     *   "success": true,
     *   "method": "terminal",
     *   "terminal_id": "TERMINAL_001",
     *   "order_id": "order_...",
     *   "amount": 500.00,
     *   "payment_ticket_id": 456
     * }
     */
    public function processTerminalPayment(Request $request)
    {
        // Validar request
        // Crear tickets
        // Enviar a terminal usando MercadoPagoPointHandler
        // Retornar ID de orden
    }

    /**
     * ✨ NUEVO - Obtener estado de pago
     * 
     * GET /api/payment-status/{payment_ticket_id}
     * 
     * Response:
     * {
     *   "success": true,
     *   "status": "pending|processing|approved|failed",
     *   "payment_ticket_id": 456,
     *   "transaction_id": "...",
     *   "message": "..."
     * }
     */
    public function getPaymentStatus(int $paymentTicketId)
    {
        // Obtener PaymentProviderTicket
        // Retornar estado actual
        // Soportar polling desde frontend
    }

    /**
     * EXISTENTE pero puede ser mejorado:
     * 
     * Asegúrate de que processBatchPayment() soporte:
     * - Crear múltiples tickets
     * - Calcular precio total correcto
     * - Retornar redirect_url cuando aplique
     * - Manejar errores de asientos duplicados
     */
    public function processBatchPayment(Request $request)
    {
        // Implementación actual en PaymentController.php
    }
}

/**
 * ESTRUCTURA DE DIRECTORIOS ESPERADA
 * 
 * app/
 * └── Http/
 *     ├── Controllers/
 *     │   └── Api/
 *     │       ├── PaymentController.php          [MODIFICADO]
 *     │       └── WebhookController.php          [Si existe]
 *     └── Middleware/
 *         └── [Otros middlewares]
 * 
 * app/
 * └── Services/
 *     ├── PaymentProviders/
 *     │   ├── PaymentProviderManager.php         [MODIFICADO]
 *     │   ├── PaymentProviderHandler.php
 *     │   └── Handlers/
 *     │       ├── MercadoPagoHandler.php
 *     │       ├── MercadoPagoQrHandler.php       [EXISTENTE]
 *     │       ├── MercadoPagoPointHandler.php    [EXISTENTE]
 *     │       ├── PayPalHandler.php
 *     │       └── CashHandler.php
 *     └── PaymentMethods/
 *         └── PaymentMethodService.php           [✨ NUEVO]
 * 
 * resources/js/
 * ├── components/
 * │   ├── QRPaymentCard.vue                     [✨ NUEVO]
 * │   ├── SmartPointCard.vue                    [✨ NUEVO]
 * │   ├── PaymentMethodsSection.vue             [✨ NUEVO]
 * │   └── PaymentMethodSelector.vue             [EXISTENTE]
 * └── services/
 *     └── PaymentMethodService.js               [✨ NUEVO]
 */

/**
 * CONFIGURACIÓN DE MERCADO PAGO
 * 
 * En .env:
 * 
 * MERCADO_PAGO_ACCESS_TOKEN=APP_USR-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
 * MERCADO_PAGO_STORE_ID=12345678
 * MERCADO_PAGO_TERMINAL_ID=TERMINAL_001
 * MERCADO_PAGO_PUBLIC_KEY=APP_USR-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
 * 
 * O en config/services.php:
 * 
 * 'mercado_pago' => [
 *     'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
 *     'store_id' => env('MERCADO_PAGO_STORE_ID'),
 *     'terminal_id' => env('MERCADO_PAGO_TERMINAL_ID'),
 *     'public_key' => env('MERCADO_PAGO_PUBLIC_KEY'),
 * ],
 */

/**
 * PACKAGE.JSON - DEPENDENCIAS NECESARIAS
 * 
 * npm install qrcode
 * 
 * O agregar a package.json:
 * {
 *   "devDependencies": {
 *     "qrcode": "^1.5.3"
 *   }
 * }
 */

/**
 * OPCIONES DE COMPILACIÓN (vite.config.js)
 * 
 * Ya está configurado, pero si necesitas agregar alias o plugins:
 * 
 * import { defineConfig } from 'vite';
 * import laravel from 'laravel-vite-plugin';
 * import vue from '@vitejs/plugin-vue';
 * 
 * export default defineConfig({
 *   plugins: [
 *     laravel({
 *       input: ['resources/css/app.css', 'resources/js/app.js'],
 *       refresh: true,
 *     }),
 *     vue(),
 *   ],
 *   resolve: {
 *     alias: {
 *       '@': new URL('./resources/js', import.meta.url).pathname,
 *     },
 *   },
 * });
 */
