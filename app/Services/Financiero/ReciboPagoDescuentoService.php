<?php

namespace App\Services\Financiero;

use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\ConceptoPago\ConceptoPago;
use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\Descuento\DescuentoAplicado;
use App\Models\Financiero\ReciboPago\ReciboPago;

/**
 * ReciboPagoDescuentoService
 *
 * Deja registrado, al pagar una cuota (matrícula o mensualidad), a cuánto equivale cada
 * descuento aplicado. Por cada descuento de la línea escribe:
 *  - la línea de concepto "Descuento" que se ve en el recibo,
 *  - el detalle en recibo_pago_descuento (cuota, rubro, valor original, descuento y valor final),
 *  - el historial en descuento_aplicado.
 */
class ReciboPagoDescuentoService
{
    /**
     * Registra los descuentos de una línea del plan de distribución.
     *
     * @param  Cartera  $cartera  cuota ya actualizada por el pago
     * @param  array  $linea  línea del plan: ['descuento' => float, 'detalle' => [['descuento' => Descuento, 'valor' => float]]]
     * @param  float  $valorBase  valor sobre el que se calcularon los descuentos (saldo de la matrícula o valor bruto de la cuota)
     * @param  int  $statusSnapshot  estado de la cartera tras el pago
     * @param  float  $saldoSnapshot  saldo de la cartera tras el pago
     */
    public function registrar(ReciboPago $recibo, Cartera $cartera, array $linea, float $valorBase, int $statusSnapshot, float $saldoSnapshot): void
    {
        if (($linea['descuento'] ?? 0) <= 0 || empty($linea['detalle'])) {
            return;
        }

        $rubro = $cartera->numero_cuota === 0 ? 'matricula' : 'cuota';
        $lp = $cartera->matricula?->lpPrecioProducto;
        $conceptoDesc = ConceptoPago::porNombre(ConceptoPago::DESCUENTO);
        $restante = $valorBase;

        foreach ($linea['detalle'] as $item) {
            /** @var Descuento $desc */
            $desc = $item['descuento'];
            $valor = (float) $item['valor'];

            if ($conceptoDesc) {
                $recibo->conceptosPago()->attach($conceptoDesc->id, [
                    'tipo' => $conceptoDesc->tipo,
                    'valor' => $valor,
                    'cantidad' => 1,
                    'unitario' => $valor,
                    'subtotal' => $valor,
                    'id_relacional' => $cartera->id,
                    'observaciones' => $desc->tipo === Descuento::TIPO_PORCENTUAL
                        ? "{$desc->nombre} {$desc->valor}%"
                        : "{$desc->nombre} $".number_format($valor, 0, ',', '.'),
                    'status_cartera' => $statusSnapshot,
                    'saldo_cartera' => $saldoSnapshot,
                ]);
            }

            $recibo->descuentos()->attach($desc->id, [
                'cartera_id' => $cartera->id,
                'rubro' => $rubro,
                'valor_descuento' => $valor,
                'valor_original' => $restante,
                'valor_final' => $restante - $valor,
            ]);

            DescuentoAplicado::create([
                'descuento_id' => $desc->id,
                'tipo_movimiento' => Descuento::MOVIMIENTO_DESCUENTO,
                'concepto_tipo' => $rubro,
                'concepto_id' => $cartera->id,
                'valor_original' => $restante,
                'valor_descuento' => $valor,
                'valor_final' => $restante - $valor,
                'producto_id' => $lp?->producto_id,
                'lista_precio_id' => $lp?->lista_precio_id,
                'sede_id' => $recibo->sede_id,
                'observaciones' => "Recibo {$recibo->id} — cuota {$cartera->numero_cuota}",
            ]);

            $restante -= $valor;
        }
    }
}
