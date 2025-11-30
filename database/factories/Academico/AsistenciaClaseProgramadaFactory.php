<?php

namespace Database\Factories\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Grupo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\AsistenciaClaseProgramada>
 */
class AsistenciaClaseProgramadaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fechaClase = fake()->dateTimeBetween('-1 month', '+2 months');
        $horaInicio = fake()->time('08:00', '18:00');
        $horaFin = (clone \Carbon\Carbon::parse($horaInicio))->addHours(fake()->numberBetween(1, 4))->format('H:i:s');
        $duracionHoras = \Carbon\Carbon::parse($horaInicio)->diffInMinutes(\Carbon\Carbon::parse($horaFin)) / 60;

        return [
            'grupo_id' => Grupo::inRandomOrder()->first()?->id ?? Grupo::factory(),
            'ciclo_id' => Ciclo::inRandomOrder()->first()?->id ?? Ciclo::factory(),
            'fecha_clase' => $fechaClase,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'duracion_horas' => round($duracionHoras, 2),
            'estado' => fake()->randomElement(['programada', 'dictada', 'cancelada', 'reprogramada']),
            'observaciones' => fake()->optional(0.2)->sentence(),
            'creado_por_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'fecha_programacion' => now(),
        ];
    }

    /**
     * Estado para crear una clase programada.
     *
     * @return static
     */
    public function programada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'programada',
        ]);
    }

    /**
     * Estado para crear una clase dictada.
     *
     * @return static
     */
    public function dictada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'dictada',
        ]);
    }

    /**
     * Estado para crear una clase cancelada.
     *
     * @return static
     */
    public function cancelada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'cancelada',
        ]);
    }

    /**
     * Estado para crear una clase reprogramada.
     *
     * @return static
     */
    public function reprogramada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'reprogramada',
        ]);
    }
}
