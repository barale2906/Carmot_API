<?php

namespace App\Services\Inventarios;

use App\Models\Inventarios\InvDocumentoMovimiento;
use App\Models\Inventarios\InvMovimiento;
use App\Models\Inventarios\InvOrdenCompra;
use App\Models\Inventarios\InvOrdenCompraItem;
use Illuminate\Support\Facades\DB;

/**
 * InvRecepcionService — registra la recepción total o parcial de una orden de compra.
 *
 * Para cada ítem recibido:
 *  1. Incrementa cantidad_recibida en el ítem.
 *  2. Genera un movimiento de entrada de stock.
 *  3. Llama a InvNecesidadService::verificarPendientesCubiertos() para notificar
 *     a los cajeros si hay necesidades de compra cubiertas por este ingreso.
 *
 * Actualiza el status de la orden:
 *  - Si todos los ítems están completos → 'recibida'
 *  - Si al menos uno está incompleto   → 'recibida_parcial'
 */
class InvRecepcionService
{
    /**
     * Registra la recepción de ítems de una orden de compra.
     *
     * @param InvOrdenCompra $orden
     * @param array $itemsRecibidos  [{orden_item_id: int, cantidad_recibida: int}]
     * @param int   $responsableId   ID del usuario que recibe
     * @return InvDocumentoMovimiento  Documento de entrada generado
     * @throws \RuntimeException Si la orden no está en estado recibible o alguna cantidad es inválida.
     */
    public static function recibirItems(
        InvOrdenCompra $orden,
        array $itemsRecibidos,
        int $responsableId
    ): InvDocumentoMovimiento {
        if (!$orden->esRecibible()) {
            throw new \RuntimeException(
                "La orden no puede recibirse en su estado actual ({$orden->status})."
            );
        }

        return DB::transaction(function () use ($orden, $itemsRecibidos, $responsableId) {
            $documento = InvDocumentoMovimiento::create([
                'numero_documento' => InvDocumentoMovimiento::generarNumero('entrada'),
                'tipo_documento'   => InvDocumentoMovimiento::TIPO_ENTRADA,
                'almacen_id'       => $orden->almacen_id,
                'proveedor_id'     => $orden->proveedor_id,
                'motivo'           => "Recepción OC #{$orden->id}",
                'status'           => InvDocumentoMovimiento::STATUS_CONFIRMADO,
                'user_id'          => $responsableId,
            ]);

            $itemsIndexados = collect($itemsRecibidos)->keyBy('orden_item_id');

            $orden->loadMissing('items');

            foreach ($orden->items as $item) {
                $recepcion = $itemsIndexados->get($item->id);

                if (!$recepcion || (int) $recepcion['cantidad_recibida'] <= 0) {
                    continue;
                }

                $cantidadRecibir = min(
                    (int) $recepcion['cantidad_recibida'],
                    $item->cantidadPendiente()
                );

                if ($cantidadRecibir <= 0) {
                    continue;
                }

                // Actualizar precio de costo si se proporciona
                if (!empty($recepcion['precio_costo_unitario'])) {
                    $item->update([
                        'precio_costo_unitario' => $recepcion['precio_costo_unitario'],
                        'subtotal'              => $recepcion['precio_costo_unitario'] * $item->cantidad_solicitada,
                    ]);
                }

                // Registrar movimiento
                InvMovimiento::create([
                    'documento_id'    => $documento->id,
                    'almacen_id'      => $orden->almacen_id,
                    'producto_id'     => $item->producto_id,
                    'tipo_movimiento' => 'entrada',
                    'cantidad'        => $cantidadRecibir,
                    'referencia_type' => InvOrdenCompraItem::class,
                    'referencia_id'   => $item->id,
                    'user_id'         => $responsableId,
                ]);

                // Incrementar stock
                InvStockService::incrementar($orden->almacen_id, $item->producto_id, $cantidadRecibir);

                // Actualizar cantidad recibida en el ítem
                $item->increment('cantidad_recibida', $cantidadRecibir);

                // Notificar si cubre necesidades pendientes
                InvNecesidadService::verificarPendientesCubiertos(
                    $item->producto_id,
                    $orden->almacen_id
                );
            }

            // Recalcular totales de la orden
            $orden->refresh()->recalcularTotales();

            // Actualizar status de la orden
            $todoRecibido = $orden->items->every(fn ($i) => $i->fresh()->estaCompleto());
            $orden->update([
                'status' => $todoRecibido
                    ? InvOrdenCompra::STATUS_RECIBIDA
                    : InvOrdenCompra::STATUS_RECIBIDA_PARCIAL,
            ]);

            return $documento;
        });
    }
}
