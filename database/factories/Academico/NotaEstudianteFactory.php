<?php

namespace Database\Factories\Academico;

use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\Grupo;
use App\Models\Academico\Modulo;
use App\Models\Academico\NotaEstudiante;
use App\Models\Academico\TipoNotaEsquema;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para crear notas de estudiantes.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\NotaEstudiante>
 */
class NotaEstudianteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grupo = Grupo::inRandomOrder()->first();
        if (!$grupo) {
            $grupo = Grupo::factory()->create();
        }

        $modulo = $grupo->modulo;
        if (!$modulo) {
            $modulo = Modulo::inRandomOrder()->first();
            if (!$modulo) {
                $modulo = Modulo::factory()->create();
            }
        }

        $esquema = EsquemaCalificacion::where('modulo_id', $modulo->id)
            ->where(function ($q) use ($grupo) {
                $q->where('grupo_id', $grupo->id)->orWhereNull('grupo_id');
            })
            ->where('status', 1)
            ->inRandomOrder()
            ->first();

        if (!$esquema) {
            $esquema = EsquemaCalificacion::factory()->activo()->paraModulo($modulo->id)->create();
        }

        $tipoNota = TipoNotaEsquema::where('esquema_calificacion_id', $esquema->id)
            ->inRandomOrder()
            ->first();

        if (!$tipoNota) {
            $tipoNota = TipoNotaEsquema::factory()->create([
                'esquema_calificacion_id' => $esquema->id,
            ]);
        }

        $estudiante = User::inRandomOrder()->first();
        if (!$estudiante) {
            $estudiante = User::factory()->create();
        }

        $profesor = User::role('profesor')->inRandomOrder()->first();
        if (!$profesor) {
            $profesor = User::inRandomOrder()->first();
            if (!$profesor) {
                $profesor = User::factory()->create();
            }
        }

        $nota = fake()->randomFloat(2, 0, 5);
        $peso = $tipoNota->peso;
        $notaPonderada = NotaEstudiante::calcularNotaPonderada($nota, $peso);

        return [
            'estudiante_id' => $estudiante->id,
            'grupo_id' => $grupo->id,
            'modulo_id' => $modulo->id,
            'esquema_calificacion_id' => $esquema->id,
            'tipo_nota_esquema_id' => $tipoNota->id,
            'nota' => $nota,
            'nota_ponderada' => $notaPonderada,
            'fecha_registro' => fake()->dateTimeBetween('-3 months', 'now'),
            'registrado_por_id' => $profesor->id,
            'observaciones' => fake()->optional(0.2)->sentence(),
            'status' => fake()->randomElement([0, 1, 2]), // 0: Pendiente, 1: Registrada, 2: Cerrada
        ];
    }

    /**
     * Estado para crear una nota registrada.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function registrada(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 1,
            ];
        });
    }

    /**
     * Estado para crear una nota pendiente.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function pendiente(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 0,
            ];
        });
    }

    /**
     * Estado para crear una nota cerrada.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function cerrada(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 2,
            ];
        });
    }

    /**
     * Estado para crear una nota con valor específico.
     *
     * @param float $nota
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conNota(float $nota): static
    {
        return $this->state(function (array $attributes) use ($nota) {
            // Recalcular nota ponderada
            $tipoNota = TipoNotaEsquema::find($attributes['tipo_nota_esquema_id']);
            $peso = $tipoNota ? $tipoNota->peso : 25;
            $notaPonderada = NotaEstudiante::calcularNotaPonderada($nota, $peso);

            return [
                'nota' => $nota,
                'nota_ponderada' => $notaPonderada,
            ];
        });
    }

    /**
     * Estado para crear una nota para un estudiante específico.
     *
     * @param int $estudianteId
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function paraEstudiante(int $estudianteId): static
    {
        return $this->state(function (array $attributes) use ($estudianteId) {
            return [
                'estudiante_id' => $estudianteId,
            ];
        });
    }

    /**
     * Estado para crear una nota para un grupo y módulo específicos.
     *
     * @param int $grupoId
     * @param int $moduloId
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function paraGrupoModulo(int $grupoId, int $moduloId): static
    {
        return $this->state(function (array $attributes) use ($grupoId, $moduloId) {
            // Buscar esquema activo para este grupo/módulo
            $esquema = EsquemaCalificacion::where('modulo_id', $moduloId)
                ->where(function ($q) use ($grupoId) {
                    $q->where('grupo_id', $grupoId)->orWhereNull('grupo_id');
                })
                ->where('status', 1)
                ->first();

            if (!$esquema) {
                $esquema = EsquemaCalificacion::factory()->activo()->paraGrupo($grupoId)->create();
            }

            $tipoNota = TipoNotaEsquema::where('esquema_calificacion_id', $esquema->id)
                ->inRandomOrder()
                ->first();

            if (!$tipoNota) {
                $tipoNota = TipoNotaEsquema::factory()->create([
                    'esquema_calificacion_id' => $esquema->id,
                ]);
            }

            $nota = fake()->randomFloat(2, 0, 5);
            $notaPonderada = NotaEstudiante::calcularNotaPonderada($nota, $tipoNota->peso);

            return [
                'grupo_id' => $grupoId,
                'modulo_id' => $moduloId,
                'esquema_calificacion_id' => $esquema->id,
                'tipo_nota_esquema_id' => $tipoNota->id,
                'nota' => $nota,
                'nota_ponderada' => $notaPonderada,
            ];
        });
    }

    /**
     * Configurar el factory para evitar duplicados.
     */
    public function configure()
    {
        return $this->afterMaking(function ($nota) {
            // Verificar si ya existe una nota con los mismos valores únicos
            $existe = NotaEstudiante::where('estudiante_id', $nota->estudiante_id)
                ->where('grupo_id', $nota->grupo_id)
                ->where('modulo_id', $nota->modulo_id)
                ->where('tipo_nota_esquema_id', $nota->tipo_nota_esquema_id)
                ->exists();

            if ($existe) {
                // Si existe, cambiar el estudiante
                $nuevoEstudiante = User::inRandomOrder()
                    ->where('id', '!=', $nota->estudiante_id)
                    ->first();
                
                if ($nuevoEstudiante) {
                    $nota->estudiante_id = $nuevoEstudiante->id;
                }
            }
        });
    }
}
