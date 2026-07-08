<?php

namespace Database\Factories\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\TipoAplazamiento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\Aplazamiento>
 */
class AplazamientoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fechaInicio  = now()->addDays(10);
        $fechaReinicio = $fechaInicio->copy()->addDays($this->faker->numberBetween(5, 30));

        return [
            'ciclo_id'                => Ciclo::inRandomOrder()->first()?->id ?? Ciclo::factory(),
            'tipo_aplazamiento_id'    => TipoAplazamiento::inRandomOrder()->first()?->id ?? TipoAplazamiento::factory(),
            'user_id'                 => User::inRandomOrder()->first()?->id ?? User::factory(),
            'aplazamiento_padre_id'   => null,
            'fecha_aplazamiento'      => now()->format('Y-m-d'),
            'fecha_inicio_original'   => $fechaInicio->format('Y-m-d'),
            'fecha_reinicio_probable'  => $fechaReinicio->format('Y-m-d'),
            'dias_aplazamiento'        => (int) $fechaInicio->diffInDays($fechaReinicio),
            'mover_cartera'           => false,
            'clases_movidas'          => 0,
            'carteras_movidas'        => 0,
            'observaciones'           => null,
            'estado'                  => 0, // Pendiente
        ];
    }
}
