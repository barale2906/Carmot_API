<?php

namespace Database\Factories\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para crear matrículas con relaciones.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\Matricula>
 */
class MatriculaFactory extends Factory
{
    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Fecha de matrícula (últimos 6 meses hasta hoy)
        $fechaMatricula = fake()->dateTimeBetween('-6 months', 'now');

        // Fecha de inicio (desde la fecha de matrícula hasta 3 meses después)
        $fechaInicio = fake()->dateTimeBetween($fechaMatricula, '+3 months');

        return [
            'curso_id' => Curso::inRandomOrder()->first()?->id ?? Curso::factory(),
            'ciclo_id' => Ciclo::inRandomOrder()->first()?->id ?? Ciclo::factory(),
            'estudiante_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'matriculado_por_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'comercial_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'fecha_matricula' => $fechaMatricula,
            'fecha_inicio' => $fechaInicio,
            'monto' => fake()->randomFloat(2, 100000, 5000000), // Montos entre 100,000 y 5,000,000
            'observaciones' => fake()->optional(0.3)->paragraph(2), // 30% de probabilidad de tener observaciones
            'status' => fake()->randomElement([0, 1, 2]), // 0: Inactivo, 1: Activo, 2: Anulado
        ];
    }

    /**
     * Estado para crear una matrícula activa.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function activa(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 1,
            ];
        });
    }

    /**
     * Estado para crear una matrícula inactiva.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function inactiva(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 0,
            ];
        });
    }

    /**
     * Estado para crear una matrícula anulada.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function anulada(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 2,
            ];
        });
    }

    /**
     * Estado para crear una matrícula con curso específico.
     *
     * @param int $cursoId ID del curso
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conCurso(int $cursoId): static
    {
        return $this->state(function (array $attributes) use ($cursoId) {
            return [
                'curso_id' => $cursoId,
            ];
        });
    }

    /**
     * Estado para crear una matrícula con ciclo específico.
     *
     * @param int $cicloId ID del ciclo
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conCiclo(int $cicloId): static
    {
        return $this->state(function (array $attributes) use ($cicloId) {
            return [
                'ciclo_id' => $cicloId,
            ];
        });
    }

    /**
     * Estado para crear una matrícula con estudiante específico.
     *
     * @param int $estudianteId ID del estudiante
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conEstudiante(int $estudianteId): static
    {
        return $this->state(function (array $attributes) use ($estudianteId) {
            return [
                'estudiante_id' => $estudianteId,
            ];
        });
    }

    /**
     * Estado para crear una matrícula con monto específico.
     *
     * @param float $monto Monto de la matrícula
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conMonto(float $monto): static
    {
        return $this->state(function (array $attributes) use ($monto) {
            return [
                'monto' => $monto,
            ];
        });
    }

    /**
     * Estado para crear una matrícula con fechas específicas.
     *
     * @param string $fechaMatricula Fecha de matrícula (Y-m-d)
     * @param string $fechaInicio Fecha de inicio (Y-m-d)
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conFechas(string $fechaMatricula, string $fechaInicio): static
    {
        return $this->state(function (array $attributes) use ($fechaMatricula, $fechaInicio) {
            return [
                'fecha_matricula' => $fechaMatricula,
                'fecha_inicio' => $fechaInicio,
            ];
        });
    }

    /**
     * Estado para crear una matrícula reciente (último mes).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function reciente(): static
    {
        return $this->state(function (array $attributes) {
            $fechaMatricula = fake()->dateTimeBetween('-1 month', 'now');
            $fechaInicio = fake()->dateTimeBetween($fechaMatricula, '+2 months');

            return [
                'fecha_matricula' => $fechaMatricula,
                'fecha_inicio' => $fechaInicio,
            ];
        });
    }

    /**
     * Estado para crear una matrícula futura (próximos meses).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function futura(): static
    {
        return $this->state(function (array $attributes) {
            $fechaMatricula = fake()->dateTimeBetween('now', '+1 month');
            $fechaInicio = fake()->dateTimeBetween($fechaMatricula, '+3 months');

            return [
                'fecha_matricula' => $fechaMatricula,
                'fecha_inicio' => $fechaInicio,
            ];
        });
    }

    /**
     * Configurar el factory para usar relaciones existentes.
     */
    public function configure()
    {
        return $this->afterMaking(function ($matricula) {
            // Solo crear nuevas relaciones si no existen datos en la BD
            if (!$matricula->curso_id && Curso::count() === 0) {
                $matricula->curso_id = Curso::factory();
            }
            if (!$matricula->ciclo_id && Ciclo::count() === 0) {
                $matricula->ciclo_id = Ciclo::factory();
            }
            if (!$matricula->estudiante_id && User::count() === 0) {
                $matricula->estudiante_id = User::factory();
            }
            if (!$matricula->matriculado_por_id && User::count() === 0) {
                $matricula->matriculado_por_id = User::factory();
            }
            if (!$matricula->comercial_id && User::count() === 0) {
                $matricula->comercial_id = User::factory();
            }
        });
    }
}
