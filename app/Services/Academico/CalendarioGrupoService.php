<?php

namespace App\Services\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CalendarioGrupoService
 *
 * Gestiona el calendario cíclico de grupos/módulos:
 *  - fechasActivasGrupo: consulta si un grupo tiene ejecución activa o futura en algún ciclo.
 *  - calcularYAsignarFechas: asigna fechas al pivot ciclo_grupo de un ciclo nuevo,
 *    usando el calendario existente para grupos en ejecución y calculando desde
 *    la duración del módulo para grupos entre ejecuciones.
 *  - previsualizarCurso: devuelve el panorama de módulos/grupos antes de crear un ciclo.
 */
class CalendarioGrupoService
{
    /**
     * Retorna las fechas activas o futuras más próximas de un grupo en cualquier ciclo.
     * "Activa o futura" = fecha_fin_grupo >= hoy y fecha_inicio_grupo no nulo.
     * Opcionalmente excluye el ciclo que se está construyendo para evitar
     * que el registro recién creado se use como referencia de sí mismo.
     *
     * @param int      $grupoId
     * @param int|null $excluirCicloId
     * @return array{ciclo_id:int,fecha_inicio:string,fecha_fin:string}|null
     */
    public function fechasActivasGrupo(int $grupoId, ?int $excluirCicloId = null): ?array
    {
        $hoy = now()->toDateString();

        $query = DB::table('ciclo_grupo')
            ->where('grupo_id', $grupoId)
            ->whereNotNull('fecha_inicio_grupo')
            ->where(function ($q) use ($hoy) {
                $q->whereNull('fecha_fin_grupo')
                  ->orWhere('fecha_fin_grupo', '>=', $hoy);
            })
            ->orderBy('fecha_inicio_grupo')
            ->select(['ciclo_id', 'fecha_inicio_grupo', 'fecha_fin_grupo']);

        if ($excluirCicloId !== null) {
            $query->where('ciclo_id', '!=', $excluirCicloId);
        }

        $fila = $query->first();

        if (! $fila) {
            return null;
        }

        return [
            'ciclo_id'     => $fila->ciclo_id,
            'fecha_inicio' => $fila->fecha_inicio_grupo,
            'fecha_fin'    => $fila->fecha_fin_grupo,
        ];
    }

    /**
     * Calcula y asigna las fechas del pivot ciclo_grupo para todos los grupos
     * del ciclo, respetando el calendario cíclico:
     *
     *  - Grupo con fechas activas en otro ciclo → usa esas fechas directamente.
     *  - Grupo entre ejecuciones (sin fechas activas) → calcula desde la duración
     *    del módulo, tomando como inicio la fecha_fin del grupo anterior en la
     *    secuencia del mismo ciclo.
     *
     * Devuelve la fecha_fin del último grupo (= fecha_fin sugerida del ciclo).
     *
     * @param Ciclo $ciclo
     * @return Carbon|null
     */
    public function calcularYAsignarFechas(Ciclo $ciclo): ?Carbon
    {
        if (! $ciclo->fecha_inicio) {
            return null;
        }

        $ciclo->refresh();
        $gruposOrdenados = $ciclo->grupos()->orderBy('ciclo_grupo.orden')->get();

        if ($gruposOrdenados->isEmpty()) {
            return null;
        }

        $fechaActual  = Carbon::parse($ciclo->fecha_inicio);
        $ultimaFechaFin = null;

        foreach ($gruposOrdenados as $grupo) {
            $fechasExistentes = $this->fechasActivasGrupo($grupo->id, $ciclo->id);

            if ($fechasExistentes) {
                // El grupo tiene un slot en otro ciclo → compartir ese slot
                $fechaInicioGrupo = Carbon::parse($fechasExistentes['fecha_inicio']);
                $fechaFinGrupo    = Carbon::parse($fechasExistentes['fecha_fin']);
            } else {
                // Grupo entre ejecuciones → calcular desde módulo
                $duracion    = $grupo->modulo->duracion ?? 0;
                $horasSemana = $grupo->getTotalHorasSemanaAttribute();

                if ($horasSemana <= 0 || $duracion <= 0) {
                    continue;
                }

                $semanas          = (int) ceil($duracion / $horasSemana);
                $fechaInicioGrupo = $fechaActual->copy();
                $fechaFinGrupo    = $fechaActual->copy()->addWeeks($semanas);

                // Avanzar el cursor solo para grupos sin slot previo
                $fechaActual = $fechaFinGrupo->copy();
            }

            $ciclo->grupos()->updateExistingPivot($grupo->id, [
                'fecha_inicio_grupo' => $fechaInicioGrupo->format('Y-m-d'),
                'fecha_fin_grupo'    => $fechaFinGrupo->format('Y-m-d'),
            ]);

            // Guardar la fecha_fin más tardía como candidata a fecha_fin del ciclo
            if ($ultimaFechaFin === null || $fechaFinGrupo->gt($ultimaFechaFin)) {
                $ultimaFechaFin = $fechaFinGrupo;
            }
        }

        return $ultimaFechaFin;
    }

    /**
     * Previsualiza el calendario de módulos de un curso antes de crear un ciclo.
     * Para cada módulo (en su orden canónico) muestra sus grupos con:
     *  - con_fechas=true  → el grupo ya tiene ejecución activa o futura en otro ciclo.
     *  - con_fechas=false → el grupo está entre ejecuciones; las fechas son estimadas
     *    a partir de la secuencia de la previsualización.
     *
     * @param int    $cursoId
     * @param string $fechaInicio  Fecha de inicio propuesta para el nuevo ciclo (Y-m-d).
     * @return array
     */
    public function previsualizarCurso(int $cursoId, string $fechaInicio): array
    {
        $curso = Curso::with([
            'modulosOrdenados.grupos.horarios',
            'modulosOrdenados.grupos.modulo',
        ])->findOrFail($cursoId);

        $fechaActual = Carbon::parse($fechaInicio);
        $modulos     = [];

        foreach ($curso->modulosOrdenados as $modulo) {
            $gruposModulo   = [];
            $primerSinFecha = null; // fecha_fin calculada del primer grupo sin slot

            foreach ($modulo->grupos as $grupo) {
                $horasSemana      = $grupo->getTotalHorasSemanaAttribute();
                $duracion         = $modulo->duracion ?? 0;
                $semanas          = ($horasSemana > 0 && $duracion > 0)
                    ? (int) ceil($duracion / $horasSemana)
                    : 0;

                $fechasExistentes = $this->fechasActivasGrupo($grupo->id);

                if ($fechasExistentes) {
                    $gruposModulo[] = [
                        'grupo_id'            => $grupo->id,
                        'grupo_nombre'        => $grupo->nombre,
                        'con_fechas'          => true,
                        'fecha_inicio'        => $fechasExistentes['fecha_inicio'],
                        'fecha_fin'           => $fechasExistentes['fecha_fin'],
                        'ciclo_referencia_id' => $fechasExistentes['ciclo_id'],
                        'horas_semana'        => $horasSemana,
                        'semanas_estimadas'   => $semanas,
                    ];
                } else {
                    $fechaInicioCalc = $fechaActual->copy();
                    $fechaFinCalc    = $semanas > 0
                        ? $fechaActual->copy()->addWeeks($semanas)
                        : null;

                    $gruposModulo[] = [
                        'grupo_id'            => $grupo->id,
                        'grupo_nombre'        => $grupo->nombre,
                        'con_fechas'          => false,
                        'fecha_inicio'        => $fechaInicioCalc->format('Y-m-d'),
                        'fecha_fin'           => $fechaFinCalc?->format('Y-m-d'),
                        'ciclo_referencia_id' => null,
                        'horas_semana'        => $horasSemana,
                        'semanas_estimadas'   => $semanas,
                    ];

                    // El primer grupo sin slot determina cuándo avanza el cursor
                    if ($primerSinFecha === null && $fechaFinCalc) {
                        $primerSinFecha = $fechaFinCalc;
                    }
                }
            }

            // Solo avanzar cursor cuando hay grupos entre ejecuciones en este módulo
            if ($primerSinFecha) {
                $fechaActual = $primerSinFecha->copy();
            }

            $modulos[] = [
                'modulo_id'     => $modulo->id,
                'modulo_nombre' => $modulo->nombre,
                'orden'         => $modulo->pivot->orden,
                'duracion'      => $modulo->duracion,
                'grupos'        => $gruposModulo,
            ];
        }

        return [
            'curso_id'     => $curso->id,
            'curso_nombre' => $curso->nombre,
            'fecha_inicio' => $fechaInicio,
            'modulos'      => $modulos,
        ];
    }
}
