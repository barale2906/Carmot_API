<?php

namespace App\Services\Academico;

use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use App\Services\Asistencia\VerificarCumplimientoService;
use Carbon\Carbon;

/**
 * Evalúa si una matrícula puede cerrarse como Graduada (N20): el ciclo terminó, todos
 * sus módulos tienen la nota completa y alcanzan `academico.nota_aprobatoria`, y el
 * estudiante cumple la regla de asistencia del curso (N19).
 *
 * La cartera pendiente no impide graduar: sigue siendo exigible y se informa aparte.
 */
class EvaluacionCierreService
{
    public function __construct(
        private readonly SabanaNotasService $sabanaNotas,
        private readonly VerificarCumplimientoService $cumplimiento
    ) {
    }

    /**
     * @param Matricula   $matricula
     * @param Carbon|null $fecha Fecha del cierre; por defecto hoy.
     * @return array<string, mixed> `aprueba` y `motivos` resumen el resultado.
     */
    public function evaluar(Matricula $matricula, ?Carbon $fecha = null): array
    {
        $fecha = ($fecha ?? now())->copy()->startOfDay();
        $matricula->loadMissing(['ciclo.grupos.modulo']);

        $ciclo           = $matricula->ciclo;
        $cicloTerminado  = $ciclo && $ciclo->fecha_fin && $ciclo->fecha_fin->lte($fecha);
        $notaAprobatoria = (float) config('academico.nota_aprobatoria');
        $motivos         = [];

        if (! $cicloTerminado) {
            $motivos[] = $ciclo?->fecha_fin
                ? "El ciclo termina el {$ciclo->fecha_fin->format('d/m/Y')}."
                : 'El ciclo no tiene fecha de terminación.';
        }

        $sabana   = $this->sabanaNotas->porMatricula($matricula);
        $porGrupo = collect($sabana['modulos'] ?? [])->keyBy('grupo.id');
        $modulos  = ($ciclo?->grupos ?? collect())->map(function ($grupo) use ($porGrupo, $notaAprobatoria, &$motivos) {
            $fila      = $porGrupo->get($grupo->id);
            $notaFinal = $fila['nota_final'] ?? null;
            $completo  = (bool) ($fila['completo'] ?? false);
            $aprobado  = $completo && $notaFinal >= $notaAprobatoria;
            $nombre    = $grupo->modulo->nombre;

            if (! $fila) {
                $motivos[] = "El módulo {$nombre} no tiene esquema de calificación.";
            } elseif (! $completo) {
                $motivos[] = "El módulo {$nombre} tiene notas pendientes.";
            } elseif (! $aprobado) {
                $motivos[] = "El módulo {$nombre} tiene nota final {$notaFinal} (mínimo {$notaAprobatoria}).";
            }

            return [
                'modulo'     => ['id' => $grupo->modulo->id, 'nombre' => $nombre],
                'grupo'      => ['id' => $grupo->id, 'nombre' => $grupo->nombre],
                'nota_final' => $notaFinal,
                'completo'   => $completo,
                'aprobado'   => $aprobado,
            ];
        })->values();

        $asistencia = $this->cumplimiento->porMatricula($matricula);

        if (! $asistencia['cumple']) {
            $detalle = $asistencia['porcentaje_minimo'] !== null && $asistencia['porcentaje'] < $asistencia['porcentaje_minimo']
                ? "asistencia {$asistencia['porcentaje']} % (mínimo {$asistencia['porcentaje_minimo']} %)"
                : "{$asistencia['horas_asistidas']} horas asistidas (mínimo {$asistencia['horas_minimas']})";
            $motivos[] = $asistencia['pierde_por_fallas']
                ? "Pierde por fallas: {$detalle}."
                : "No cumple el mínimo de asistencia: {$detalle}.";
        }

        return [
            'ciclo_terminado'  => $cicloTerminado,
            'nota_aprobatoria' => $notaAprobatoria,
            'modulos'          => $modulos,
            'asistencia'       => $asistencia,
            'saldo_pendiente'  => (float) $matricula->carteras()
                ->whereIn('status', [Cartera::getStatusKey('Activa'), Cartera::getStatusKey('Abonada')])
                ->sum('saldo'),
            'aprueba'          => $motivos === [],
            'motivos'          => $motivos,
        ];
    }
}
