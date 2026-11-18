<?php

namespace Database\Factories;

use App\Models\Bike;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaintenanceLog>
 */
class MaintenanceLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bike_id' => Bike::factory(),
            'user_id' => User::factory()->state(fn () => ['role' => 'mechanic']),
            'type' => fake()->randomElement(['repair', 'inspection', 'battery_swap']),
            'status' => fake()->randomElement(['open', 'in_progress', 'closed']),
            'notes' => fake()->sentence(12),
            'performed_at' => now()->subDays(fake()->numberBetween(0, 30)),
            'cost' => fake()->randomFloat(2, 50, 500),
            'metadata' => [
                'parts' => fake()->words(3),
            ],
        ];
    }
}
