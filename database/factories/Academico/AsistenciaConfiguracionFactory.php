<?php

namespace Database\Factories\Academico;

use App\Models\Academico\Curso;
use App\Models\Academico\Modulo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\AsistenciaConfiguracion>
 */
class AsistenciaConfiguracionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'curso_id' => fake()->optional(0.5)->randomElement([null, Curso::inRandomOrder()->first()?->id ?? Curso::factory()]),
            'modulo_id' => fake()->optional(0.3)->randomElement([null, Modulo::inRandomOrder()->first()?->id ?? Modulo::factory()]),
            'porcentaje_minimo' => fake()->randomFloat(2, 70, 95),
            'horas_minimas' => fake()->optional(0.3)->numberBetween(20, 120),
            'aplicar_justificaciones' => fake()->boolean(80),
            'perder_por_fallas' => fake()->boolean(90),
            'fecha_inicio_vigencia' => fake()->optional(0.5)->dateTimeBetween('-1 year', 'now'),
            'fecha_fin_vigencia' => fake()->optional(0.3)->dateTimeBetween('now', '+1 year'),
            'observaciones' => fake()->optional(0.4)->sentence(),
        ];
    }

    /**
     * Estado para crear una configuración general (sin curso ni módulo).
     *
     * @return static
     */
    public function general(): static
    {
        return $this->state(fn (array $attributes) => [
            'curso_id' => null,
            'modulo_id' => null,
        ]);
    }

    /**
     * Estado para crear una configuración por curso.
     *
     * @return static
     */
    public function porCurso(): static
    {
        return $this->state(fn (array $attributes) => [
            'curso_id' => Curso::inRandomOrder()->first()?->id ?? Curso::factory(),
            'modulo_id' => null,
        ]);
    }

    /**
     * Estado para crear una configuración por módulo.
     *
     * @return static
     */
    public function porModulo(): static
    {
        return $this->state(fn (array $attributes) => [
            'curso_id' => Curso::inRandomOrder()->first()?->id ?? Curso::factory(),
            'modulo_id' => Modulo::inRandomOrder()->first()?->id ?? Modulo::factory(),
        ]);
    }

    /**
     * Estado para crear una configuración vigente.
     *
     * @return static
     */
    public function vigente(): static
    {
        return $this->state(fn (array $attributes) => [
            'fecha_inicio_vigencia' => fake()->dateTimeBetween('-6 months', 'now'),
            'fecha_fin_vigencia' => fake()->optional(0.5)->dateTimeBetween('now', '+6 months'),
        ]);
    }
}
