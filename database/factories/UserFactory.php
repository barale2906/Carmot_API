<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'primer_nombre'    => fake()->firstName(),
            'segundo_nombre'   => fake()->optional(0.5)->firstName(),
            'primer_apellido'  => fake()->lastName(),
            'segundo_apellido' => fake()->optional(0.7)->lastName(),
            'documento'        => fake()->unique()->numerify('#########'),
            'email'            => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'         => static::$password ??= Hash::make('password'),
            'remember_token'   => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
