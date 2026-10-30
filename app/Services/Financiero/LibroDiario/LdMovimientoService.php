<?php

namespace App\Services\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdImpuesto;
use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Models\Financiero\LibroDiario\LdTercero;
use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use Illuminate\Support\Facades\DB;

/**
 * Servicio LdMovimientoService
 *
 * Registra y actualiza los movimientos manuales del libro diario con sus
 * impuestos y retenciones, y calcula sus totales:
 *
 *   total_impuestos   = Σ valor de los impuestos que suman
 *   total_retenciones = Σ valor de los que restan
 *   valor_total       = subtotal + total_impuestos - total_retenciones
 *
 * El tercero se resuelve en la misma transacción: el seleccionado (`tercero_id`) o
 * el de esa identificación, que se crea si no existe. El movimiento guarda una copia
 * del tercero (tipo, identificación y nombre) tal como estaba al registrarlo.
 *
 * El valor de cada impuesto lo envía el cliente (calculado sobre el subtotal y
 * ajustable a mano para coincidir con la factura); el servidor recalcula los totales.
 */
class LdMovimientoService
{
    /** Tolerancia en pesos al comparar el total enviado con el calculado. */
    public const TOLERANCIA = 1.0;

    /**
     * Totales del movimiento a partir del subtotal y los impuestos aplicados.
     *
     * @param  array<int, array{impuesto_id: int, valor: float|string}>  $impuestos
     * @return array{total_impuestos: float, total_retenciones: float, valor_total: float}
     */
    public function calcularTotales(float $subtotal, array $impuestos): array
    {
        $efectos = LdImpuesto::withTrashed()
            ->whereIn('id', array_column($impuestos, 'impuesto_id'))
            ->pluck('efecto', 'id');

        $suma = 0.0;
        $resta = 0.0;
        foreach ($impuestos as $impuesto) {
            if ((int) $efectos[$impuesto['impuesto_id']] === LdImpuesto::EFECTO_RESTA) {
                $resta += (float) $impuesto['valor'];
            } else {
                $suma += (float) $impuesto['valor'];
            }
        }

        return [
            'total_impuestos' => round($suma, 2),
            'total_retenciones' => round($resta, 2),
            'valor_total' => round($subtotal + $suma - $resta, 2),
        ];
    }

    /**
     * Registra el movimiento en el turno abierto de quien lo crea, con fecha de hoy
     * salvo que los datos traigan otra (carga masiva de varios días).
     *
     * @param  array<string, mixed>  $datos  Datos validados (incluye impuestos[] y, opcional, fecha)
     */
    public function crear(LdTurnoCaja $turno, array $datos): LdMovimiento
    {
        return DB::transaction(function () use ($turno, $datos) {
            $movimiento = LdMovimiento::create(array_merge(
                $this->atributos($datos),
                [
                    'turno_caja_id' => $turno->id,
                    'sede_id' => $turno->sede_id,
                    'usuario_id' => $turno->cajero_id,
                    'fecha' => $datos['fecha'] ?? today(),
                    'status' => LdMovimiento::STATUS_REGISTRADO,
                ]
            ));

            $this->guardarImpuestos($movimiento, $datos['impuestos'] ?? []);

            return $movimiento;
        });
    }

    /**
     * Actualiza el movimiento y reemplaza sus impuestos.
     *
     * @param  array<string, mixed>  $datos  Datos validados (incluye impuestos[])
     */
    public function actualizar(LdMovimiento $movimiento, array $datos): LdMovimiento
    {
        return DB::transaction(function () use ($movimiento, $datos) {
            $movimiento->update($this->atributos($datos));

            $movimiento->impuestos()->delete();
            $this->guardarImpuestos($movimiento, $datos['impuestos'] ?? []);

            return $movimiento;
        });
    }

    /**
     * Atributos del movimiento con la clase del tipo y los totales calculados.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function atributos(array $datos): array
    {
        $subtotal = (float) $datos['subtotal'];

        return array_merge(
            collect($datos)->only([
                'tipo_movimiento_id', 'documento_tipo', 'documento_numero', 'medio_pago',
                'banco_id', 'observaciones',
            ])->all(),
            $this->datosTercero($this->resolverTercero($datos)),
            ['clase' => LdTipoMovimiento::findOrFail($datos['tipo_movimiento_id'])->clase, 'subtotal' => $subtotal],
            $this->calcularTotales($subtotal, $datos['impuestos'] ?? [])
        );
    }

    /**
     * Tercero del movimiento: el seleccionado o el de esa identificación, creándolo si no existe.
     *
     * @param  array<string, mixed>  $datos
     */
    public function resolverTercero(array $datos): LdTercero
    {
        if (! empty($datos['tercero_id'])) {
            return LdTercero::findOrFail($datos['tercero_id']);
        }

        return LdTercero::firstOrCreate(
            [
                'tipo_identificacion' => $datos['tercero_tipo_identificacion'],
                'identificacion' => trim($datos['tercero_identificacion']),
            ],
            [
                'nombre' => trim($datos['tercero_nombre']),
                'telefono' => $datos['tercero_telefono'] ?? null,
                'email' => $datos['tercero_email'] ?? null,
                'direccion' => $datos['tercero_direccion'] ?? null,
                'status' => 1,
            ]
        );
    }

    /**
     * Enlace al tercero y copia de sus datos en el movimiento.
     *
     * @return array<string, mixed>
     */
    private function datosTercero(LdTercero $tercero): array
    {
        return [
            'tercero_id' => $tercero->id,
            'tercero_tipo_identificacion' => $tercero->tipo_identificacion,
            'tercero_identificacion' => $tercero->identificacion,
            'tercero_nombre' => $tercero->nombre,
        ];
    }

    /**
     * Guarda los impuestos aplicados con el nombre y efecto vigentes del catálogo.
     *
     * @param  array<int, array{impuesto_id: int, porcentaje: float|string, valor: float|string}>  $impuestos
     */
    private function guardarImpuestos(LdMovimiento $movimiento, array $impuestos): void
    {
        $catalogo = LdImpuesto::whereIn('id', array_column($impuestos, 'impuesto_id'))->get()->keyBy('id');

        foreach ($impuestos as $impuesto) {
            $item = $catalogo[$impuesto['impuesto_id']];

            $movimiento->impuestos()->create([
                'impuesto_id' => $item->id,
                'nombre' => $item->nombre,
                'efecto' => $item->efecto,
                'porcentaje' => $impuesto['porcentaje'],
                'valor' => $impuesto['valor'],
            ]);
        }
    }
}
