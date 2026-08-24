<?php

use App\Http\Controllers\Auth\WebLoginController;
use App\Http\Controllers\Manage\CategoryController as ManageCategoryController;
use App\Http\Controllers\Manage\OrderController as ManageOrderController;
use App\Http\Controllers\Manage\PaymentMethodController as ManagePaymentMethodController;
use App\Http\Controllers\Manage\ProductImageUploadController;
use App\Http\Controllers\Manage\ProductController as ManageProductController;
use App\Http\Controllers\Manage\ShopSettingController as ManageShopSettingController;
use App\Http\Controllers\Manage\UserController as ManageUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
define('STDIN',fopen("php://stdin","r"));
Route::get('install', function() {
    Artisan::call('migrate',['--force'=>true]);
});
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [WebLoginController::class, 'create'])->name('login');
    Route::post('/login', [WebLoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [WebLoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::redirect('/shop', '/shop/search');
Route::redirect('/manage', '/admin');

Route::view('/t/{store}/{any?}', 'storefront')
    ->where('any', '.*')
    ->name('storefront');

Route::post('/admin/uploads/product-images', [ProductImageUploadController::class, 'store'])
    ->middleware('auth')
    ->name('admin.product-images.store');

Route::middleware('auth')->prefix('admin/api')->name('admin.api.')->group(function () {
    Route::get('/products', [ManageProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ManageProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}', [ManageProductController::class, 'show'])->name('products.show');
    Route::put('/products/{product}', [ManageProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ManageProductController::class, 'destroy'])->name('products.destroy');
    Route::get('/categories', [ManageCategoryController::class, 'index'])->name('categories.index');
    Route::put('/categories/{category}', [ManageCategoryController::class, 'update'])->name('categories.update');
    Route::get('/orders', [ManageOrderController::class, 'index'])->name('orders.index');
    Route::get('/settings', [ManageShopSettingController::class, 'show'])->name('settings.show');
    Route::put('/settings', [ManageShopSettingController::class, 'update'])->name('settings.update');
    Route::get('/payments', [ManagePaymentMethodController::class, 'index'])->name('payments.index');
    Route::post('/payments', [ManagePaymentMethodController::class, 'store'])->name('payments.store');
    Route::put('/payments/{payment}', [ManagePaymentMethodController::class, 'update'])->name('payments.update');
    Route::get('/users', [ManageUserController::class, 'index'])->name('users.index');
    Route::post('/users', [ManageUserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [ManageUserController::class, 'update'])->name('users.update');
});

Route::view('/admin/{any?}', 'manage')
    ->middleware('auth')
    ->where('any', '.*')
    ->name('admin.manage');

// Aimeos registra /shop y /admin desde el paquete.
Route::get('{any}', function () {
    return view('welcome');
})->where('any', '^(?!admin|aimeos-admin|shop|jsonapi|graphql|api|auth|manage|login|logout|t).*')->name('catch-all');
