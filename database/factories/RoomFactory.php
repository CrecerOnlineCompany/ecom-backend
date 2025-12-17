<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\Cinema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $roomNumbers = [];
        
        $rows = $this->faker->numberBetween(10, 20);
        $columns = $this->faker->numberBetween(15, 25);
        $types = ['2D', '3D', 'IMAX'];

        return [
            'cinema_id' => Cinema::factory(),
            'number' => (string)$this->faker->unique()->numberBetween(1, 50),
            'name' => 'Sala ' . $this->faker->numberBetween(1, 10),
            'total_seats' => $rows * $columns,
            'type' => $this->faker->randomElement($types),
            'rows' => $rows,
            'columns' => $columns,
            'description' => $this->faker->optional()->paragraph(),
            'is_active' => true,
        ];
    }
}
