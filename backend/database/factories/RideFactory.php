<?php

namespace Database\Factories;

use App\Enums\RideStatus;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ride>
 */
class RideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = now()->subMinutes(fake()->numberBetween(30, 300));
        $endedAt = (clone $startedAt)->addMinutes(fake()->numberBetween(5, 45));
        $duration = $endedAt->diffInSeconds($startedAt);

        return [
            'user_id' => User::factory(),
            'bike_id' => null,
            'start_station_id' => Station::factory(),
            'end_station_id' => Station::factory(),
            'status' => fake()->randomElement([
                RideStatus::Completed->value,
                RideStatus::Cancelled->value,
                RideStatus::Failed->value,
            ]),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_seconds' => $duration,
            'distance_m' => fake()->randomFloat(2, 500, 15000),
            'unlock_fee' => config('pricing.rides.unlock_fee', 5),
            'per_minute_rate' => fake()->randomFloat(2, 1, 3),
            'total_amount' => fake()->randomFloat(2, 30, 150),
            'currency' => 'ETB',
            'start_latitude' => fake()->latitude(8.8, 9.1),
            'start_longitude' => fake()->longitude(38.6, 39.0),
            'end_latitude' => fake()->latitude(8.8, 9.1),
            'end_longitude' => fake()->longitude(38.6, 39.0),
            'pricing_breakdown' => [
                'base' => 10,
                'time' => 20,
            ],
            'telemetry' => [
                'average_speed_kph' => fake()->randomFloat(2, 10, 30),
            ],
        ];
    }
}
