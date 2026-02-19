<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use OpenAdmin\Admin\Facades\Admin;

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

Admin::routes();
Route::get('/', function () {
    return view('welcome');
});

// Todas las rutas excepto admin van a welcome
Route::get('{any}', function () {
    return view('welcome');
})->where('any', '^(?!admin|api|auth).*')->name('catch-all');
