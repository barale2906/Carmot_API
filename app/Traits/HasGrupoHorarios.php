<?php

namespace App\Traits;

use App\Models\Configuracion\Horario;
use Illuminate\Support\Facades\DB;

/**
 * Trait HasGrupoHorarios
 *
 * Proporciona funcionalidades para manejar horarios de grupos.
 * Incluye métodos para crear, actualizar y eliminar horarios específicos de grupos.
 */
trait HasGrupoHorarios
{
    /**
     * Asigna horarios a un grupo específico.
     *
     * @param \App\Models\Academico\Grupo $grupo El grupo al que se asignarán los horarios
     * @param array $horariosData Array de datos de horarios
     * @return bool True si se asignaron correctamente, false en caso contrario
     */
    protected function asignarHorariosAGrupo($grupo, array $horariosData): bool
    {
        try {
            DB::beginTransaction();

            // Eliminar horarios existentes del grupo
            $grupo->horarios()->delete();

            // Crear nuevos horarios
            $horarios = [];
            foreach ($horariosData as $horarioData) {
                $horarios[] = [
                    'sede_id' => $grupo->sede_id,
                    'area_id' => $horarioData['area_id'],
                    'grupo_id' => $grupo->id,
                    'grupo_nombre' => $grupo->nombre,
                    'tipo' => false, // Horario de grupo
                    'periodo' => true, // Hora de inicio
                    'dia' => $horarioData['dia'],
                    'hora' => $horarioData['hora'],
                    'duracion_horas' => $horarioData['duracion_horas'] ?? 1,
                    'status' => $horarioData['status'] ?? 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            Horario::insert($horarios);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * Actualiza los horarios de un grupo específico.
     *
     * @param \App\Models\Academico\Grupo $grupo El grupo cuyos horarios se actualizarán
     * @param array $horariosData Array de datos de horarios
     * @return bool True si se actualizaron correctamente, false en caso contrario
     */
    protected function actualizarHorariosDeGrupo($grupo, array $horariosData): bool
    {
        return $this->asignarHorariosAGrupo($grupo, $horariosData);
    }

    /**
     * Elimina todos los horarios de un grupo específico.
     *
     * @param \App\Models\Academico\Grupo $grupo El grupo cuyos horarios se eliminarán
     * @return bool True si se eliminaron correctamente, false en caso contrario
     */
    protected function eliminarHorariosDeGrupo($grupo): bool
    {
        try {
            $grupo->horarios()->delete();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtiene los horarios de un grupo con filtros opcionales.
     *
     * @param \App\Models\Academico\Grupo $grupo El grupo del cual obtener los horarios
     * @param array $filtros Filtros opcionales (status, dia)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function obtenerHorariosDeGrupo($grupo, array $filtros = [])
    {
        $query = $grupo->horarios()->with(['area']);

        if (isset($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        if (isset($filtros['dia'])) {
            $query->where('dia', $filtros['dia']);
        }

        return $query->orderBy('dia')->orderBy('hora')->get();
    }

    /**
     * Valida que los horarios no se solapen para el mismo grupo.
     *
     * @param array $horariosData Array de datos de horarios
     * @return array Array con 'valido' (bool) y 'errores' (array)
     */
    protected function validarSolapamientoHorarios(array $horariosData): array
    {
        $errores = [];
        $horariosPorDia = [];

        // Agrupar horarios por día
        foreach ($horariosData as $index => $horario) {
            $dia = $horario['dia'];
            $hora = $horario['hora'];
            $duracion = $horario['duracion_horas'] ?? 1;

            if (!isset($horariosPorDia[$dia])) {
                $horariosPorDia[$dia] = [];
            }

            $horariosPorDia[$dia][] = [
                'index' => $index,
                'hora' => $hora,
                'duracion_horas' => $duracion,
            ];
        }

        // Verificar solapamientos por día
        foreach ($horariosPorDia as $dia => $horarios) {
            if (count($horarios) > 1) {
                // Ordenar por hora
                usort($horarios, function ($a, $b) {
                    return strcmp($a['hora'], $b['hora']);
                });

                // Verificar solapamientos consecutivos
                for ($i = 0; $i < count($horarios) - 1; $i++) {
                    $horaActual = $horarios[$i]['hora'];
                    $duracionActual = $horarios[$i]['duracion_horas'] ?? 1;
                    $horaSiguiente = $horarios[$i + 1]['hora'];

                    // Calcular hora de fin considerando la duración
                    $horaFinActual = date('H:i', strtotime($horaActual . ' +' . $duracionActual . ' hour'));

                    if ($horaFinActual > $horaSiguiente) {
                        $errores[] = "Los horarios del día {$dia} se solapan. Verifique los horarios en las posiciones " .
                                   ($horarios[$i]['index'] + 1) . " y " . ($horarios[$i + 1]['index'] + 1);
                    }
                }
            }
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores
        ];
    }

    /**
     * Obtiene estadísticas de horarios de un grupo.
     *
     * @param \App\Models\Academico\Grupo $grupo El grupo del cual obtener estadísticas
     * @return array Array con estadísticas de horarios
     */
    protected function obtenerEstadisticasHorariosGrupo($grupo): array
    {
        $horarios = $grupo->horarios;

        return [
            'total_horarios' => $horarios->count(),
            'total_horas_semana' => $grupo->total_horas_semana,
            'dias_clase' => $grupo->dias_clase,
            'horarios_por_dia' => $horarios->groupBy('dia')->map(function ($horariosDia) {
                return [
                    'cantidad' => $horariosDia->count(),
                    'horas' => $horariosDia->count(), // Asumiendo 1 hora por horario
                    'horarios' => $horariosDia->pluck('hora')->toArray()
                ];
            }),
            'areas_utilizadas' => $horarios->pluck('area.nombre')->unique()->values()->toArray(),
        ];
    }
}
