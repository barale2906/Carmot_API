<?php

namespace App\Services\Asistencia;

use App\Models\Academico\Asistencia;
use App\Models\Academico\AsistenciaConfiguracion;
use App\Models\Academico\Matricula;
use Illuminate\Support\Collection;

/**
 * Aplica la regla de asistencia vigente (porcentaje mínimo, horas mínimas, si cuentan
 * las justificadas y si se pierde por fallas) a las asistencias de un estudiante.
 *
 * Lo usan el reporte de asistencia por grupo y la evaluación de cierre de la matrícula
 * (N19), para que ambos digan lo mismo.
 */
class VerificarCumplimientoService
{
    /**
     * Cumplimiento de la matrícula en su ciclo, con la regla del curso (incluye horas mínimas).
     *
     * Agrega `perdida_definitiva` (N24): con la regla "pierde por fallas", las horas faltadas
     * ya superan lo que el mínimo permite sobre el total de horas del ciclo (o ya no alcanzan
     * las horas mínimas), así que el estudiante no puede recuperarse aunque asista al resto.
     *
     * @param Matricula $matricula
     * @return array<string, mixed>
     */
    public function porMatricula(Matricula $matricula): array
    {
        $asistencias = Asistencia::where('estudiante_id', $matricula->estudiante_id)
            ->where('ciclo_id', $matricula->ciclo_id)
            ->with('claseProgramada')
            ->get();

        $configuracion = AsistenciaConfiguracion::obtenerPara($matricula->curso_id);
        $resultado     = $this->evaluar($asistencias, $configuracion, true);

        $matricula->loadMissing('ciclo.grupos.modulo');
        $horasCiclo   = (float) $matricula->ciclo?->grupos->sum(fn ($grupo) => (float) $grupo->modulo?->duracion);
        $horasFaltas  = $resultado['horas_totales'] - $resultado['horas_asistidas'];
        $maxAsistible = $horasCiclo - $horasFaltas;

        $resultado['horas_ciclo']        = round($horasCiclo, 2);
        $resultado['perdida_definitiva'] = (bool) $configuracion?->perder_por_fallas && $horasCiclo > 0 && (
            ($resultado['porcentaje_minimo'] !== null && $maxAsistible / $horasCiclo * 100 < $resultado['porcentaje_minimo'])
            || ($resultado['horas_minimas'] !== null && $maxAsistible < $resultado['horas_minimas'])
        );

        return $resultado;
    }

    /**
     * Evalúa un conjunto de asistencias contra una configuración. Sin configuración no hay
     * mínimo que cumplir. Las horas mínimas solo aplican al curso completo.
     *
     * @param Collection<int, Asistencia>   $asistencias
     * @param AsistenciaConfiguracion|null  $configuracion
     * @param bool                          $conHorasMinimas
     * @return array<string, mixed>
     */
    public function evaluar(Collection $asistencias, ?AsistenciaConfiguracion $configuracion, bool $conHorasMinimas = false): array
    {
        $cuentaJustificadas = ! $configuracion || $configuracion->aplicar_justificaciones;
        $horas              = fn (Asistencia $a) => (float) ($a->claseProgramada->duracion_horas ?? 0);

        $horasTotales   = $asistencias->sum($horas);
        $horasAsistidas = $asistencias
            ->filter(fn (Asistencia $a) => $a->esPresente() || ($cuentaJustificadas && $a->esJustificada()))
            ->sum($horas);
        $porcentaje = $horasTotales > 0 ? round($horasAsistidas / $horasTotales * 100, 2) : 0.0;

        $minimo       = $configuracion ? (float) $configuracion->porcentaje_minimo : null;
        $horasMinimas = $conHorasMinimas && $configuracion?->horas_minimas ? (float) $configuracion->horas_minimas : null;
        $cumple       = ($minimo === null || $porcentaje >= $minimo)
            && ($horasMinimas === null || $horasAsistidas >= $horasMinimas);

        return [
            'porcentaje'        => $porcentaje,
            'horas_totales'     => round($horasTotales, 2),
            'horas_asistidas'   => round($horasAsistidas, 2),
            'porcentaje_minimo' => $minimo,
            'horas_minimas'     => $horasMinimas,
            'cumple'            => $cumple,
            'pierde_por_fallas' => ! $cumple && (bool) $configuracion?->perder_por_fallas,
            'configuracion_id'  => $configuracion?->id,
        ];
    }
}
