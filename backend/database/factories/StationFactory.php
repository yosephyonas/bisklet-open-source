<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Station>
 */
class StationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->streetName() . ' Station',
            'code' => strtoupper(fake()->bothify('ST##??')),
            'description' => fake()->sentence(8),
            'latitude' => fake()->latitude(8.8, 9.1),
            'longitude' => fake()->longitude(38.6, 39.0),
            'capacity' => fake()->numberBetween(10, 40),
            'available_docks' => fake()->numberBetween(0, 10),
            'status' => fake()->randomElement(['active', 'inactive']),
            'metadata' => [
                'landmark' => fake()->streetAddress(),
            ],
        ];
    }
}
