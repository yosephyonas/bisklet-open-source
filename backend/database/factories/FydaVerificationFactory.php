<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FydaVerification>
 */
class FydaVerificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'national_id' => fake()->unique()->numerify('#############'),
            'status' => fake()->randomElement(['pending', 'verified', 'failed', 'error']),
            'request_payload' => [
                'national_id' => fake()->unique()->numerify('#############'),
            ],
            'response_payload' => [
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
            ],
            'error_message' => null,
            'verified_at' => now(),
        ];
    }
}
