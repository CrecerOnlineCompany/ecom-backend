<?php

namespace Database\Factories;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Movie>
 */
class MovieFactory extends Factory
{
    protected $model = Movie::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $genres = ['Acción', 'Drama', 'Comedia', 'Terror', 'Ciencia Ficción', 'Romance', 'Thriller'];
        $ratings = ['G', 'PG', 'PG-13', 'R', 'NC-17'];

        return [
            'title' => $this->faker->unique()->sentence(3),
            'description' => $this->faker->paragraph(),
            'genre' => $this->faker->randomElement($genres),
            'duration' => $this->faker->numberBetween(90, 180),
            'rating' => $this->faker->randomElement($ratings),
            'director' => $this->faker->name(),
            'cast' => implode(', ', [$this->faker->name(), $this->faker->name(), $this->faker->name()]),
            'language' => 'es',
            'poster_url' => 'https://via.placeholder.com/300x450',
            'trailer_url' => 'https://youtube.com/watch?v=placeholder',
            'release_date' => $this->faker->dateTimeBetween('-30 days', '+30 days'),
            'end_date' => $this->faker->dateTimeBetween('+30 days', '+60 days'),
            'is_active' => true,
        ];
    }
}
