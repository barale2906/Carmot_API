<?php

namespace App\Services\Inventarios;

use App\Models\Inventarios\InvDocumentoMovimiento;
use App\Models\Inventarios\InvEntregaSimple;
use App\Models\Inventarios\InvMovimiento;
use App\Models\Inventarios\InvPedidoItem;

/**
 * InvEntregaSimpleService — despacha la entrega de un ítem de tipo simple.
 *
 * Debe llamarse dentro de DB::transaction().
 * Si hay stock: registra el movimiento de salida y marca la entrega como entregada.
 * Si no hay stock: la entrega queda pendiente y se genera una necesidad de compra.
 */
class InvEntregaSimpleService
{
    /**
     * Inicia o completa la entrega de un ítem simple.
     *
     * @param InvPedidoItem $item       Ítem del pedido (tipo=simple)
     * @param int           $cajeroId   ID del usuario cajero que ejecuta la entrega
     * @return InvEntregaSimple
     */
    public static function entregar(InvPedidoItem $item, int $cajeroId): InvEntregaSimple
    {
        $pedido    = $item->pedido;
        $almacenId = $pedido->almacen_id;
        $estudianteId = $pedido->estudiante_id;

        $entrega = InvEntregaSimple::firstOrCreate(
            ['pedido_item_id' => $item->id],
            [
                'producto_id'        => $item->producto_id,
                'cantidad_entregada' => 0,
                'status'             => InvEntregaSimple::STATUS_PENDIENTE,
            ]
        );

        if ($entrega->status === InvEntregaSimple::STATUS_ENTREGADO) {
            return $entrega;
        }

        // Verificar stock disponible
        $stockDisponible = \App\Models\Inventarios\InvStock::where('almacen_id', $almacenId)
            ->where('producto_id', $item->producto_id)
            ->value('cantidad_disponible') ?? 0;

        if ($stockDisponible >= $item->cantidad) {
            // Crear documento de salida
            $documento = InvDocumentoMovimiento::create([
                'numero_documento' => InvDocumentoMovimiento::generarNumero('salida'),
                'tipo_documento'   => InvDocumentoMovimiento::TIPO_SALIDA,
                'almacen_id'       => $almacenId,
                'pedido_id'        => $pedido->id,
                'motivo'           => "Entrega pedido #{$pedido->id}",
                'status'           => InvDocumentoMovimiento::STATUS_CONFIRMADO,
                'user_id'          => $cajeroId,
            ]);

            InvMovimiento::create([
                'documento_id'     => $documento->id,
                'almacen_id'       => $almacenId,
                'producto_id'      => $item->producto_id,
                'tipo_movimiento'  => 'salida',
                'cantidad'         => $item->cantidad,
                'referencia_type'  => InvEntregaSimple::class,
                'referencia_id'    => $entrega->id,
                'user_id'          => $cajeroId,
            ]);

            InvStockService::decrementar($almacenId, $item->producto_id, $item->cantidad);

            $entrega->update([
                'cantidad_entregada' => $item->cantidad,
                'status'             => InvEntregaSimple::STATUS_ENTREGADO,
                'fecha_entrega'      => now(),
                'user_id'            => $cajeroId,
            ]);
        } else {
            // Sin stock: generar necesidad de compra
            InvNecesidadService::generarSiNoExiste(
                $item->producto_id,
                $item->cantidad,
                $almacenId,
                $estudianteId,
                InvEntregaSimple::class,
                $entrega->id
            );
        }

        return $entrega->fresh();
    }
}
