<?php

namespace Database\Factories\Crm;

use App\Models\Academico\Curso;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\odel=Referido>
 */
class ReferidoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'curso_id' => Curso::all()->random()->id, // Asigna un ID de un Curso existente aleatoriamente
            'gestor_id' => User::all()->random()->id, // Asigna un ID de un Usuario existente aleatoriamente
            'nombre' => fake()->name(),
            'celular' => fake()->unique()->phoneNumber(),
            'ciudad' => fake()->city(),
            'status'    => fake()->randomElement([0,1,2,3,4]),
        ];
    }
}
