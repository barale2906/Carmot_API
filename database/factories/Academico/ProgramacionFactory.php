<?php

namespace Database\Factories\Academico;

use App\Models\Academico\Curso;
use App\Models\Academico\Grupo;
use App\Models\Academico\Modulo;
use App\Models\Academico\Programacion;
use App\Models\Configuracion\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para crear programaciones académicas con relaciones.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\Programacion>
 */
class ProgramacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Nombres de programaciones académicas realistas
        $nombresProgramaciones = [
            'Programación I 2024', 'Programación II 2024', 'Programación III 2024',
            'Programación I 2025', 'Programación II 2025', 'Programación III 2025',
            'Programación Intensiva 2024', 'Programación Especial 2024',
            'Programación de Verano 2024', 'Programación de Invierno 2024',
            'Programación Regular 2024', 'Programación Extraordinaria 2024',
            'Programación Básica 2024', 'Programación Avanzada 2024',
            'Programación de Actualización 2024', 'Programación de Especialización 2024'
        ];

        // Descripciones de programaciones académicas
        $descripcionesProgramaciones = [
            'Programación académica regular del año',
            'Programación intensiva de formación',
            'Programación especial de actualización',
            'Programación de verano para estudiantes',
            'Programación de invierno intensivo',
            'Programación extraordinaria de recuperación',
            'Programación básica de fundamentos',
            'Programación avanzada de especialización',
            'Programación de actualización profesional',
            'Programación de especialización técnica'
        ];

        // Seleccionar curso y sede
        $curso = Curso::inRandomOrder()->first() ?? Curso::factory()->create();
        $sede = Sede::inRandomOrder()->first() ?? Sede::factory()->create();

        // Seleccionar jornada aleatoria
        $jornada = $this->faker->numberBetween(0, 4);

        // Generar fechas base (se ajustarán después según los grupos)
        $fechaInicio = $this->faker->dateTimeBetween('now', '+3 months');
        $fechaFin = $this->faker->dateTimeBetween($fechaInicio, '+6 months');

        return [
            'curso_id' => $curso->id,
            'sede_id' => $sede->id,
            'nombre' => $this->faker->randomElement($nombresProgramaciones),
            'descripcion' => $this->faker->randomElement($descripcionesProgramaciones),
            'fecha_inicio' => $fechaInicio->format('Y-m-d'),
            'fecha_fin' => $fechaFin->format('Y-m-d'),
            'registrados' => $this->faker->numberBetween(0, 50),
            'jornada' => $jornada,
            'status' => $this->faker->randomElement([0, 1]), // 0 inactivo, 1 Activo
        ];
    }

    /**
     * Estado para programación activa.
     */
    public function activo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 1,
        ]);
    }

    /**
     * Estado para programación inactiva.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
        ]);
    }

    /**
     * Estado para programación con grupos basados en módulos del curso.
     * Selecciona grupos que pertenezcan a módulos del curso, estén en la sede y tengan la jornada.
     */
    public function conGruposDelCurso(): static
    {
        return $this->afterCreating(function ($programacion) {
            // Obtener módulos del curso
            $modulosDelCurso = $programacion->curso->modulos()->pluck('modulos.id');

            if ($modulosDelCurso->isEmpty()) {
                return;
            }

            // Buscar grupos que:
            // 1. Pertenezcan a módulos del curso
            // 2. Estén en la sede de la programación
            // 3. Tengan la jornada de la programación
            // 4. Tengan horarios configurados
            $gruposDisponibles = Grupo::whereIn('modulo_id', $modulosDelCurso)
                ->where('sede_id', $programacion->sede_id)
                ->where('jornada', $programacion->jornada)
                ->where('status', 1)
                ->whereHas('horarios')
                ->with(['modulo', 'horarios'])
                ->get();

            if ($gruposDisponibles->isEmpty()) {
                return;
            }

            // Seleccionar algunos grupos (máximo 5)
            $cantidad = min(5, $gruposDisponibles->count());
            $gruposSeleccionados = $gruposDisponibles->random($cantidad);

            // Calcular fechas basándose en los horarios de los grupos
            $fechasCalculadas = $this->calcularFechasGrupos($gruposSeleccionados, $programacion->fecha_inicio);

            // Asignar grupos con fechas
            $programacion->asignarGruposConFechas($fechasCalculadas);

            // Actualizar fecha de inicio y fin de la programación
            if (!empty($fechasCalculadas)) {
                $fechasInicio = array_filter(array_column($fechasCalculadas, 'fecha_inicio_grupo'));
                $fechasFin = array_filter(array_column($fechasCalculadas, 'fecha_fin_grupo'));

                if (!empty($fechasInicio)) {
                    $programacion->fecha_inicio = min($fechasInicio);
                }
                if (!empty($fechasFin)) {
                    $programacion->fecha_fin = max($fechasFin);
                }
                $programacion->save();
            }
        });
    }

    /**
     * Calcula las fechas de inicio y fin para cada grupo basándose en sus horarios y duración del módulo.
     * Los grupos pueden iniciar en paralelo (mismo día) o secuencialmente.
     *
     * @param \Illuminate\Database\Eloquent\Collection $grupos
     * @param string $fechaInicioBase Fecha de inicio base de la programación
     * @return array
     */
    private function calcularFechasGrupos($grupos, string $fechaInicioBase): array
    {
        $fechaBase = \Carbon\Carbon::parse($fechaInicioBase);
        $gruposConFechas = [];
        $fechaMaximaInicio = $fechaBase->copy();

        // Primero, calcular todas las fechas de inicio basándose en los horarios
        foreach ($grupos as $grupo) {
            // Obtener el primer día de clase del grupo desde sus horarios
            $primerDiaClase = $this->obtenerPrimerDiaClase($grupo, $fechaBase);

            // Asegurar que la fecha de inicio no sea anterior a la fecha base
            if ($primerDiaClase->lt($fechaBase)) {
                $primerDiaClase = $fechaBase->copy();
            }

            // Calcular fecha de fin basándose en duración del módulo y horas por semana
            $duracionModulo = $grupo->modulo->duracion ?? 0;
            $horasPorSemana = $grupo->getTotalHorasSemanaAttribute();

            $fechaInicioGrupo = $primerDiaClase->format('Y-m-d');
            $fechaFinGrupo = null;

            if ($horasPorSemana > 0 && $duracionModulo > 0) {
                // Calcular semanas necesarias
                $semanasNecesarias = ceil($duracionModulo / $horasPorSemana);
                $fechaFinGrupo = $primerDiaClase->copy()->addWeeks($semanasNecesarias)->format('Y-m-d');
            }

            $gruposConFechas[] = [
                'grupo_id' => $grupo->id,
                'fecha_inicio_grupo' => $fechaInicioGrupo,
                'fecha_fin_grupo' => $fechaFinGrupo,
            ];

            // Rastrear la fecha máxima de inicio para ajustar si es necesario
            if ($primerDiaClase->gt($fechaMaximaInicio)) {
                $fechaMaximaInicio = $primerDiaClase->copy();
            }
        }

        return $gruposConFechas;
    }

    /**
     * Obtiene el primer día de clase del grupo basándose en sus horarios.
     *
     * @param \App\Models\Academico\Grupo $grupo
     * @param \Carbon\Carbon $fechaBase Fecha base desde donde buscar
     * @return \Carbon\Carbon
     */
    private function obtenerPrimerDiaClase($grupo, \Carbon\Carbon $fechaBase): \Carbon\Carbon
    {
        $horarios = $grupo->horarios;

        if ($horarios->isEmpty()) {
            // Si no tiene horarios, usar la fecha base
            return $fechaBase;
        }

        // Mapeo de días de la semana
        $diasSemana = [
            'lunes' => 1,
            'martes' => 2,
            'miércoles' => 3,
            'jueves' => 4,
            'viernes' => 5,
            'sábado' => 6,
            'domingo' => 0,
        ];

        // Obtener el día de la semana más temprano
        $diasClase = $horarios->pluck('dia')->map(function ($dia) use ($diasSemana) {
            return $diasSemana[strtolower($dia)] ?? null;
        })->filter()->sort()->values();

        if ($diasClase->isEmpty()) {
            return $fechaBase;
        }

        $primerDiaSemana = $diasClase->first();
        $fechaActual = $fechaBase->copy();

        // Encontrar el próximo día de la semana que coincida
        // Si ya pasó ese día esta semana, buscar en la próxima semana
        $diferenciaDias = ($primerDiaSemana - $fechaActual->dayOfWeek + 7) % 7;

        if ($diferenciaDias == 0 && $fechaActual->dayOfWeek == $primerDiaSemana) {
            // Ya es ese día, usar la fecha actual
            return $fechaActual;
        }

        // Avanzar al próximo día de la semana
        $fechaActual->addDays($diferenciaDias);

        return $fechaActual;
    }

    /**
     * Estado para programación con grupos de jornada específica.
     */
    public function conGruposJornada(int $jornada): static
    {
        return $this->state(fn (array $attributes) => [
            'jornada' => $jornada,
        ]);
    }

    /**
     * Estado para programación con grupos de mañana.
     */
    public function conGruposManana(): static
    {
        return $this->conGruposJornada(0);
    }

    /**
     * Estado para programación con grupos de tarde.
     */
    public function conGruposTarde(): static
    {
        return $this->conGruposJornada(1);
    }

    /**
     * Estado para programación con grupos de noche.
     */
    public function conGruposNoche(): static
    {
        return $this->conGruposJornada(2);
    }

    /**
     * Estado para programación con grupos de fin de semana.
     */
    public function conGruposFinDeSemana(): static
    {
        return $this->state(fn (array $attributes) => [
            'jornada' => $this->faker->randomElement([3, 4]), // Fin de semana mañana o tarde
        ]);
    }

    /**
     * Estado para programación que ya comenzó.
     */
    public function enCurso(): static
    {
        $fechaInicio = $this->faker->dateTimeBetween('-2 months', 'now');
        $fechaFin = $this->faker->dateTimeBetween('now', '+4 months');

        return $this->state(fn (array $attributes) => [
            'fecha_inicio' => $fechaInicio->format('Y-m-d'),
            'fecha_fin' => $fechaFin->format('Y-m-d'),
        ]);
    }

    /**
     * Estado para programación que ya finalizó.
     */
    public function finalizada(): static
    {
        $fechaInicio = $this->faker->dateTimeBetween('-8 months', '-6 months');
        $fechaFin = $this->faker->dateTimeBetween('-2 months', '-1 month');

        return $this->state(fn (array $attributes) => [
            'fecha_inicio' => $fechaInicio->format('Y-m-d'),
            'fecha_fin' => $fechaFin->format('Y-m-d'),
        ]);
    }

    /**
     * Estado para programación que está por iniciar.
     */
    public function porIniciar(): static
    {
        $fechaInicio = $this->faker->dateTimeBetween('+1 month', '+3 months');
        $fechaFin = $this->faker->dateTimeBetween($fechaInicio, '+6 months');

        return $this->state(fn (array $attributes) => [
            'fecha_inicio' => $fechaInicio->format('Y-m-d'),
            'fecha_fin' => $fechaFin->format('Y-m-d'),
        ]);
    }

    /**
     * Estado para programación sin grupos.
     */
    public function sinGrupos(): static
    {
        return $this->afterCreating(function ($programacion) {
            // No asignar ningún grupo
        });
    }

    /**
     * Estado para programación con muchos grupos.
     */
    public function conMuchosGrupos(): static
    {
        return $this->conGruposDelCursoConCantidad(8);
    }

    /**
     * Estado para programación con pocos grupos.
     */
    public function conPocosGrupos(): static
    {
        return $this->conGruposDelCursoConCantidad(2);
    }

    /**
     * Método auxiliar para asignar grupos del curso con una cantidad específica.
     *
     * @param int $cantidadMaxima Cantidad máxima de grupos a asignar
     * @return static
     */
    private function conGruposDelCursoConCantidad(int $cantidadMaxima): static
    {
        return $this->afterCreating(function ($programacion) use ($cantidadMaxima) {
            $modulosDelCurso = $programacion->curso->modulos()->pluck('modulos.id');

            if ($modulosDelCurso->isEmpty()) {
                return;
            }

            $gruposDisponibles = Grupo::whereIn('modulo_id', $modulosDelCurso)
                ->where('sede_id', $programacion->sede_id)
                ->where('jornada', $programacion->jornada)
                ->where('status', 1)
                ->whereHas('horarios')
                ->with(['modulo', 'horarios'])
                ->get();

            if ($gruposDisponibles->isEmpty()) {
                return;
            }

            $cantidad = min($cantidadMaxima, $gruposDisponibles->count());
            $gruposSeleccionados = $gruposDisponibles->random($cantidad);

            $fechasCalculadas = $this->calcularFechasGrupos($gruposSeleccionados, $programacion->fecha_inicio);
            $programacion->asignarGruposConFechas($fechasCalculadas);

            if (!empty($fechasCalculadas)) {
                $fechasInicio = array_filter(array_column($fechasCalculadas, 'fecha_inicio_grupo'));
                $fechasFin = array_filter(array_column($fechasCalculadas, 'fecha_fin_grupo'));

                if (!empty($fechasInicio)) {
                    $programacion->fecha_inicio = min($fechasInicio);
                }
                if (!empty($fechasFin)) {
                    $programacion->fecha_fin = max($fechasFin);
                }
                $programacion->save();
            }
        });
    }

    /**
     * Configurar el factory para usar relaciones existentes.
     */
    public function configure()
    {
        return $this->afterMaking(function ($programacion) {
            // Solo crear nuevas relaciones si no existen datos en la BD
            if (!$programacion->curso_id && Curso::count() === 0) {
                $programacion->curso_id = Curso::factory();
            }
            if (!$programacion->sede_id && Sede::count() === 0) {
                $programacion->sede_id = Sede::factory();
            }
        });
    }
}
