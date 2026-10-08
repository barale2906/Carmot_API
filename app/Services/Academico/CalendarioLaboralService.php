<?php

namespace App\Services\Academico;

use App\Models\Academico\Grupo;
use App\Models\Configuracion\DiaNoLaborable;
use App\Models\Configuracion\Horario;
use Carbon\Carbon;

/**
 * CalendarioLaboralService
 *
 * Recorre el calendario día por día cruzando los horarios de un grupo con
 * los días no laborables de su sede. Es la única fuente de verdad para
 * saber cuándo se dicta realmente una sesión:
 *  - calcularRangoGrupo: primera y última sesión necesarias para cubrir unas horas.
 *  - recorrerSesiones: todas las sesiones (laborables o no) de un grupo en un rango.
 *  - siguienteDiaLaborable: primer día laborable a partir de una fecha.
 *
 * Los cálculos repetitivos (búsqueda del factor de ajuste) pueden precargar
 * los días no laborables con cargarNoLaborables() y pasarlos como parámetro,
 * para no consultar la base en cada iteración.
 */
class CalendarioLaboralService
{
    /** Tope del recorrido (3 años): evita un ciclo infinito si todos los días de clase están bloqueados. */
    public const TOPE_DIAS = 1095;

    /**
     * Días no laborables activos que aplican a la sede (los de la sede + los generales).
     *
     * @param Carbon   $desde
     * @param Carbon   $hasta
     * @param int|null $sedeId
     * @return array<string, string> ['Y-m-d' => nombre]
     */
    public function fechasNoLaborables(Carbon $desde, Carbon $hasta, ?int $sedeId): array
    {
        return DiaNoLaborable::active()
            ->aplicaASede($sedeId)
            ->enRango($desde->toDateString(), $hasta->toDateString())
            ->orderBy('fecha')
            ->get(['fecha', 'nombre'])
            ->mapWithKeys(fn ($dia) => [$dia->fecha->toDateString() => $dia->nombre])
            ->all();
    }

    /**
     * Precarga los días no laborables de una sede para una ventana amplia
     * (2 × TOPE_DIAS desde $desde), suficiente para planear un ciclo completo.
     *
     * @param Carbon   $desde
     * @param int|null $sedeId
     * @return array<string, string> ['Y-m-d' => nombre]
     */
    public function cargarNoLaborables(Carbon $desde, ?int $sedeId): array
    {
        return $this->fechasNoLaborables($desde, $desde->copy()->addDays(2 * self::TOPE_DIAS), $sedeId);
    }

    /**
     * Calcula el rango real de un grupo: avanza desde $desde sumando las horas
     * de cada sesión laborable hasta cubrir $horasObjetivo (por defecto, la
     * duración del módulo). La fecha fin es el día de la última sesión.
     *
     * Devuelve null si el grupo no tiene horarios, si no hay horas por cubrir
     * o si no se alcanza a cubrirlas dentro de TOPE_DIAS.
     *
     * @param Grupo      $grupo
     * @param Carbon     $desde
     * @param float|null $horasObjetivo
     * @param array<string, string>|null $noLaborables Precargados con cargarNoLaborables(); null = consultar
     * @return array{fecha_inicio: Carbon, fecha_fin: Carbon, sesiones: int, horas: float, dias_omitidos: array<int, array{fecha: string, nombre: string}>}|null
     */
    public function calcularRangoGrupo(Grupo $grupo, Carbon $desde, ?float $horasObjetivo = null, ?array $noLaborables = null): ?array
    {
        $horasObjetivo ??= (float) ($grupo->modulo->duracion ?? 0);

        if ($horasObjetivo <= 0) {
            return null;
        }

        $fechaInicio  = null;
        $sesiones     = 0;
        $acumuladas   = 0.0;
        $diasOmitidos = [];

        $hasta = $desde->copy()->addDays(self::TOPE_DIAS);

        foreach ($this->recorrerSesiones($grupo, $desde, $hasta, $noLaborables) as $sesion) {
            $fecha = $sesion['fecha']->toDateString();

            if ($sesion['no_laborable'] !== null) {
                $diasOmitidos[$fecha] = ['fecha' => $fecha, 'nombre' => $sesion['no_laborable']];
                continue;
            }

            $fechaInicio ??= $sesion['fecha']->copy();
            $sesiones++;
            $acumuladas += $sesion['horario']->duracion_horas;

            if ($acumuladas >= $horasObjetivo) {
                return [
                    'fecha_inicio'  => $fechaInicio,
                    'fecha_fin'     => $sesion['fecha']->copy(),
                    'sesiones'      => $sesiones,
                    'horas'         => $acumuladas,
                    'dias_omitidos' => array_values($diasOmitidos),
                ];
            }
        }

        return null;
    }

    /**
     * Recorre las sesiones de un grupo entre dos fechas (inclusive), una por
     * cada horario cuyo día de la semana coincide. Las sesiones que caen en
     * un día no laborable se entregan con 'no_laborable' = nombre del día,
     * para que el consumidor decida si las omite o las reporta.
     *
     * @param Grupo  $grupo
     * @param Carbon $desde
     * @param Carbon $hasta
     * @param array<string, string>|null $noLaborables Precargados; null = consultar
     * @return \Generator<int, array{fecha: Carbon, horario: Horario, no_laborable: string|null}>
     */
    public function recorrerSesiones(Grupo $grupo, Carbon $desde, Carbon $hasta, ?array $noLaborables = null): \Generator
    {
        $horariosPorDia = $grupo->horarios->groupBy('dia');

        if ($horariosPorDia->isEmpty()) {
            return;
        }

        $noLaborables ??= $this->fechasNoLaborables($desde, $hasta, $grupo->sede_id);
        $fecha          = $desde->copy()->startOfDay();

        while ($fecha->lte($hasta)) {
            $horariosDelDia = $horariosPorDia->get(Horario::DIAS_SEMANA[$fecha->dayOfWeek]);

            if ($horariosDelDia) {
                $motivo = $noLaborables[$fecha->toDateString()] ?? null;

                foreach ($horariosDelDia->sortBy('hora') as $horario) {
                    yield ['fecha' => $fecha->copy(), 'horario' => $horario, 'no_laborable' => $motivo];
                }
            }

            $fecha->addDay();
        }
    }

    /**
     * Primer día laborable para la sede a partir de $fecha (inclusive).
     *
     * @param Carbon   $fecha
     * @param int|null $sedeId
     * @return Carbon
     */
    public function siguienteDiaLaborable(Carbon $fecha, ?int $sedeId): Carbon
    {
        $noLaborables = $this->fechasNoLaborables($fecha, $fecha->copy()->addDays(self::TOPE_DIAS), $sedeId);
        $resultado    = $fecha->copy();

        while (isset($noLaborables[$resultado->toDateString()])) {
            $resultado->addDay();
        }

        return $resultado;
    }
}
