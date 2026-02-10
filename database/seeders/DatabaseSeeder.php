<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Cinema;
use App\Models\Room;
use App\Models\Movie;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ejecutar seeder de menús admin
        $this->call(AdminMenuSeeder::class);
        $this->call(PaymentProviderSeeder::class);

        // Crear usuarios de prueba
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Crear cines
        $cinema1 = Cinema::create([
            'name' => 'Cine Premium Downtown',
            'city' => 'Madrid',
            'address' => 'Calle Principal 123',
            'phone' => '+34 91 123 4567',
            'email' => 'info@cinepremium.es',
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'description' => 'Cine de última generación en el centro de la ciudad',
            'is_active' => true,
        ]);

        $cinema2 = Cinema::create([
            'name' => 'Cine Plaza Centro',
            'city' => 'Barcelona',
            'address' => 'Paseo de Gracia 456',
            'phone' => '+34 93 456 7890',
            'email' => 'info@cineplaza.es',
            'latitude' => 41.3851,
            'longitude' => 2.1734,
            'description' => 'Cine moderno con experiencia inmersiva',
            'is_active' => true,
        ]);

        $cinema3 = Cinema::create([
            'name' => 'Cine Torrefiel',
            'city' => 'Valencia',
            'address' => 'Avenida del Tecnólogo 789',
            'phone' => '+34 96 123 4567',
            'email' => 'info@cinetorrefiel.es',
            'latitude' => 39.4699,
            'longitude' => -0.3763,
            'description' => 'Cine con salas IMAX y 4DX',
            'is_active' => true,
        ]);

        // Crear salas
        for ($i = 1; $i <= 3; $i++) {
            $rows = 6;
            $columns = 5;
            $room = Room::create([
                'cinema_id' => $cinema1->id,
                'number' => (string)$i,
                'name' => 'Sala ' . $i,
                'total_seats' => $rows * $columns,
                'type' => $i % 2 == 0 ? '3D' : '2D',
                'rows' => $rows,
                'columns' => $columns,
                'is_active' => true,
            ]);

            // Generar asientos
            $this->generateSeats($room);
        }

        for ($i = 1; $i <= 2; $i++) {
            $rows = 7;
            $columns = 6;
            $room = Room::create([
                'cinema_id' => $cinema2->id,
                'number' => (string)$i,
                'name' => 'Sala ' . $i,
                'total_seats' => $rows * $columns,
                'type' => 'IMAX',
                'rows' => $rows,
                'columns' => $columns,
                'is_active' => true,
            ]);

            $this->generateSeats($room);
        }

        for ($i = 1; $i <= 2; $i++) {
            $rows = 4;
            $columns = 5;
            $room = Room::create([
                'cinema_id' => $cinema3->id,
                'number' => (string)$i,
                'name' => 'Sala ' . $i,
                'total_seats' => $rows * $columns,
                'type' => $i == 1 ? '4DX' : '3D',
                'rows' => $rows,
                'columns' => $columns,
                'is_active' => true,
            ]);

            $this->generateSeats($room);
        }

        // Crear películas
        $movies = [
            [
                'title' => 'Dune: Parte Dos',
                'description' => 'La épica aventura continúa en el planeta Arrakis',
                'genre' => 'Ciencia Ficción',
                'duration' => 166,
                'rating' => 'PG-13',
                'director' => 'Denis Villeneuve',
                'cast' => 'Timothée Chalamet, Zendaya, Oscar Isaac',
                'language' => 'es',
                'release_date' => now()->subDays(20),
                'end_date' => now()->addDays(40),
            ],
            [
                'title' => 'Oppenheimer',
                'description' => 'La historia del padre de la bomba atómica',
                'genre' => 'Drama',
                'duration' => 180,
                'rating' => 'R',
                'director' => 'Christopher Nolan',
                'cast' => 'Cillian Murphy, Robert Downey Jr',
                'language' => 'es',
                'release_date' => now()->subDays(30),
                'end_date' => now()->addDays(50),
            ],
            [
                'title' => 'Killers of the Flower Moon',
                'description' => 'Un thriller sobre crímenes en Oklahoma',
                'genre' => 'Thriller',
                'duration' => 206,
                'rating' => 'R',
                'director' => 'Martin Scorsese',
                'cast' => 'Leonardo DiCaprio, Robert De Niro',
                'language' => 'es',
                'release_date' => now()->subDays(15),
                'end_date' => now()->addDays(60),
            ],
            [
                'title' => 'Barbie',
                'description' => 'Una aventura en el mundo de Barbie',
                'genre' => 'Comedia',
                'duration' => 114,
                'rating' => 'PG',
                'director' => 'Greta Gerwig',
                'cast' => 'Margot Robbie, Ryan Gosling',
                'language' => 'es',
                'release_date' => now()->subDays(5),
                'end_date' => now()->addDays(70),
            ],
            [
                'title' => 'Guardians of the Galaxy Vol. 3',
                'description' => 'La final de los Guardianes de la Galaxia',
                'genre' => 'Acción',
                'duration' => 150,
                'rating' => 'PG-13',
                'director' => 'James Gunn',
                'cast' => 'Chris Pratt, Zoe Saldana',
                'language' => 'es',
                'release_date' => now()->addDays(5),
                'end_date' => now()->addDays(80),
            ],
        ];

        foreach ($movies as $movieData) {
            Movie::create($movieData);
        }

        // Crear funciones (screenings)
        $rooms = Room::all();
        $movies = Movie::all();

        foreach ($movies as $movie) {
            foreach ($rooms->random(min(2, $rooms->count())) as $room) {
                // 2-3 funciones por día durante 7 días
                for ($day = 0; $day < 7; $day++) {
                    $times = ['14:30', '17:00', '19:30', '22:00'];
                    foreach ($times as $time) {
                        [$hours, $minutes] = explode(':', $time);
                        $startTime = now()->addDays($day)->setTime((int)$hours, (int)$minutes);
                        
                        if ($startTime > now()) {
                            $endTime = (clone $startTime)->addMinutes($movie->duration);
                            
                            Screening::create([
                                'movie_id' => $movie->id,
                                'room_id' => $room->id,
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                                'price' => 8.00,
                                'available_seats' => $room->total_seats,
                                'format' => $room->type,
                                'is_active' => true,
                            ]);
                        }
                    }
                }
            }
        }

        // Crear tickets de prueba con ticket_details
        $this->createSampleTickets();
    }

    private function createSampleTickets(): void
    {
        $users = User::all();
        $screenings = Screening::with('movie', 'room.cinema')->take(5)->get();

        foreach ($screenings as $screening) {
            // Obtener todos los asientos de la sala
            $allSeats = $screening->room->seats()->get();
            $usedSeats = [];

            // Crear 2-3 tickets por función
            for ($i = 0; $i < rand(2, 3); $i++) {
                $user = $users->random();
                $numSeats = rand(1, 4); // 1-4 asientos por ticket
                
                // Obtener asientos que no hayamos usado en esta función
                $availableSeats = $allSeats->filter(function ($seat) use ($usedSeats) {
                    return !in_array($seat->id, $usedSeats);
                })->take($numSeats);

                if ($availableSeats->isEmpty()) continue;

                $totalPrice = 0;
                $details = [];

                // Calcular precio total y preparar detalles
                foreach ($availableSeats as $seat) {
                    $seatPrice = $screening->price * (1 + $seat->price_modifier);
                    $totalPrice += $seatPrice;
                    $usedSeats[] = $seat->id; // Marcar como usada
                    
                    $details[] = [
                        'seat_id' => $seat->id,
                        'seat_code' => $seat->seat_code,
                        'row_number' => $seat->row_number,
                        'seat_number' => $seat->seat_number,
                        'price' => $seatPrice,
                    ];
                }

                // Crear ticket con datos desnormalizados
                $status = ['confirmed', 'pending_payment'][rand(0, 1)];
                $ticket = \App\Models\Ticket::create([
                    'screening_id' => $screening->id,
                    'user_id' => $user->id,
                    'seat_id' => null, // Ya no usamos esto, tenemos ticket_details
                    'ticket_number' => 'TKT-' . date('Ymd') . '-' . strtoupper(Str::random(8)),
                    'price' => $totalPrice,
                    'status' => $status,
                    'payment_method' => ['Tarjeta de Crédito', 'MercadoPago', 'PayPal'][rand(0, 2)],
                    'original_price' => $totalPrice,
                    'discount_amount' => 0,
                    'purchased_at' => now()->subDays(rand(0, 30)),
                    // Datos desnormalizados
                    'customer_name' => $user->name,
                    'customer_email' => $user->email,
                    'customer_phone' => $user->phone ?? '+34 ' . rand(600000000, 699999999),
                    'movie_title' => $screening->movie->title,
                    'room_name' => $screening->room->name,
                    'cinema_name' => $screening->room->cinema->name,
                    'screening_start_time' => $screening->start_time,
                    'screening_format' => $screening->format,
                ]);

                // Crear ticket_details para cada asiento
                foreach ($details as $detail) {
                    \App\Models\TicketDetail::create([
                        'ticket_id' => $ticket->id,
                        'screening_id' => $screening->id,
                        'seat_id' => $detail['seat_id'],
                        'seat_code' => $detail['seat_code'],
                        'row_number' => $detail['row_number'],
                        'seat_number' => $detail['seat_number'],
                        'price' => $detail['price'],
                        'status' => $status === 'confirmed' ? 'confirmed' : 'pending',
                        'qr_code' => hash('sha256', $ticket->ticket_number . '-' . $detail['seat_code']),
                        'used_at' => $status === 'confirmed' && rand(0, 1) ? now()->subDays(rand(0, 5)) : null,
                    ]);
                }
            }
        }
    }

    private function generateSeats(Room $room): void
    {
        $rows = $room->rows;
        $columns = $room->columns;

        for ($row = 1; $row <= $rows; $row++) {
            $rowLetter = chr(64 + $row); // A, B, C, etc.

            for ($col = 1; $col <= $columns; $col++) {
                $seatCode = $rowLetter . $col;

                $room->seats()->create([
                    'row_number' => $row,
                    'seat_number' => $col,
                    'seat_code' => $seatCode,
                    'type' => $row == 1 || $row == $rows ? 'vip' : 'standard',
                    'price_modifier' => $row == 1 || $row == $rows ? 1.5 : 1.0,
                ]);
            }
        }
    }
}
