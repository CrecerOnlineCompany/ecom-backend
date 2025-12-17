<?php

/**
 * Configuración de API endpoints para el sistema de Cine
 * 
 * Este archivo documenta todos los endpoints disponibles en la API RESTful
 * para gestionar cines, películas, salas, funciones y entradas.
 */

return [
    'api' => [
        'prefix' => 'api',
        'version' => 'v1',
        
        'endpoints' => [
            // Cines - Cinema Management
            'cinemas' => [
                'list' => [
                    'method' => 'GET',
                    'uri' => '/api/cinemas',
                    'description' => 'Obtener lista de cines activos',
                    'auth' => false,
                    'response' => 'Array de cines con salas'
                ],
                'show' => [
                    'method' => 'GET',
                    'uri' => '/api/cinemas/{id}',
                    'description' => 'Obtener detalles de un cine específico',
                    'auth' => false,
                    'response' => 'Objeto cine con sus salas y funciones'
                ],
                'create' => [
                    'method' => 'POST',
                    'uri' => '/api/cinemas',
                    'description' => 'Crear nuevo cine',
                    'auth' => true,
                    'body' => [
                        'name' => 'string|unique',
                        'city' => 'string|required',
                        'address' => 'string|required',
                        'phone' => 'string|optional',
                        'email' => 'email|optional',
                        'latitude' => 'numeric|optional',
                        'longitude' => 'numeric|optional',
                        'description' => 'text|optional',
                        'is_active' => 'boolean|default:true'
                    ]
                ],
                'update' => [
                    'method' => 'PUT',
                    'uri' => '/api/cinemas/{id}',
                    'description' => 'Actualizar cine existente',
                    'auth' => true,
                    'body' => 'Mismo formato que create, pero todos opcionales'
                ],
                'delete' => [
                    'method' => 'DELETE',
                    'uri' => '/api/cinemas/{id}',
                    'description' => 'Eliminar cine',
                    'auth' => true,
                ]
            ],

            // Películas - Movie Management
            'movies' => [
                'list' => [
                    'method' => 'GET',
                    'uri' => '/api/movies',
                    'description' => 'Obtener lista de películas',
                    'auth' => false,
                    'filters' => [
                        'is_active' => 'boolean',
                        'genre' => 'string',
                        'per_page' => 'integer|default:15'
                    ]
                ],
                'show' => [
                    'method' => 'GET',
                    'uri' => '/api/movies/{id}',
                    'description' => 'Obtener detalles de película con sus funciones',
                    'auth' => false,
                ],
                'create' => [
                    'method' => 'POST',
                    'uri' => '/api/movies',
                    'description' => 'Crear nueva película',
                    'auth' => true,
                    'body' => [
                        'title' => 'string|unique|required',
                        'description' => 'text|optional',
                        'genre' => 'string|required',
                        'duration' => 'integer|required|min:1',
                        'rating' => 'string|optional',
                        'director' => 'string|optional',
                        'cast' => 'string|optional',
                        'language' => 'string|default:es',
                        'poster_url' => 'url|optional',
                        'trailer_url' => 'url|optional',
                        'release_date' => 'date|required',
                        'end_date' => 'date|optional|after:release_date',
                        'is_active' => 'boolean|default:true'
                    ]
                ],
                'update' => [
                    'method' => 'PUT',
                    'uri' => '/api/movies/{id}',
                    'description' => 'Actualizar película',
                    'auth' => true,
                ],
                'delete' => [
                    'method' => 'DELETE',
                    'uri' => '/api/movies/{id}',
                    'description' => 'Eliminar película',
                    'auth' => true,
                ]
            ],

            // Salas - Room Management
            'rooms' => [
                'list' => [
                    'method' => 'GET',
                    'uri' => '/api/rooms',
                    'description' => 'Obtener lista de salas',
                    'auth' => false,
                    'filters' => [
                        'cinema_id' => 'integer',
                    ]
                ],
                'show' => [
                    'method' => 'GET',
                    'uri' => '/api/rooms/{id}',
                    'description' => 'Obtener detalles de sala con sus asientos',
                    'auth' => false,
                ],
                'create' => [
                    'method' => 'POST',
                    'uri' => '/api/rooms',
                    'description' => 'Crear nueva sala',
                    'auth' => true,
                    'body' => [
                        'cinema_id' => 'integer|required|exists:cinemas',
                        'number' => 'string|required',
                        'name' => 'string|required',
                        'total_seats' => 'integer|required|min:1',
                        'type' => 'string|required',
                        'rows' => 'integer|required|min:1',
                        'columns' => 'integer|required|min:1',
                        'description' => 'text|optional',
                        'is_active' => 'boolean|default:true'
                    ]
                ],
                'update' => [
                    'method' => 'PUT',
                    'uri' => '/api/rooms/{id}',
                    'description' => 'Actualizar sala',
                    'auth' => true,
                ],
                'delete' => [
                    'method' => 'DELETE',
                    'uri' => '/api/rooms/{id}',
                    'description' => 'Eliminar sala',
                    'auth' => true,
                ]
            ],

            // Funciones - Screening Management
            'screenings' => [
                'list' => [
                    'method' => 'GET',
                    'uri' => '/api/screenings',
                    'description' => 'Obtener lista de funciones',
                    'auth' => false,
                    'filters' => [
                        'movie_id' => 'integer',
                        'cinema_id' => 'integer',
                        'date' => 'date|format:Y-m-d',
                        'per_page' => 'integer|default:20'
                    ]
                ],
                'show' => [
                    'method' => 'GET',
                    'uri' => '/api/screenings/{id}',
                    'description' => 'Obtener detalles de función con entradas',
                    'auth' => false,
                ],
                'available_seats' => [
                    'method' => 'GET',
                    'uri' => '/api/screenings/{id}/available-seats',
                    'description' => 'Obtener asientos disponibles de una función',
                    'auth' => false,
                    'response' => [
                        'screening_id' => 'id de la función',
                        'available_seats_count' => 'cantidad de asientos disponibles',
                        'seats' => 'array con detalles de asientos'
                    ]
                ],
                'create' => [
                    'method' => 'POST',
                    'uri' => '/api/screenings',
                    'description' => 'Crear nueva función',
                    'auth' => true,
                    'body' => [
                        'movie_id' => 'integer|required|exists:movies',
                        'room_id' => 'integer|required|exists:rooms',
                        'start_time' => 'datetime|required|format:Y-m-d H:i:s',
                        'end_time' => 'datetime|required|format:Y-m-d H:i:s|after:start_time',
                        'price' => 'numeric|required|min:0.01',
                        'format' => 'string|required',
                        'is_active' => 'boolean|default:true'
                    ]
                ],
                'update' => [
                    'method' => 'PUT',
                    'uri' => '/api/screenings/{id}',
                    'description' => 'Actualizar función',
                    'auth' => true,
                ],
                'delete' => [
                    'method' => 'DELETE',
                    'uri' => '/api/screenings/{id}',
                    'description' => 'Eliminar función',
                    'auth' => true,
                ]
            ],

            // Entradas - Ticket Management
            'tickets' => [
                'list' => [
                    'method' => 'GET',
                    'uri' => '/api/tickets',
                    'description' => 'Obtener entradas del usuario autenticado',
                    'auth' => true,
                    'filters' => [
                        'per_page' => 'integer|default:10'
                    ]
                ],
                'show' => [
                    'method' => 'GET',
                    'uri' => '/api/tickets/{id}',
                    'description' => 'Obtener detalles de entrada específica',
                    'auth' => true,
                ],
                'purchase' => [
                    'method' => 'POST',
                    'uri' => '/api/tickets',
                    'description' => 'Comprar entradas',
                    'auth' => true,
                    'body' => [
                        'screening_id' => 'integer|required|exists:screenings',
                        'seat_ids' => 'array|required|min:1',
                        'seat_ids.*' => 'integer|exists:seats',
                    ],
                    'response' => [
                        'message' => 'Mensaje de confirmación',
                        'tickets' => 'Array de entradas creadas'
                    ]
                ],
                'cancel' => [
                    'method' => 'DELETE',
                    'uri' => '/api/tickets/{id}',
                    'description' => 'Cancelar entrada',
                    'auth' => true,
                    'notes' => 'No se puede cancelar si la función ya pasó'
                ],
                'screening_tickets' => [
                    'method' => 'GET',
                    'uri' => '/api/screenings/{screening_id}/my-tickets',
                    'description' => 'Obtener entradas del usuario para una función específica',
                    'auth' => true,
                ]
            ]
        ]
    ],

    'admin' => [
        'prefix' => 'admin',
        
        'modules' => [
            'cinemas' => [
                'controller' => 'CinemaController',
                'model' => 'App\Models\Cinema',
                'title' => 'Cines',
                'icon' => 'fa-building',
                'features' => ['create', 'read', 'update', 'delete']
            ],
            'movies' => [
                'controller' => 'MovieController',
                'model' => 'App\Models\Movie',
                'title' => 'Películas',
                'icon' => 'fa-film',
                'features' => ['create', 'read', 'update', 'delete']
            ],
            'rooms' => [
                'controller' => 'RoomController',
                'model' => 'App\Models\Room',
                'title' => 'Salas',
                'icon' => 'fa-chair',
                'features' => ['create', 'read', 'update', 'delete']
            ],
            'screenings' => [
                'controller' => 'ScreeningController',
                'model' => 'App\Models\Screening',
                'title' => 'Funciones',
                'icon' => 'fa-clock-o',
                'features' => ['create', 'read', 'update', 'delete']
            ],
            'tickets' => [
                'controller' => 'TicketController',
                'model' => 'App\Models\Ticket',
                'title' => 'Entradas',
                'icon' => 'fa-ticket',
                'features' => ['read']
            ]
        ]
    ]
];
