<?php

namespace App\Services\Asistencia;

use App\Models\Academico\AsistenciaConfiguracion;
use App\Services\Asistencia\CalcularPorcentajeAsistenciaService;

class VerificarCumplimientoService
{
    protected CalcularPorcentajeAsistenciaService $calcularService;

    public function __construct(CalcularPorcentajeAsistenciaService $calcularService)
    {
        $this->calcularService = $calcularService;
    }

    /**
     * Verifica si un estudiante cumple con el mínimo de asistencia requerido.
     *
     * @param int $estudianteId
     * @param int $cursoId
     * @param int|null $moduloId
     * @return array
     */
    public function verificar(int $estudianteId, int $cursoId, ?int $moduloId = null): array
    {
        // Obtener configuración vigente
        $configuracion = AsistenciaConfiguracion::obtenerPara($cursoId, $moduloId);

        if (!$configuracion) {
            return [
                'cumple' => false,
                'porcentaje' => 0,
                'porcentaje_minimo' => 0,
                'mensaje' => 'No se encontró una configuración de asistencia vigente.',
            ];
        }

        // Calcular porcentaje de asistencia
        $porcentaje = $moduloId 
            ? $this->calcularService->porModulo($estudianteId, 0, 0) // Necesitaría grupo_id y ciclo_id
            : $this->calcularService->porCurso($estudianteId, $cursoId);

        // Comparar con mínimo
        $cumple = $porcentaje >= $configuracion->porcentaje_minimo;

        return [
            'cumple' => $cumple,
            'porcentaje' => round($porcentaje, 2),
            'porcentaje_minimo' => (float) $configuracion->porcentaje_minimo,
            'diferencia' => round($porcentaje - $configuracion->porcentaje_minimo, 2),
            'mensaje' => $cumple 
                ? 'El estudiante cumple con el mínimo de asistencia requerido.'
                : 'El estudiante no cumple con el mínimo de asistencia requerido.',
            'configuracion' => [
                'id' => $configuracion->id,
                'aplicar_justificaciones' => $configuracion->aplicar_justificaciones,
                'perder_por_fallas' => $configuracion->perder_por_fallas,
            ],
        ];
    }
}

