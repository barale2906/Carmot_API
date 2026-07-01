<?php

namespace App\Services\Financiero;

use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CarteraGeneradorService
 *
 * Genera los registros de cartera (cuentas por cobrar) al completar una matrícula.
 *
 * Dos escenarios según el plan de pago del LpPrecioProducto vinculado:
 *
 *  A) Pago de contado (numero_cuotas = 0 o null):
 *     → 1 cartera: cuota 0, valor = precio_contado, fecha_vencimiento = fecha_matricula
 *
 *  B) Pago a cuotas (base = ciclo.fecha_inicio; si no hay ciclo, base = fecha_matricula):
 *     → Cuota 0 (matrícula):  valor = lp.matricula,   fecha = fecha_matricula
 *     → Cuota 1 (primera mensualidad):
 *        - Ciclo no ha iniciado: fecha = base (ciclo.fecha_inicio)
 *        - Ciclo ya inició o sin ciclo: fecha = fecha_matricula
 *     → Cuotas 2..N:          valor = lp.valor_cuota,  fecha = base + (n-1) meses
 */
class CarteraGeneradorService
{
    /**
     * Genera los cargos de cartera para una matrícula activa.
     * Debe llamarse dentro de la transacción del hook created de Matricula.
     *
     * @param  Matricula $matricula  debe tener lpPrecioProducto y ciclo cargados
     */
    public function generarParaMatricula(Matricula $matricula): void
    {
        $lp             = $matricula->lpPrecioProducto;
        $numeroCuotas   = (int) ($lp->numero_cuotas ?? 0);
        $fechaMatricula = Carbon::parse($matricula->fecha_matricula);
        $sedeId         = $matricula->sede_id;

        if (! $matricula->relationLoaded('ciclo')) {
            $matricula->load('ciclo');
        }

        // Base para el calendario de mensualidades: fecha de inicio del ciclo.
        // Si el ciclo no tiene fecha o no existe, se usa la fecha de matrícula.
        $fechaInicioCiclo = $matricula->ciclo?->fecha_inicio
            ? Carbon::parse($matricula->ciclo->fecha_inicio)
            : null;

        DB::transaction(function () use ($matricula, $lp, $numeroCuotas, $fechaMatricula, $fechaInicioCiclo, $sedeId) {
            if ($numeroCuotas === 0) {
                // ── Contado ────────────────────────────────────────────────
                $this->crearCuota($matricula, $sedeId, 0, (float) $lp->precio_contado, $fechaMatricula);
            } else {
                // ── Cuotas ────────────────────────────────────────────────
                // Cuota 0: cargo de matrícula, siempre en la fecha de matrícula
                $this->crearCuota($matricula, $sedeId, 0, (float) $lp->matricula, $fechaMatricula);

                // Base del calendario mensual: ciclo.fecha_inicio o fecha_matricula si no hay ciclo
                $base = $fechaInicioCiclo ?? $fechaMatricula;

                // Si el ciclo ya inició (o no hay ciclo), la primera cuota vence hoy (fecha matrícula).
                // Si el ciclo aún no ha comenzado, la primera cuota vence en la fecha de inicio del ciclo.
                $cicloYaInicio = $fechaInicioCiclo === null || $fechaInicioCiclo->lte($fechaMatricula);
                $fechaCuota1   = $cicloYaInicio ? $fechaMatricula : $base;

                $this->crearCuota($matricula, $sedeId, 1, (float) $lp->valor_cuota, $fechaCuota1);

                // Cuotas 2..N: una por mes siguiendo el calendario del ciclo
                for ($n = 2; $n <= $numeroCuotas; $n++) {
                    $fecha = (clone $base)->addMonths($n - 1);
                    $this->crearCuota($matricula, $sedeId, $n, (float) $lp->valor_cuota, $fecha);
                }
            }
        });
    }

    /**
     * Crea un único registro de cartera.
     */
    private function crearCuota(
        Matricula $matricula,
        ?int $sedeId,
        int $numeroCuota,
        float $valor,
        Carbon $fechaVencimiento
    ): Cartera {
        return Cartera::create([
            'matricula_id'     => $matricula->id,
            'sede_id'          => $sedeId,
            'estudiante_id'    => $matricula->estudiante_id,
            'numero_cuota'     => $numeroCuota,
            'valor'            => $valor,
            'saldo'            => $valor,
            'abono'            => 0,
            'descuento'        => 0,
            'fecha_vencimiento' => $fechaVencimiento->toDateString(),
            'status'           => Cartera::getStatusKey('Activa'),
        ]);
    }
}
