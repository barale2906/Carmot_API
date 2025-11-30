<?php

namespace App\Services\Asistencia;

use App\Models\Academico\Asistencia;
use App\Models\Academico\AsistenciaConfiguracion;

class CalcularPorcentajeAsistenciaService
{
    /**
     * Calcula el porcentaje de asistencia por módulo.
     *
     * @param int $estudianteId
     * @param int $grupoId
     * @param int $cicloId
     * @return float
     */
    public function porModulo(int $estudianteId, int $grupoId, int $cicloId): float
    {
        $asistencias = Asistencia::where('estudiante_id', $estudianteId)
            ->where('grupo_id', $grupoId)
            ->where('ciclo_id', $cicloId)
            ->with('claseProgramada')
            ->get();

        $horasTotales = $asistencias->sum(function ($asistencia) {
            return $asistencia->claseProgramada->duracion_horas ?? 0;
        });

        $horasAsistidas = $asistencias->filter(function ($asistencia) {
            return $asistencia->contarParaMinimo();
        })->sum(function ($asistencia) {
            return $asistencia->claseProgramada->duracion_horas ?? 0;
        });

        return $horasTotales > 0 ? ($horasAsistidas / $horasTotales) * 100 : 0;
    }

    /**
     * Calcula el porcentaje de asistencia por curso.
     *
     * @param int $estudianteId
     * @param int $cursoId
     * @return float
     */
    public function porCurso(int $estudianteId, int $cursoId): float
    {
        $asistencias = Asistencia::where('estudiante_id', $estudianteId)
            ->where('curso_id', $cursoId)
            ->with('claseProgramada')
            ->get();

        $horasTotales = $asistencias->sum(function ($asistencia) {
            return $asistencia->claseProgramada->duracion_horas ?? 0;
        });

        $horasAsistidas = $asistencias->filter(function ($asistencia) {
            return $asistencia->contarParaMinimo();
        })->sum(function ($asistencia) {
            return $asistencia->claseProgramada->duracion_horas ?? 0;
        });

        return $horasTotales > 0 ? ($horasAsistidas / $horasTotales) * 100 : 0;
    }

    /**
     * Calcula el porcentaje de asistencia general del estudiante.
     *
     * @param int $estudianteId
     * @return float
     */
    public function general(int $estudianteId): float
    {
        $asistencias = Asistencia::where('estudiante_id', $estudianteId)
            ->with('claseProgramada')
            ->get();

        $horasTotales = $asistencias->sum(function ($asistencia) {
            return $asistencia->claseProgramada->duracion_horas ?? 0;
        });

        $horasAsistidas = $asistencias->filter(function ($asistencia) {
            return $asistencia->contarParaMinimo();
        })->sum(function ($asistencia) {
            return $asistencia->claseProgramada->duracion_horas ?? 0;
        });

        return $horasTotales > 0 ? ($horasAsistidas / $horasTotales) * 100 : 0;
    }
}

