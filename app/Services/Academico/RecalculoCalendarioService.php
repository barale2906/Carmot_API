<?php

namespace App\Services\Academico;

use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Grupo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * RecalculoCalendarioService
 *
 * Mantiene los ciclos coherentes con el calendario laboral:
 *  - recalcularPorDias: ante un cambio en días no laborables, recalcula
 *    automáticamente cada ciclo afectado (fechas de grupos, fecha fin y
 *    clases programadas pendientes), propagando a los ciclos que copian
 *    fechas de un grupo compartido.
 *  - realinearClases: reubica las clases 'programada' del ciclo sobre las
 *    sesiones laborables de su grupo. Las clases dictadas, canceladas o
 *    reprogramadas no se tocan y ocupan su slot.
 */
class RecalculoCalendarioService
{
    /** Fecha temporal para liberar slots antes de reubicar (evita choques con el índice único). */
    private const FECHA_TEMPORAL = '1000-01-01';

    public function __construct(
        private readonly CalendarioGrupoService $calendarioGrupo,
        private readonly CalendarioLaboralService $calendarioLaboral,
    ) {
    }

    /**
     * Recalcula los ciclos afectados por cambios en días no laborables.
     *
     * Un ciclo está afectado si es activo y la fecha cae dentro de su rango
     * (fecha_inicio..fecha_fin) o dentro del rango de alguno de sus grupos.
     * Cuando el día es de una sede, solo se consideran ciclos o grupos de esa sede.
     *
     * Los ciclos se procesan por fecha de inicio (los orígenes de fechas
     * compartidas son anteriores a sus copias) y luego se reprocesan los que
     * copian fechas de un ciclo recalculado.
     *
     * @param array<int, array{fecha: string, sede_id: int|null}> $dias
     * @return array<int, array{id: int, nombre: string, fecha_fin_anterior: string|null, fecha_fin: string|null, factor_ajuste: float|null, advertencia: string|null, clases_realineadas: int}>
     */
    public function recalcularPorDias(array $dias): array
    {
        $cicloIds = collect($dias)
            ->flatMap(fn ($dia) => $this->ciclosAfectados($dia['fecha'], $dia['sede_id']))
            ->unique();

        if ($cicloIds->isEmpty()) {
            return [];
        }

        // Agregar los ciclos que copian fechas de un ciclo afectado
        $cicloIds = $cicloIds->merge(
            DB::table('ciclo_grupo')->whereIn('ciclo_origen_id', $cicloIds)->pluck('ciclo_id')
        )->unique();

        $ciclos = Ciclo::whereIn('id', $cicloIds)->orderBy('fecha_inicio')->orderBy('id')->get();

        return DB::transaction(function () use ($ciclos) {
            $resumen = [];

            foreach ($ciclos as $ciclo) {
                $resumen[$ciclo->id] = [
                    'id'                 => $ciclo->id,
                    'nombre'             => $ciclo->nombre,
                    'fecha_fin_anterior' => $ciclo->fecha_fin?->toDateString(),
                ];
                $this->calendarioGrupo->recalcularCiclo($ciclo);
            }

            // Segunda pasada: las copias releen a su origen ya recalculado
            $conCopias = DB::table('ciclo_grupo')
                ->whereIn('ciclo_id', $ciclos->pluck('id'))
                ->whereNotNull('ciclo_origen_id')
                ->pluck('ciclo_id')
                ->unique();

            foreach ($ciclos->whereIn('id', $conCopias) as $ciclo) {
                $this->calendarioGrupo->recalcularCiclo($ciclo);
            }

            foreach ($ciclos as $ciclo) {
                $actualizado = $ciclo->fresh();
                $resumen[$ciclo->id] += [
                    'fecha_fin'          => $actualizado->fecha_fin?->toDateString(),
                    'factor_ajuste'      => $actualizado->factor_ajuste,
                    'advertencia'        => $actualizado->advertencia_ajuste,
                    'clases_realineadas' => $this->realinearClases($ciclo),
                ];
            }

            return array_values($resumen);
        });
    }

    /**
     * Reubica las clases pendientes ('programada') del ciclo, grupo por grupo,
     * sobre las sesiones laborables a partir de fecha_inicio_grupo, en orden
     * cronológico y saltando los slots ocupados por otras clases.
     *
     * Si el grupo no está en el ciclo, no tiene fechas o no tiene horarios,
     * la clase se desplaza $diasDesplazamiento días y se corre al siguiente
     * día laborable de la sede del ciclo.
     *
     * @param Ciclo $ciclo
     * @param int   $diasDesplazamiento Desplazamiento para clases sin horario de referencia (negativo = hacia atrás)
     * @return int  cantidad de clases cuya fecha u hora cambió
     */
    public function realinearClases(Ciclo $ciclo, int $diasDesplazamiento = 0): int
    {
        $pendientesPorGrupo = AsistenciaClaseProgramada::where('ciclo_id', $ciclo->id)
            ->where('estado', 'programada')
            ->orderBy('fecha_clase')
            ->orderBy('hora_inicio')
            ->get()
            ->groupBy('grupo_id');

        if ($pendientesPorGrupo->isEmpty()) {
            return 0;
        }

        $grupos = $ciclo->grupos()->with('horarios')->get()->keyBy('id');

        return DB::transaction(function () use ($ciclo, $pendientesPorGrupo, $grupos, $diasDesplazamiento) {
            $movidas = 0;

            foreach ($pendientesPorGrupo as $grupoId => $pendientes) {
                $grupo  = $grupos->get($grupoId);
                $inicio = $grupo?->pivot->fecha_inicio_grupo;

                $destinos = ($grupo && $inicio && $grupo->horarios->isNotEmpty())
                    ? $this->slotsPorHorario($ciclo, $grupo, Carbon::parse($inicio), $pendientes->count())
                    : $this->slotsPorDesplazamiento($ciclo, $grupoId, $pendientes, $diasDesplazamiento);

                $movidas += $this->aplicarDestinos($pendientes, $destinos);
            }

            return $movidas;
        });
    }

    /**
     * IDs de ciclos activos afectados por un día no laborable.
     *
     * @param string   $fecha
     * @param int|null $sedeId
     * @return Collection<int, int>
     */
    private function ciclosAfectados(string $fecha, ?int $sedeId): Collection
    {
        return Ciclo::where('status', 1)
            ->where(function ($q) use ($fecha, $sedeId) {
                $q->where(function ($rango) use ($fecha, $sedeId) {
                    $rango->where('fecha_inicio', '<=', $fecha)
                        ->where(fn ($fin) => $fin->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $fecha))
                        ->when($sedeId, fn ($s) => $s->where('sede_id', $sedeId));
                })->orWhereHas('grupos', function ($g) use ($fecha, $sedeId) {
                    $g->where('ciclo_grupo.fecha_inicio_grupo', '<=', $fecha)
                        ->where('ciclo_grupo.fecha_fin_grupo', '>=', $fecha)
                        ->when($sedeId, fn ($s) => $s->where('grupos.sede_id', $sedeId));
                });
            })
            ->pluck('id');
    }

    /**
     * Primeros $cantidad slots laborables y libres del grupo desde $desde.
     *
     * @return array<int, array{fecha: string, hora_inicio: string, hora_fin: string, duracion_horas: float}>
     */
    private function slotsPorHorario(Ciclo $ciclo, Grupo $grupo, Carbon $desde, int $cantidad): array
    {
        $ocupados = $this->slotsOcupados($ciclo->id, $grupo->id);
        $slots    = [];
        $hasta    = $desde->copy()->addDays(CalendarioLaboralService::TOPE_DIAS);

        foreach ($this->calendarioLaboral->recorrerSesiones($grupo, $desde, $hasta) as $sesion) {
            if ($sesion['no_laborable'] !== null) {
                continue;
            }

            $horaInicio = Carbon::parse($sesion['horario']->hora);
            $clave      = $sesion['fecha']->toDateString() . '|' . $horaInicio->format('H:i:s');

            if (isset($ocupados[$clave])) {
                continue;
            }

            $slots[] = [
                'fecha'          => $sesion['fecha']->toDateString(),
                'hora_inicio'    => $horaInicio->format('H:i:s'),
                'hora_fin'       => $horaInicio->copy()->addHours($sesion['horario']->duracion_horas)->format('H:i:s'),
                'duracion_horas' => $sesion['horario']->duracion_horas,
            ];

            if (count($slots) === $cantidad) {
                break;
            }
        }

        return $slots;
    }

    /**
     * Destinos para clases sin horario de referencia: misma hora, fecha
     * desplazada y corrida al siguiente día laborable libre.
     *
     * @return array<int, array{fecha: string, hora_inicio: string, hora_fin: string, duracion_horas: float}>
     */
    private function slotsPorDesplazamiento(Ciclo $ciclo, int $grupoId, Collection $pendientes, int $dias): array
    {
        $ocupados = $this->slotsOcupados($ciclo->id, $grupoId);
        $slots    = [];

        foreach ($pendientes as $clase) {
            $hora  = Carbon::parse($clase->hora_inicio)->format('H:i:s');
            $fecha = $this->calendarioLaboral->siguienteDiaLaborable($clase->fecha_clase->copy()->addDays($dias), $ciclo->sede_id);

            while (isset($ocupados[$fecha->toDateString() . '|' . $hora])) {
                $fecha = $this->calendarioLaboral->siguienteDiaLaborable($fecha->addDay(), $ciclo->sede_id);
            }

            $ocupados[$fecha->toDateString() . '|' . $hora] = true;
            $slots[] = [
                'fecha'          => $fecha->toDateString(),
                'hora_inicio'    => $hora,
                'hora_fin'       => Carbon::parse($clase->hora_fin)->format('H:i:s'),
                'duracion_horas' => $clase->duracion_horas,
            ];
        }

        return $slots;
    }

    /**
     * Slots (fecha|hora) ocupados por clases que no se reubican, incluidas
     * las eliminadas lógicamente (el índice único también las cuenta).
     *
     * @return array<string, true>
     */
    private function slotsOcupados(int $cicloId, int $grupoId): array
    {
        return AsistenciaClaseProgramada::withTrashed()
            ->where('ciclo_id', $cicloId)
            ->where('grupo_id', $grupoId)
            ->where(fn ($q) => $q->where('estado', '!=', 'programada')->orWhereNotNull('deleted_at'))
            ->get(['fecha_clase', 'hora_inicio'])
            ->mapWithKeys(fn ($c) => [$c->fecha_clase->toDateString() . '|' . Carbon::parse($c->hora_inicio)->format('H:i:s') => true])
            ->all();
    }

    /**
     * Asigna los destinos en orden. Primero libera los slots moviendo las
     * clases a fechas temporales únicas y luego las ubica en su destino, para
     * no violar el índice único (grupo, ciclo, fecha, hora) durante el cambio.
     * Si hay menos destinos que clases (tope alcanzado), las restantes no se mueven.
     *
     * @return int clases cuya fecha u hora cambió
     */
    private function aplicarDestinos(Collection $pendientes, array $destinos): int
    {
        $pendientes = $pendientes->values()->take(count($destinos));
        $cambios    = [];

        foreach ($pendientes as $i => $clase) {
            $destino = $destinos[$i];
            $actual  = $clase->fecha_clase->toDateString() . '|' . Carbon::parse($clase->hora_inicio)->format('H:i:s');

            if ($actual !== $destino['fecha'] . '|' . $destino['hora_inicio']) {
                $cambios[] = [$clase, $destino];
            }
        }

        $temporal = Carbon::parse(self::FECHA_TEMPORAL);
        foreach ($cambios as $i => [$clase]) {
            $clase->update(['fecha_clase' => $temporal->copy()->addDays($i)->toDateString()]);
        }

        foreach ($cambios as [$clase, $destino]) {
            $clase->update([
                'fecha_clase'    => $destino['fecha'],
                'hora_inicio'    => $destino['hora_inicio'],
                'hora_fin'       => $destino['hora_fin'],
                'duracion_horas' => $destino['duracion_horas'],
            ]);
        }

        return count($cambios);
    }
}
