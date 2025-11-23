<?php

namespace Database\Factories\Academico;

use App\Models\Academico\Grupo;
use App\Models\Academico\Modulo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para crear esquemas de calificación.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\EsquemaCalificacion>
 */
class EsquemaCalificacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $modulo = Modulo::inRandomOrder()->first();
        if (!$modulo) {
            $modulo = Modulo::factory()->create();
        }

        $grupo = Grupo::inRandomOrder()->first();
        
        $profesor = User::role('profesor')->inRandomOrder()->first();
        if (!$profesor) {
            $profesor = User::inRandomOrder()->first();
            if (!$profesor) {
                $profesor = User::factory()->create();
            }
        }

        return [
            'modulo_id' => $modulo->id,
            'grupo_id' => $grupo ? $grupo->id : null,
            'profesor_id' => $profesor->id,
            'nombre_esquema' => fake()->randomElement([
                'Esquema Estándar',
                'Esquema Intensivo',
                'Esquema Mañana',
                'Esquema Tarde',
                'Esquema Noche',
                'Esquema Fin de Semana',
                'Esquema Personalizado',
            ]),
            'descripcion' => fake()->optional(0.7)->paragraph(2),
            'condicion_aplicacion' => fake()->optional(0.5)->sentence(),
            'status' => fake()->randomElement([0, 1]), // 0: Inactivo, 1: Activo
        ];
    }

    /**
     * Estado para crear un esquema activo.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function activo(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 1,
            ];
        });
    }

    /**
     * Estado para crear un esquema inactivo.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function inactivo(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 0,
            ];
        });
    }

    /**
     * Estado para crear un esquema para un módulo específico.
     *
     * @param int $moduloId
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function paraModulo(int $moduloId): static
    {
        return $this->state(function (array $attributes) use ($moduloId) {
            return [
                'modulo_id' => $moduloId,
                'grupo_id' => null, // Esquema general del módulo
            ];
        });
    }

    /**
     * Estado para crear un esquema para un grupo específico.
     *
     * @param int $grupoId
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function paraGrupo(int $grupoId): static
    {
        return $this->state(function (array $attributes) use ($grupoId) {
            $grupo = Grupo::find($grupoId);
            return [
                'modulo_id' => $grupo ? $grupo->modulo_id : $attributes['modulo_id'],
                'grupo_id' => $grupoId,
            ];
        });
    }

    /**
     * Estado para crear un esquema estándar (3 parciales + proyecto final).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function esquemaEstandar(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'nombre_esquema' => 'Esquema Estándar',
                'descripcion' => 'Esquema con 3 parciales y proyecto final',
            ];
        })->afterCreating(function ($esquema) {
            // Crear tipos de nota para el esquema estándar
            $tiposNota = [
                ['nombre_tipo' => 'Parcial 1', 'peso' => 25, 'orden' => 1, 'nota_minima' => 0, 'nota_maxima' => 5],
                ['nombre_tipo' => 'Parcial 2', 'peso' => 25, 'orden' => 2, 'nota_minima' => 0, 'nota_maxima' => 5],
                ['nombre_tipo' => 'Parcial 3', 'peso' => 25, 'orden' => 3, 'nota_minima' => 0, 'nota_maxima' => 5],
                ['nombre_tipo' => 'Proyecto Final', 'peso' => 25, 'orden' => 4, 'nota_minima' => 0, 'nota_maxima' => 5],
            ];

            foreach ($tiposNota as $tipo) {
                \App\Models\Academico\TipoNotaEsquema::create([
                    'esquema_calificacion_id' => $esquema->id,
                    ...$tipo,
                ]);
            }
        });
    }

    /**
     * Estado para crear un esquema con participación y quizzes.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function esquemaConParticipacion(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'nombre_esquema' => 'Esquema con Participación',
                'descripcion' => 'Esquema que incluye participación y quizzes',
            ];
        })->afterCreating(function ($esquema) {
            $tiposNota = [
                ['nombre_tipo' => 'Parcial 1', 'peso' => 30, 'orden' => 1, 'nota_minima' => 0, 'nota_maxima' => 5],
                ['nombre_tipo' => 'Parcial 2', 'peso' => 30, 'orden' => 2, 'nota_minima' => 0, 'nota_maxima' => 5],
                ['nombre_tipo' => 'Quizzes', 'peso' => 20, 'orden' => 3, 'nota_minima' => 0, 'nota_maxima' => 5],
                ['nombre_tipo' => 'Participación', 'peso' => 10, 'orden' => 4, 'nota_minima' => 0, 'nota_maxima' => 5],
                ['nombre_tipo' => 'Proyecto Final', 'peso' => 10, 'orden' => 5, 'nota_minima' => 0, 'nota_maxima' => 5],
            ];

            foreach ($tiposNota as $tipo) {
                \App\Models\Academico\TipoNotaEsquema::create([
                    'esquema_calificacion_id' => $esquema->id,
                    ...$tipo,
                ]);
            }
        });
    }

    /**
     * Estado para crear un esquema intensivo (2 parciales + proyecto).
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function esquemaIntensivo(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'nombre_esquema' => 'Esquema Intensivo',
                'descripcion' => 'Esquema para cursos intensivos con 2 parciales',
            ];
        })->afterCreating(function ($esquema) {
            $tiposNota = [
                ['nombre_tipo' => 'Parcial 1', 'peso' => 40, 'orden' => 1, 'nota_minima' => 0, 'nota_maxima' => 5],
                ['nombre_tipo' => 'Parcial 2', 'peso' => 40, 'orden' => 2, 'nota_minima' => 0, 'nota_maxima' => 5],
                ['nombre_tipo' => 'Proyecto Final', 'peso' => 20, 'orden' => 3, 'nota_minima' => 0, 'nota_maxima' => 5],
            ];

            foreach ($tiposNota as $tipo) {
                \App\Models\Academico\TipoNotaEsquema::create([
                    'esquema_calificacion_id' => $esquema->id,
                    ...$tipo,
                ]);
            }
        });
    }
}
