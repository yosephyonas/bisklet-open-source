<?php

namespace Database\Factories;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'national_id' => fake()->unique()->numerify('#############'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'father_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone' => fake()->numerify('+2519########'),
            'date_of_birth' => fake()->date(),
            'role' => UserRole::Rider->value,
            'fyda_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'metadata' => [
                'preferred_language' => fake()->randomElement(['am', 'en', 'om']),
            ],
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'fyda_verified_at' => null,
        ]);
    }
}
