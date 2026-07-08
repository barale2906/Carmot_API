<?php

namespace Database\Factories\Academico;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\TipoAplazamiento>
 */
class TipoAplazamientoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre'      => 'Tipo-' . $this->faker->unique()->numerify('####'),
            'descripcion' => $this->faker->optional(0.7)->sentence(),
            'status'      => 1,
        ];
    }
}
