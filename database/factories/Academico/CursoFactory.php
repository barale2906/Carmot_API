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
            'duracion'  => fake()->randomNumber([100,150,200,250,300]),
            'status'    => fake()->randomElement([0,1]),
        ];
    }
}
