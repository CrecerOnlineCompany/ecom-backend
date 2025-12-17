<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CinemaController;
use App\Http\Controllers\Api\MovieController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\ScreeningController;
use App\Http\Controllers\Api\TicketController;

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

    // User tickets
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);
    Route::get('/screenings/{screening}/my-tickets', [TicketController::class, 'screeningTickets']);
});
