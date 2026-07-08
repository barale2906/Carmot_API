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
     * Elimina entradas duplicadas por slot (dia + hora), conservando la última ocurrencia.
     *
     * Un grupo no puede estar en dos áreas al mismo tiempo. Cuando el frontend envía los
     * horarios actuales (área vieja) junto con los nuevos (área nueva) para el mismo slot,
     * este método descarta la ocurrencia anterior y se queda con la más reciente, que es la
     * que el usuario acaba de asignar.
     *
     * @param array $horariosData Array de horarios del request
     * @return array Array deduplicado (valores re-indexados)
     */
    protected function deduplicarHorariosPorSlot(array $horariosData): array
    {
        $porSlot = [];
        foreach ($horariosData as $horario) {
            $clave = $horario['dia'] . '_' . $horario['hora'];
            $porSlot[$clave] = $horario; // Sobreescribe: gana la última ocurrencia (la nueva)
        }
        return array_values($porSlot);
    }

    /**
     * Valida que los horarios enviados en la solicitud no se solapen entre sí (mismo grupo).
     * No comprueba conflictos con otros grupos — un área puede ser compartida por múltiples
     * grupos en el mismo horario (requerimiento de cliente).
     *
     * Debe llamarse sobre el array ya deduplicado con deduplicarHorariosPorSlot().
     * Solo detecta solapamientos reales: bloques con distinta hora de inicio cuyo rango
     * se superpone.
     *
     * @param array $horariosData Array de horarios ya deduplicado
     * @return array Array con 'valido' (bool) y 'errores' (array)
     */
    protected function validarSolapamientoHorarios(array $horariosData): array
    {
        $errores = [];
        $horariosPorDia = [];

        // Agrupar por día conservando el índice original (base 0)
        foreach ($horariosData as $index => $horario) {
            $horariosPorDia[$horario['dia']][] = [
                'index'          => $index,
                'hora'           => $horario['hora'],
                'duracion_horas' => $horario['duracion_horas'] ?? 1,
            ];
        }

        foreach ($horariosPorDia as $dia => $horarios) {
            if (count($horarios) <= 1) {
                continue;
            }

            // Ordenar por hora de inicio
            usort($horarios, fn($a, $b) => strcmp($a['hora'], $b['hora']));

            for ($i = 0; $i < count($horarios) - 1; $i++) {
                $actual    = $horarios[$i];
                $siguiente = $horarios[$i + 1];

                $horaFin = date('H:i', strtotime($actual['hora'] . ' +' . $actual['duracion_horas'] . ' hour'));

                if ($horaFin > $siguiente['hora']) {
                    $errores[] = "Los horarios del día {$dia} se solapan: "
                        . "{$actual['hora']}–{$horaFin} (posición " . ($actual['index'] + 1) . ") "
                        . "y {$siguiente['hora']} (posición " . ($siguiente['index'] + 1) . ").";
                }
            }
        }

        return [
            'valido'  => empty($errores),
            'errores' => $errores,
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
