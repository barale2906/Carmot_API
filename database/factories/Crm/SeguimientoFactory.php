<?php

namespace Database\Factories\Crm;

use App\Models\Crm\Referido;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Crm\Seguimiento>
 */
class SeguimientoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referido_id'   => Referido::all()->random()->id,
            'seguidor_id'   => User::all()->random()->id,
            'fecha'         => fake()->date(),
            'seguimiento'   => fake()->paragraph(),
        ];
    }
}
