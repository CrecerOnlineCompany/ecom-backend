<?php

use Illuminate\Routing\Router;

Admin::routes();

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

    // Tickets (read-only)
    $router->get('tickets', 'TicketController@index')->name('tickets.index');
    $router->get('tickets/{id}', 'TicketController@show')->name('tickets.show');

});
