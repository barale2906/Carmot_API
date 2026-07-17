<?php

namespace App\Services\Asistencia;

use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Grupo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerarClasesProgramadasService
{
    /**
     * Genera clases programadas automáticamente para un grupo y ciclo.
     *
     * @param int $grupoId
     * @param int $cicloId
     * @return array
     */
    /**
     * Mapeo de dayOfWeek de Carbon (0=dom) al nombre del día en la tabla horarios.
     */
    private const DIAS = [
        0 => 'domingo',
        1 => 'lunes',
        2 => 'martes',
        3 => 'miércoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sábado',
    ];

    public function generarParaGrupoCiclo(int $grupoId, int $cicloId): array
    {
        try {
            DB::beginTransaction();

            $grupo = Grupo::with(['horarios', 'modulo'])->findOrFail($grupoId);
            $ciclo = Ciclo::findOrFail($cicloId);

            $pivot = $ciclo->grupos()->where('grupos.id', $grupoId)->first();

            if (! $pivot || ! $pivot->pivot->fecha_inicio_grupo || ! $pivot->pivot->fecha_fin_grupo) {
                throw new \Exception('El grupo no tiene fechas configuradas en el ciclo.');
            }

            $fechaInicio = Carbon::parse($pivot->pivot->fecha_inicio_grupo);
            $fechaFin    = Carbon::parse($pivot->pivot->fecha_fin_grupo);

            $horarios = $grupo->horarios;

            if ($horarios->isEmpty()) {
                throw new \Exception('El grupo no tiene horarios configurados.');
            }

            $clasesGeneradas = [];
            $fechaActual     = $fechaInicio->copy();

            while ($fechaActual <= $fechaFin) {
                $diaNombre = self::DIAS[$fechaActual->dayOfWeek];

                foreach ($horarios as $horario) {
                    if ($horario->dia !== $diaNombre) {
                        continue;
                    }

                    $horaInicio    = Carbon::parse($horario->hora);
                    $horaFin       = $horaInicio->copy()->addHours($horario->duracion_horas);
                    $horaInicioStr = $horaInicio->format('H:i:s');

                    $existe = AsistenciaClaseProgramada::where('grupo_id', $grupoId)
                        ->where('ciclo_id', $cicloId)
                        ->whereDate('fecha_clase', $fechaActual->format('Y-m-d'))
                        ->where('hora_inicio', $horaInicioStr)
                        ->exists();

                    if (! $existe) {
                        $clasesGeneradas[] = AsistenciaClaseProgramada::create([
                            'grupo_id'          => $grupoId,
                            'ciclo_id'          => $cicloId,
                            'fecha_clase'       => $fechaActual->format('Y-m-d'),
                            'hora_inicio'       => $horaInicioStr,
                            'hora_fin'          => $horaFin->format('H:i:s'),
                            'duracion_horas'    => $horario->duracion_horas,
                            'estado'            => 'programada',
                            'creado_por_id'     => auth()->id(),
                            'fecha_programacion' => now(),
                        ]);
                    }
                }

                $fechaActual->addDay();
            }

            DB::commit();

            return [
                'success'          => true,
                'message'          => count($clasesGeneradas) . ' clases generadas exitosamente.',
                'clases_generadas' => count($clasesGeneradas),
                'data'             => $clasesGeneradas,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success'          => false,
                'message'          => 'Error al generar las clases: ' . $e->getMessage(),
                'clases_generadas' => 0,
            ];
        }
    }
}

