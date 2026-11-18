<?php

namespace Database\Factories;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ride_id' => Ride::factory(),
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(['chapa', 'telebirr', 'hellocash']),
            'provider_reference' => fake()->uuid(),
            'amount' => fake()->randomFloat(2, 20, 200),
            'currency' => 'ETB',
            'status' => fake()->randomElement(['pending', 'paid', 'failed']),
            'payload' => [
                'reference' => fake()->bothify('TRX-####'),
            ],
            'metadata' => [
                'channel' => 'mobile',
            ],
            'paid_at' => now()->subMinutes(fake()->numberBetween(0, 120)),
        ];
    }
}
