<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Cinema;
use App\Models\Room;
use App\Models\Movie;
use App\Models\Screening;
use App\Models\User;
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
            $rows = 15;
            $columns = 20;
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
            $rows = 12;
            $columns = 18;
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
            $rows = 16;
            $columns = 22;
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
                                'price' => rand(12, 18),
                                'available_seats' => $room->total_seats,
                                'format' => $room->type,
                                'is_active' => true,
                            ]);
                        }
                    }
                }
            }
        }
    }

    private function generateSeats(Room $room): void
    {
        $rows = $room->rows;
        $columns = $room->columns;

        for ($row = 1; $row <= $rows; $row++) {
            $rowLetter = chr(64 + $row);

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
