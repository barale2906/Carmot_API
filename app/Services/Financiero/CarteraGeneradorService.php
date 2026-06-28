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
 *  B) Pago a cuotas:
 *     → Cuota 0 (matrícula): valor = lp.matricula,    fecha = fecha_matricula
 *     → Cuota 1 (primera):   valor = lp.valor_cuota,  fecha = fecha_matricula
 *     → Cuotas 2..N:         valor = lp.valor_cuota,  fecha = fecha_matricula + (n-1) meses
 */
class CarteraGeneradorService
{
    /**
     * Genera los cargos de cartera para una matrícula activa.
     * Debe llamarse dentro de la transacción del hook created de Matricula.
     *
     * @param  Matricula $matricula  debe tener lpPrecioProducto cargado
     */
    public function generarParaMatricula(Matricula $matricula): void
    {
        $lp           = $matricula->lpPrecioProducto;
        $numeroCuotas = (int) ($lp->numero_cuotas ?? 0);
        $fechaBase    = Carbon::parse($matricula->fecha_matricula);
        $sedeId       = $matricula->sede_id;

        DB::transaction(function () use ($matricula, $lp, $numeroCuotas, $fechaBase, $sedeId) {
            if ($numeroCuotas === 0) {
                // ── Contado ────────────────────────────────────────────────
                $this->crearCuota($matricula, $sedeId, 0, (float) $lp->precio_contado, $fechaBase);
            } else {
                // ── Cuotas ────────────────────────────────────────────────
                // Cuota 0: cargo de matrícula, en el día de la matrícula
                $this->crearCuota($matricula, $sedeId, 0, (float) $lp->matricula, $fechaBase);

                // Primera cuota también en el día de la matrícula
                $this->crearCuota($matricula, $sedeId, 1, (float) $lp->valor_cuota, $fechaBase);

                // Cuotas 2..N: una por mes a partir de la primera
                for ($n = 2; $n <= $numeroCuotas; $n++) {
                    $fecha = (clone $fechaBase)->addMonths($n - 1);
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
