<?php

namespace App\Services\Financiero;

use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\Financiero\ReciboPago\ReciboPagoMedioPago;
use App\Models\Financiero\ReciboPago\ReciboPagoSobrecargo;
use Illuminate\Support\Collection;

/**
 * Servicio AjusteService
 *
 * Extiende DescuentoService con la lógica de sobrecargos.
 * Resuelve qué sobrecargos aplican a un medio de pago, los pre-calcula
 * y persiste el detalle en recibo_pago_sobrecargo.
 *
 * @package App\Services\Financiero
 */
class AjusteService extends DescuentoService
{
    /**
     * Retorna los sobrecargos activos aplicables a un medio de pago y marca de tarjeta.
     * Cada ítem del resultado incluye el porcentaje y el monto calculado sobre valor_base.
     *
     * @param string $medioPago Medio de pago (tarjeta_credito, tarjeta_debito, etc.)
     * @param string|null $marcaTarjeta Marca de tarjeta (visa, mastercard, etc.)
     * @param float $valorBase Monto del medio de pago para calcular el sobrecargo
     * @return Collection Colección de arrays con keys: sobrecargo, valor_sobrecargo, valor_final
     */
    public function resolverSobrecargosPorMedioPago(
        string $medioPago,
        ?string $marcaTarjeta,
        float $valorBase
    ): Collection {
        $sobrecargos = Descuento::porMedioPago($medioPago, $marcaTarjeta)->get();

        return $sobrecargos->map(function (Descuento $s) use ($valorBase) {
            $valorSobrecargo = $s->calcularSobrecargo($valorBase);
            return [
                'sobrecargo'       => $s,
                'valor_base'       => $valorBase,
                'valor_sobrecargo' => $valorSobrecargo,
                'valor_final'      => $valorBase + $valorSobrecargo,
            ];
        });
    }

    /**
     * Persiste un sobrecargo en la tabla recibo_pago_sobrecargo y devuelve el registro creado.
     * El método no actualiza sobrecargo_total en el encabezado del recibo;
     * eso se delega a calcularTotales() en el controlador.
     *
     * @param Descuento $sobrecargo Configuración del sobrecargo (tipo_movimiento='sobrecargo')
     * @param ReciboPago $recibo Recibo al que se aplica
     * @param ReciboPagoMedioPago $medioPago Medio de pago que disparó el sobrecargo
     * @return ReciboPagoSobrecargo
     */
    public function aplicarSobrecargo(
        Descuento $sobrecargo,
        ReciboPago $recibo,
        ReciboPagoMedioPago $medioPago
    ): ReciboPagoSobrecargo {
        $valorBase       = (float) $medioPago->valor;
        $valorSobrecargo = $sobrecargo->calcularSobrecargo($valorBase);

        return ReciboPagoSobrecargo::create([
            'recibo_pago_id'             => $recibo->id,
            'descuento_id'               => $sobrecargo->id,
            'recibo_pago_medio_pago_id'  => $medioPago->id,
            'valor_base'                 => $valorBase,
            'valor_sobrecargo'           => $valorSobrecargo,
            'valor_final'                => $valorBase + $valorSobrecargo,
        ]);
    }

    /**
     * Pre-calcula los sobrecargos para una lista de medios de pago sin persistir nada.
     * Usado por el endpoint de pre-cálculo del cajero.
     *
     * @param array<int, array{medio_pago: string, tipo_tarjeta: string|null, valor: float}> $mediosPago
     * @return array{
     *   sobrecargos: array,
     *   total_sobrecargo: float
     * }
     */
    public function precalcular(array $mediosPago): array
    {
        $lineas         = [];
        $totalSobrecargo = 0.0;

        foreach ($mediosPago as $medio) {
            $medioPago   = $medio['medio_pago'];
            $marcaTarjeta = $medio['tipo_tarjeta'] ?? null;
            $valorBase   = (float) ($medio['valor'] ?? 0);

            $sobrecargosAplicables = $this->resolverSobrecargosPorMedioPago($medioPago, $marcaTarjeta, $valorBase);

            foreach ($sobrecargosAplicables as $item) {
                $totalSobrecargo += $item['valor_sobrecargo'];
                $lineas[] = [
                    'descuento_id'     => $item['sobrecargo']->id,
                    'nombre'           => $item['sobrecargo']->nombre,
                    'porcentaje'       => (float) $item['sobrecargo']->valor,
                    'medio_pago'       => $medioPago,
                    'tipo_tarjeta'     => $marcaTarjeta,
                    'valor_base'       => $item['valor_base'],
                    'valor_sobrecargo' => $item['valor_sobrecargo'],
                    'valor_final'      => $item['valor_final'],
                ];
            }
        }

        return [
            'sobrecargos'     => $lineas,
            'total_sobrecargo' => $totalSobrecargo,
        ];
    }
}
