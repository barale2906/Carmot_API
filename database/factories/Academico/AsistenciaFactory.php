<?php

namespace Database\Factories\Academico;

use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Grupo;
use App\Models\Academico\Modulo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\Asistencia>
 */
class AsistenciaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $claseProgramada = AsistenciaClaseProgramada::inRandomOrder()->first() ?? AsistenciaClaseProgramada::factory()->create();
        
        // Obtener información de la clase programada
        $claseProgramada->load(['grupo', 'ciclo']);
        $grupo = $claseProgramada->grupo;
        $ciclo = $claseProgramada->ciclo;

        return [
            'estudiante_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'clase_programada_id' => $claseProgramada->id,
            'grupo_id' => $grupo->id,
            'ciclo_id' => $ciclo->id,
            'modulo_id' => $grupo->modulo_id ?? Modulo::inRandomOrder()->first()?->id ?? Modulo::factory(),
            'curso_id' => $ciclo->curso_id ?? Curso::inRandomOrder()->first()?->id ?? Curso::factory(),
            'estado' => fake()->randomElement(['presente', 'ausente', 'justificado', 'tardanza']),
            'hora_registro' => fake()->time('H:i:s'),
            'observaciones' => fake()->optional(0.2)->sentence(),
            'registrado_por_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'fecha_registro' => $claseProgramada->fecha_clase,
        ];
    }

    /**
     * Estado para crear una asistencia presente.
     *
     * @return static
     */
    public function presente(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'presente',
        ]);
    }

    /**
     * Estado para crear una asistencia ausente.
     *
     * @return static
     */
    public function ausente(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'ausente',
        ]);
    }

    /**
     * Estado para crear una asistencia justificada.
     *
     * @return static
     */
    public function justificada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'justificado',
            'observaciones' => fake()->sentence(),
        ]);
    }

    /**
     * Estado para crear una asistencia con tardanza.
     *
     * @return static
     */
    public function tardanza(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'tardanza',
        ]);
    }
}

