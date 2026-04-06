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
    $router->post('maintenance/regenerate-order-tickets', 'HomeController@regenerateOrderTickets')->name('maintenance.regenerate-order-tickets');

    // Cinemas
    $router->resource('cinemas', 'CinemaController');

    // Movies
    $router->resource('movies', 'MovieController');
    $router->get('movies/{movie}/weekly-screenings', 'MovieController@showWeeklyScreeningsForm')
        ->name('movies.weekly-screenings.form');
    $router->post('movies/{movie}/weekly-screenings', 'MovieController@storeWeeklyScreenings')
        ->name('movies.weekly-screenings.store');

    // Rooms
    $router->resource('rooms', 'RoomController');
    $router->get('rooms/{room}/generate-seats', 'RoomController@generateSeats')->name('rooms.generate-seats');
    $router->get('rooms/{room}/sync-screenings', 'RoomController@syncScreenings')->name('rooms.sync-screenings');

    // Screenings
    $router->resource('screenings', 'ScreeningController');
    $router->post('screenings/movie-rooms-options', 'ScreeningController@movieRoomsOptions')->name('screenings.movie-rooms.options');
    $router->post('screenings/room-seats-options', 'ScreeningController@roomSeatsOptions')->name('screenings.room-seats.options');
    $router->get('screenings/export/excel', 'ScreeningController@exportExcel')->name('screenings.export.excel');
    $router->get('screenings/export/csv', 'ScreeningController@exportCsv')->name('screenings.export.csv');
    $router->get('screenings/import/form', 'ScreeningController@showImportForm')->name('screenings.import.form');
    $router->post('screenings/import', 'ScreeningController@importFile')->name('screenings.import');
    $router->get('screenings/weekly-screenings', 'ScreeningController@showWeeklyScreeningsForm')
        ->name('screenings.weekly-screenings.form');
    $router->post('screenings/weekly-screenings', 'ScreeningController@storeWeeklyScreenings')
        ->name('screenings.weekly-screenings.store');

    // Tickets (resource completo - CRUD)
    $router->resource('tickets', 'TicketController');

    // Reservations (screening_seats reserved inventory)
    $router->resource('reservations', 'ReservationController');

    // Orders (general overview)
    $router->resource('orders', 'OrderController');
    $router->get('orders/{order}/sync', 'OrderController@sync')->name('orders.sync');
    
    // Manual order creation
    $router->get('orders/manual/create', 'OrderController@showManualCreateForm')->name('orders.manual.create');
    $router->post('orders/manual/api/movies', 'OrderController@apiGetMovies')->name('orders.api.movies');
    $router->post('orders/manual/api/screenings', 'OrderController@apiGetScreenings')->name('orders.api.screenings');
    $router->post('orders/manual/api/seating-chart', 'OrderController@apiGetSeatingChart')->name('orders.api.seating-chart');
    $router->post('orders/manual/api/store', 'OrderController@apiStoreManualOrder')->name('orders.api.store');
    
    // Rutas personalizadas para editar details desde tickets
    $router->put('tickets/{ticket}/details/{detail}', 'TicketController@updateDetail')->name('tickets.details.update');
    $router->delete('tickets/{ticket}/details/{detail}', 'TicketController@cancelDetail')->name('tickets.details.cancel');
    
    // Ruta para anular un ticket
    $router->post('tickets/{ticket}/cancel', 'TicketController@cancel')->name('tickets.cancel');
    $router->get('tickets/{ticket}/thermal-pdf', 'TicketController@thermalPdf')->name('tickets.thermal-pdf');
    
    // Ticket Details (anidado bajo Tickets)
    $router->resource('ticket-details', 'TicketDetailController');

    // Payment Providers
    $router->resource('payment-providers', 'PaymentProviderController');
    $router->post('payment-providers/mp-terminals', 'PaymentProviderController@getMercadoPagoTerminals')->name('payment-providers.mp-terminals');
    $router->post('payment-providers/mp-pos', 'PaymentProviderController@getMercadoPagoPos')->name('payment-providers.mp-pos');
    $router->post('payment-providers/mp-create-store', 'PaymentProviderController@createMercadoPagoStore')->name('payment-providers.mp-create-store');
    $router->post('payment-providers/mp-create-pos', 'PaymentProviderController@createMercadoPagoPos')->name('payment-providers.mp-create-pos');
    $router->patch('payment-providers/mp-update-operation-mode', 'PaymentProviderController@updateMercadoPagoOperationMode')->name('payment-providers.mp-update-operation-mode');
    $router->post('payment-providers/mp-update-operation-mode', 'PaymentProviderController@updateMercadoPagoOperationMode');

});
