<?php

namespace App\Services\Asistencia;

use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Grupo;
use App\Services\Academico\CalendarioLaboralService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Genera las clases programadas de un grupo dentro de un ciclo a partir de
 * sus horarios y del rango del pivot ciclo_grupo, omitiendo los días no
 * laborables de la sede del grupo.
 */
class GenerarClasesProgramadasService
{
    public function __construct(private readonly CalendarioLaboralService $calendarioLaboral)
    {
    }

    /**
     * Genera clases programadas automáticamente para un grupo y ciclo.
     * No duplica clases ya existentes para el mismo slot (fecha + hora).
     *
     * @param int $grupoId
     * @param int $cicloId
     * @return array{success: bool, message: string, clases_generadas: int, clases_omitidas?: int, data?: array}
     */
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

            if ($grupo->horarios->isEmpty()) {
                throw new \Exception('El grupo no tiene horarios configurados.');
            }

            $clasesGeneradas = [];
            $clasesOmitidas  = 0;

            foreach ($this->calendarioLaboral->recorrerSesiones($grupo, $fechaInicio, $fechaFin) as $sesion) {
                if ($sesion['no_laborable'] !== null) {
                    $clasesOmitidas++;
                    continue;
                }

                $horario       = $sesion['horario'];
                $fechaClase    = $sesion['fecha']->format('Y-m-d');
                $horaInicio    = Carbon::parse($horario->hora);
                $horaFin       = $horaInicio->copy()->addHours($horario->duracion_horas);
                $horaInicioStr = $horaInicio->format('H:i:s');

                $existe = AsistenciaClaseProgramada::where('grupo_id', $grupoId)
                    ->where('ciclo_id', $cicloId)
                    ->whereDate('fecha_clase', $fechaClase)
                    ->where('hora_inicio', $horaInicioStr)
                    ->exists();

                if (! $existe) {
                    $clasesGeneradas[] = AsistenciaClaseProgramada::create([
                        'grupo_id'          => $grupoId,
                        'ciclo_id'          => $cicloId,
                        'fecha_clase'       => $fechaClase,
                        'hora_inicio'       => $horaInicioStr,
                        'hora_fin'          => $horaFin->format('H:i:s'),
                        'duracion_horas'    => $horario->duracion_horas,
                        'estado'            => 'programada',
                        'creado_por_id'     => auth()->id(),
                        'fecha_programacion' => now(),
                    ]);
                }
            }

            DB::commit();

            return [
                'success'          => true,
                'message'          => count($clasesGeneradas) . ' clases generadas exitosamente.',
                'clases_generadas' => count($clasesGeneradas),
                'clases_omitidas'  => $clasesOmitidas,
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

