<?php

namespace App\Services\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Grupo;
use Carbon\Carbon;

/**
 * PlaneacionCicloService
 *
 * Construye la planeación detallada de un ciclo: por cada grupo (módulo) sus
 * tópicos y temas con la duración original, las horas ajustadas según el
 * factor del grupo (horas_planeadas / duración del módulo) y las fechas
 * estimadas en que se dicta cada tema, repartiendo las horas en orden sobre
 * las sesiones laborables reales del grupo.
 *
 * Es de solo lectura: nunca modifica temas.duracion (el currículo es común a
 * todos los ciclos); el ajuste vive únicamente en ciclo_grupo.horas_planeadas.
 */
class PlaneacionCicloService
{
    public function __construct(private readonly CalendarioLaboralService $calendarioLaboral)
    {
    }

    /**
     * Planeación por grupo → tópico → tema del ciclo.
     *
     * @param Ciclo $ciclo
     * @return array
     */
    public function planeacion(Ciclo $ciclo): array
    {
        $grupos = $ciclo->grupos()
            ->with([
                'horarios',
                'modulo.topicos' => fn ($q) => $q->orderBy('topico_modulo.id'),
                'modulo.topicos.temas' => fn ($q) => $q->orderBy('tema_topico.id'),
            ])
            ->orderBy('ciclo_grupo.orden')
            ->get();

        return [
            'ciclo' => [
                'id'                   => $ciclo->id,
                'nombre'               => $ciclo->nombre,
                'fecha_inicio'         => $ciclo->fecha_inicio?->format('Y-m-d'),
                'fecha_fin'            => $ciclo->fecha_fin?->format('Y-m-d'),
                'fecha_fin_teorica'    => $ciclo->fecha_fin_teorica?->format('Y-m-d'),
                'fecha_fin_automatica' => $ciclo->fecha_fin_automatica,
                'factor_ajuste'        => $ciclo->factor_ajuste,
                'advertencia'          => $ciclo->advertencia_ajuste,
            ],
            'grupos' => $grupos->map(fn (Grupo $grupo) => $this->planeacionGrupo($grupo))->values()->all(),
        ];
    }

    /**
     * Planeación de un grupo: reparte sus horas planeadas entre los temas del módulo.
     */
    private function planeacionGrupo(Grupo $grupo): array
    {
        $duracion = (float) ($grupo->modulo->duracion ?? 0);
        $horas    = $grupo->pivot->horas_planeadas !== null ? (float) $grupo->pivot->horas_planeadas : $duracion;
        $factor   = $duracion > 0 ? $horas / $duracion : 1.0;
        $sesiones = $this->sesiones($grupo);

        $acumulado = 0.0;
        $topicos   = [];

        foreach ($grupo->modulo?->topicos ?? [] as $topico) {
            $temas = [];

            foreach ($topico->temas as $tema) {
                $original  = (float) $tema->duracion;
                $ajustadas = round($original * $factor, 2);

                $temas[] = [
                    'tema_id'          => $tema->id,
                    'nombre'           => $tema->nombre,
                    'horas_originales' => $original,
                    'horas_ajustadas'  => $ajustadas,
                    'tiempo_ajustado'  => $this->formatoHoras($ajustadas),
                    'fecha_inicio'     => $this->fechaEnHora($sesiones, $acumulado, false),
                    'fecha_fin'        => $this->fechaEnHora($sesiones, $acumulado + $ajustadas, true),
                ];

                $acumulado += $ajustadas;
            }

            $topicos[] = [
                'topico_id'        => $topico->id,
                'nombre'           => $topico->nombre,
                'horas_originales' => (float) collect($temas)->sum('horas_originales'),
                'horas_ajustadas'  => round(collect($temas)->sum('horas_ajustadas'), 2),
                'temas'            => $temas,
            ];
        }

        return [
            'grupo_id'           => $grupo->id,
            'grupo_nombre'       => $grupo->nombre,
            'orden'              => $grupo->pivot->orden,
            'modulo'             => $grupo->modulo ? ['id' => $grupo->modulo->id, 'nombre' => $grupo->modulo->nombre] : null,
            'fecha_inicio_grupo' => $grupo->pivot->fecha_inicio_grupo,
            'fecha_fin_grupo'    => $grupo->pivot->fecha_fin_grupo,
            'ciclo_origen_id'    => $grupo->pivot->ciclo_origen_id,
            'horas_modulo'       => $duracion,
            'horas_planeadas'    => $horas,
            'factor'             => round($factor, 4),
            'sesiones'           => count($sesiones),
            'topicos'            => $topicos,
        ];
    }

    /**
     * Sesiones laborables del grupo dentro de su rango en el ciclo, con las horas acumuladas al terminar cada una.
     *
     * @return array<int, array{fecha: string, acumulado: float}>
     */
    private function sesiones(Grupo $grupo): array
    {
        if (! $grupo->pivot->fecha_inicio_grupo || ! $grupo->pivot->fecha_fin_grupo) {
            return [];
        }

        $sesiones  = [];
        $acumulado = 0.0;
        $recorrido = $this->calendarioLaboral->recorrerSesiones(
            $grupo,
            Carbon::parse($grupo->pivot->fecha_inicio_grupo),
            Carbon::parse($grupo->pivot->fecha_fin_grupo)
        );

        foreach ($recorrido as $sesion) {
            if ($sesion['no_laborable'] === null) {
                $acumulado += $sesion['horario']->duracion_horas;
                $sesiones[] = ['fecha' => $sesion['fecha']->toDateString(), 'acumulado' => $acumulado];
            }
        }

        return $sesiones;
    }

    /**
     * Fecha de la sesión en la que cae una hora acumulada del contenido.
     * Para el inicio de un tema se busca la sesión que contiene la hora
     * siguiente a $horas; para el fin, la sesión que alcanza $horas.
     *
     * @param array $sesiones
     * @param float $horas
     * @param bool  $esFin
     * @return string|null
     */
    private function fechaEnHora(array $sesiones, float $horas, bool $esFin): ?string
    {
        foreach ($sesiones as $sesion) {
            if ($esFin ? $sesion['acumulado'] >= $horas - 0.0001 : $sesion['acumulado'] > $horas + 0.0001) {
                return $sesion['fecha'];
            }
        }

        return $sesiones ? end($sesiones)['fecha'] : null;
    }

    /**
     * Formato legible de horas decimales (ej. 1,3 → "1 h 18 min").
     */
    private function formatoHoras(float $horas): string
    {
        $h = (int) floor($horas);
        $m = (int) round(($horas - $h) * 60);

        if ($m === 60) {
            $h++;
            $m = 0;
        }

        return trim(($h > 0 ? "{$h} h " : '') . ($m > 0 || $h === 0 ? "{$m} min" : ''));
    }
}
