<?php

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
    'as'            => config('admin.route.prefix') . '.',
], function (Router $router) {

    $router->get('/', 'HomeController@index')->name('home');

    // Cinemas
    $router->resource('cinemas', 'CinemaController');

    // Movies
    $router->resource('movies', 'MovieController');

    // Rooms
    $router->resource('rooms', 'RoomController');

    // Screenings
    $router->resource('screenings', 'ScreeningController');
    $router->get('screenings/export/excel', 'ScreeningController@exportExcel')->name('screenings.export.excel');
    $router->get('screenings/export/csv', 'ScreeningController@exportCsv')->name('screenings.export.csv');
    $router->get('screenings/import/form', 'ScreeningController@showImportForm')->name('screenings.import.form');
    $router->post('screenings/import', 'ScreeningController@importFile')->name('screenings.import');

    // Tickets (resource completo - CRUD)
    $router->resource('tickets', 'TicketController');
    
    // Rutas personalizadas para editar details desde tickets
    $router->put('tickets/{ticket}/details/{detail}', 'TicketController@updateDetail')->name('tickets.details.update');
    $router->delete('tickets/{ticket}/details/{detail}', 'TicketController@cancelDetail')->name('tickets.details.cancel');
    
    // Ticket Details (anidado bajo Tickets)
    $router->resource('ticket-details', 'TicketDetailController');

    // Payment Providers
    $router->resource('payment-providers', 'PaymentProviderController');
    $router->post('payment-providers/mp-terminals', 'PaymentProviderController@getMercadoPagoTerminals')->name('payment-providers.mp-terminals');
    $router->post('payment-providers/mp-pos', 'PaymentProviderController@getMercadoPagoPos')->name('payment-providers.mp-pos');

});
