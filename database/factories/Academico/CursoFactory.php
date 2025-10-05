<?php

namespace Database\Factories\Academico;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class CursoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre'    => fake()->randomElement(['Soldadura','Motos','Carros']),
            'user_id' => User::all()->random()->id, // Asigna un ID de un Usuario existente aleatoriamente
            'status'    => fake()->randomElement([0,1]),
        ];
    }
}
