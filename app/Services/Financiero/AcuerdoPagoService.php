<?php

namespace App\Services\Financiero;

use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AcuerdoPagoService
 *
 * Procesa un acuerdo de pago para una matrícula con cuotas vencidas.
 *
 * Flujo:
 *  1. Marca las carteras Activas/Abonadas de la matrícula como "En Acuerdo".
 *  2. Genera nuevas carteras con las condiciones reestructuradas
 *     (fecha de inicio = hoy, valor inicial = monto_inicial, cuotas con nuevo valor).
 *  3. Retorna las nuevas carteras creadas.
 */
class AcuerdoPagoService
{
    /**
     * Registra un acuerdo de pago para la matrícula.
     *
     * @param  Matricula $matricula
     * @param  float     $montoInicial  monto que el estudiante paga hoy como enganche
     * @param  int       $numeroCuotas  cantidad de cuotas de la reestructuración
     * @param  float     $valorCuota    valor de cada cuota reestructurada
     * @param  string    $observaciones notas del acuerdo
     * @return array{carteras_acuerdo: \Illuminate\Support\Collection, total_reestructurado: float}
     */
    public function procesarAcuerdo(
        Matricula $matricula,
        float $montoInicial,
        int $numeroCuotas,
        float $valorCuota,
        string $observaciones = ''
    ): array {
        return DB::transaction(function () use ($matricula, $montoInicial, $numeroCuotas, $valorCuota, $observaciones) {
            $fechaBase  = Carbon::today();
            $sedeId     = Cartera::where('matricula_id', $matricula->id)->value('sede_id');

            // Paso 1: Marcar carteras pendientes como "En Acuerdo"
            $matricula->carteras()
                ->pendientes()
                ->each(fn (Cartera $c) => $c->marcarEnAcuerdo());

            // Paso 2: Crear cartera de cuota inicial del acuerdo (hoy)
            $nuevasCarteras = collect();

            if ($montoInicial > 0) {
                $nuevasCarteras->push(Cartera::create([
                    'matricula_id'     => $matricula->id,
                    'sede_id'          => $sedeId,
                    'estudiante_id'    => $matricula->estudiante_id,
                    'numero_cuota'     => 0,
                    'valor'            => $montoInicial,
                    'saldo'            => $montoInicial,
                    'abono'            => 0,
                    'descuento'        => 0,
                    'fecha_vencimiento' => $fechaBase->toDateString(),
                    'status'           => Cartera::getStatusKey('Activa'),
                    'observaciones'    => "Inicial acuerdo de pago. {$observaciones}",
                ]));
            }

            // Paso 3: Generar cuotas reestructuradas
            for ($n = 1; $n <= $numeroCuotas; $n++) {
                $fecha = (clone $fechaBase)->addMonths($n);
                $nuevasCarteras->push(Cartera::create([
                    'matricula_id'     => $matricula->id,
                    'sede_id'          => $sedeId,
                    'estudiante_id'    => $matricula->estudiante_id,
                    'numero_cuota'     => $n,
                    'valor'            => $valorCuota,
                    'saldo'            => $valorCuota,
                    'abono'            => 0,
                    'descuento'        => 0,
                    'fecha_vencimiento' => $fecha->toDateString(),
                    'status'           => Cartera::getStatusKey('Activa'),
                    'observaciones'    => "Cuota {$n} acuerdo de pago. {$observaciones}",
                ]));
            }

            $totalReestructurado = $nuevasCarteras->sum('valor');

            return [
                'carteras_acuerdo'   => $nuevasCarteras,
                'total_reestructurado' => $totalReestructurado,
            ];
        });
    }
}
