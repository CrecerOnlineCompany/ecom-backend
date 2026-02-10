<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CinemaController;
use App\Http\Controllers\Api\MovieController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\ScreeningController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::get('/cinemas', [CinemaController::class, 'index']);
Route::get('/cinemas/{cinema}', [CinemaController::class, 'show']);

Route::get('/movies', [MovieController::class, 'index']);
Route::get('/movies/{movie}', [MovieController::class, 'show']);

Route::get('/rooms', [RoomController::class, 'index']);
Route::get('/rooms/{room}', [RoomController::class, 'show']);

Route::get('/screenings', [ScreeningController::class, 'index']);
Route::get('/screenings/{screening}', [ScreeningController::class, 'show']);
Route::get('/screenings/{screening}/available-seats', [ScreeningController::class, 'availableSeats']);

// Public payment routes
Route::get('/payment-providers', [PaymentController::class, 'index']);
Route::get('/payment-providers/{id}', [PaymentController::class, 'show']);
Route::match(['get', 'post'], '/payment/success', [PaymentController::class, 'success'])->name('api.payment.success');
Route::match(['get', 'post'], '/payment/failure', [PaymentController::class, 'failure'])->name('api.payment.failure');
Route::match(['get', 'post'], '/payment/pending', [PaymentController::class, 'pending'])->name('api.payment.pending');

// Generic webhook endpoint
Route::post('/webhooks/payment/{hash}', [PaymentController::class, 'webhook'])->name('api.webhook.payment');

// Payment processing - Can be called with or without auth
// If auth is present, use authenticated user. If not, use guest checkout
Route::post('/payment-process', [PaymentController::class, 'processPayment']);
Route::post('/payment-process-batch', [PaymentController::class, 'processBatchPayment']);
Route::get('/payment-status/{paymentTicketId}', [PaymentController::class, 'status']);
Route::delete('/payment-cleanup', [PaymentController::class, 'cleanup']);

// Public ticket validation (QR validation)
Route::post('/tickets/validate-qr', [TicketController::class, 'validateByQR']);

// Public ticket endpoints for payment success/confirmation (no auth required)
Route::get('/tickets/{ticketId}/validate', [TicketController::class, 'validateTicketForPayment']);
Route::get('/tickets/{ticketId}/qr', [TicketController::class, 'getTicketQR']);
Route::get('/tickets/{ticketId}/details', [TicketController::class, 'getTicketDetails']);
Route::get('/tickets/{ticketId}/print-thermal', [TicketController::class, 'getThermalPrintFormat']);

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->group(function () {
    // User profile
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Admin routes - Cinema management
    Route::post('/cinemas', [CinemaController::class, 'store']);
    Route::put('/cinemas/{cinema}', [CinemaController::class, 'update']);
    Route::delete('/cinemas/{cinema}', [CinemaController::class, 'destroy']);

    // Admin routes - Movie management
    Route::post('/movies', [MovieController::class, 'store']);
    Route::put('/movies/{movie}', [MovieController::class, 'update']);
    Route::delete('/movies/{movie}', [MovieController::class, 'destroy']);

    // Admin routes - Room management
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::put('/rooms/{room}', [RoomController::class, 'update']);
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy']);

    // Admin routes - Screening management
    Route::post('/screenings', [ScreeningController::class, 'store']);
    Route::put('/screenings/{screening}', [ScreeningController::class, 'update']);
    Route::delete('/screenings/{screening}', [ScreeningController::class, 'destroy']);

    // User tickets - Gestión completa centralizada en TicketController
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::put('/tickets/{ticket}', [TicketController::class, 'update']);
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);
    
    // Ticket details as nested resource (dependen de TicketController)
    Route::get('/tickets/{ticket}/details', [TicketController::class, 'getDetails']);
    Route::put('/tickets/{ticket}/details/{detail}', [TicketController::class, 'updateSeat']);
    Route::delete('/tickets/{ticket}/details/{detail}', [TicketController::class, 'cancelSeat']);
    
    // Screening-specific operations
    Route::get('/screenings/{screening}/my-tickets', [TicketController::class, 'screeningTickets']);
    Route::get('/screenings/{screening}/stats', [TicketController::class, 'screeningStats']);

    // Payment routes - Refund requires auth
    Route::post('/payment-refund/{paymentTicketId}', [PaymentController::class, 'refund']);
});
