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
    public function generarParaGrupoCiclo(int $grupoId, int $cicloId): array
    {
        try {
            DB::beginTransaction();

            $grupo = Grupo::with(['horarios', 'modulo'])->findOrFail($grupoId);
            $ciclo = Ciclo::findOrFail($cicloId);

            // Obtener fechas del grupo en el ciclo desde la tabla pivot
            $pivot = $ciclo->grupos()->where('grupos.id', $grupoId)->first();
            
            if (!$pivot || !$pivot->pivot->fecha_inicio_grupo || !$pivot->pivot->fecha_fin_grupo) {
                throw new \Exception('El grupo no tiene fechas configuradas en el ciclo.');
            }

            $fechaInicio = Carbon::parse($pivot->pivot->fecha_inicio_grupo);
            $fechaFin = Carbon::parse($pivot->pivot->fecha_fin_grupo);

            // Obtener horarios del grupo
            $horarios = $grupo->horarios;
            
            if ($horarios->isEmpty()) {
                throw new \Exception('El grupo no tiene horarios configurados.');
            }

            $clasesGeneradas = [];
            $fechaActual = $fechaInicio->copy();

            // Generar clases hasta la fecha fin
            while ($fechaActual <= $fechaFin) {
                foreach ($horarios as $horario) {
                    // Verificar si el día de la semana coincide con el horario
                    $diaSemana = $fechaActual->dayOfWeek; // 0 = Domingo, 6 = Sábado
                    
                    // Ajustar para que coincida con el formato del horario (1 = Lunes, 7 = Domingo)
                    $diaHorario = $diaSemana == 0 ? 7 : $diaSemana;
                    
                    if ($horario->dia == $diaHorario) {
                        // Verificar si ya existe una clase para esta fecha y hora
                        $claseExistente = AsistenciaClaseProgramada::where('grupo_id', $grupoId)
                            ->where('ciclo_id', $cicloId)
                            ->whereDate('fecha_clase', $fechaActual->format('Y-m-d'))
                            ->where('hora_inicio', $horario->hora_inicio)
                            ->first();

                        if (!$claseExistente) {
                            $horaInicio = Carbon::parse($horario->hora_inicio);
                            $horaFin = Carbon::parse($horario->hora_fin);
                            $duracionHoras = $horaInicio->diffInMinutes($horaFin) / 60;

                            $clase = AsistenciaClaseProgramada::create([
                                'grupo_id' => $grupoId,
                                'ciclo_id' => $cicloId,
                                'fecha_clase' => $fechaActual->format('Y-m-d'),
                                'hora_inicio' => $horario->hora_inicio,
                                'hora_fin' => $horario->hora_fin,
                                'duracion_horas' => round($duracionHoras, 2),
                                'estado' => 'programada',
                                'creado_por_id' => auth()->id(),
                                'fecha_programacion' => now(),
                            ]);

                            $clasesGeneradas[] = $clase;
                        }
                    }
                }

                $fechaActual->addDay();
            }

            DB::commit();

            return [
                'success' => true,
                'message' => count($clasesGeneradas) . ' clases generadas exitosamente.',
                'clases_generadas' => count($clasesGeneradas),
                'data' => $clasesGeneradas,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Error al generar las clases: ' . $e->getMessage(),
                'clases_generadas' => 0,
            ];
        }
    }
}

