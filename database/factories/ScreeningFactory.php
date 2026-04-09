<?php

namespace Database\Factories;

use App\Models\Screening;
use App\Models\Movie;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Screening>
 */
class ScreeningFactory extends Factory
{
    protected $model = Screening::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = $this->faker->dateTimeBetween('now', '+30 days');
        $movie = Movie::first() ?? Movie::factory()->create();
        $endTime = clone $startTime;
        $endTime->modify('+' . $movie->duration . ' minutes');

        return [
            'movie_id' => $movie->id,
            'room_id' => Room::factory(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'price' => $this->faker->numberBetween(15000, 30000),
            'available_seats' => $this->faker->numberBetween(50, 150),
            'format' => $this->faker->randomElement(['2D', '3D', 'IMAX']),
            'language' => 'espanol',
            'is_active' => true,
        ];
    }
}
