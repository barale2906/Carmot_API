<?php

namespace Database\Factories\Academico;

use App\Models\Academico\EsquemaCalificacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para crear tipos de nota de esquema.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\TipoNotaEsquema>
 */
class TipoNotaEsquemaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $esquema = EsquemaCalificacion::inRandomOrder()->first();
        if (!$esquema) {
            $esquema = EsquemaCalificacion::factory()->create();
        }

        $tiposNota = [
            'Parcial 1', 'Parcial 2', 'Parcial 3', 'Parcial 4',
            'Quiz 1', 'Quiz 2', 'Quiz 3',
            'Proyecto Final', 'Proyecto Intermedio',
            'Participación', 'Trabajo Práctico',
            'Examen Final', 'Laboratorio',
        ];

        return [
            'esquema_calificacion_id' => $esquema->id,
            'nombre_tipo' => fake()->randomElement($tiposNota),
            'peso' => fake()->randomFloat(2, 5, 40), // Peso entre 5% y 40%
            'orden' => fake()->numberBetween(1, 10),
            'nota_minima' => 0,
            'nota_maxima' => 5,
            'descripcion' => fake()->optional(0.5)->sentence(),
        ];
    }

    /**
     * Estado para crear un tipo de nota con peso específico.
     *
     * @param float $peso
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conPeso(float $peso): static
    {
        return $this->state(function (array $attributes) use ($peso) {
            return [
                'peso' => $peso,
            ];
        });
    }

    /**
     * Estado para crear un tipo de nota con orden específico.
     *
     * @param int $orden
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conOrden(int $orden): static
    {
        return $this->state(function (array $attributes) use ($orden) {
            return [
                'orden' => $orden,
            ];
        });
    }

    /**
     * Estado para crear un tipo de nota con rango específico.
     *
     * @param float $minima
     * @param float $maxima
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conRango(float $minima, float $maxima): static
    {
        return $this->state(function (array $attributes) use ($minima, $maxima) {
            return [
                'nota_minima' => $minima,
                'nota_maxima' => $maxima,
            ];
        });
    }
}
