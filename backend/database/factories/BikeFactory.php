<?php

namespace Database\Factories;

use App\Enums\BikeStatus;
use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bike>
 */
class BikeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'station_id' => Station::factory(),
            'code' => strtoupper(fake()->bothify('BK##??')),
            'model' => fake()->randomElement(['Bajaj Swift', 'Dash City', 'Urban Rider']),
            'type' => fake()->randomElement(['standard', 'electric']),
            'status' => BikeStatus::Available->value,
            'is_active' => true,
            'battery_level' => fake()->randomFloat(2, 40, 100),
            'odometer_km' => fake()->randomFloat(2, 0, 5000),
            'price_per_minute' => config('pricing.rides.per_minute_rate', 0.5),
            'unlock_fee' => config('pricing.rides.unlock_fee', 5),
            'last_service_at' => now()->subDays(fake()->numberBetween(1, 60)),
            'hardware' => [
                'controller' => fake()->uuid(),
            ],
            'metadata' => [
                'color' => fake()->safeColorName(),
            ],
            'last_latitude' => fake()->latitude(),
            'last_longitude' => fake()->longitude(),
            'location_accuracy' => fake()->randomFloat(2, 1, 25),
            'location_updated_at' => now()->subMinutes(fake()->numberBetween(1, 120)),
        ];
    }
}
