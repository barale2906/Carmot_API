<?php

namespace App\Services\Inventarios;

use App\Models\Inventarios\InvEntregaKit;
use App\Models\Inventarios\InvEntregaSimple;
use App\Models\Inventarios\InvPedido;

/**
 * InvDespachoService — punto de entrada único al despacho cuando el saldo llega a 0.
 *
 * Itera los ítems del pedido y delega a InvEntregaSimpleService o InvEntregaKitService
 * según el tipo de producto. Actualiza el status del pedido al finalizar.
 *
 * El despacho nunca falla por falta de stock: entrega lo que hay y deja el resto
 * pendiente con su necesidad de compra.
 *
 * Debe llamarse dentro de la misma DB::transaction() del abono que cerró el saldo.
 */
class InvDespachoService
{
    /**
     * Crea el registro de entrega de todos los ítems del pedido, sin descargar stock.
     *
     * Debe llamarse en cuanto el pedido queda pagado, ANTES y con independencia del
     * despacho: los ítems excluidos de la entrega inmediata (`entregar: false`) o un
     * pedido con `entrega_inmediata: false` también necesitan su registro, porque la
     * pantalla de Entregas Pendientes trabaja con el id de ese registro. Sin esto el
     * ítem quedaría sin forma de entregarse y el pedido nunca llegaría a 'entregado'.
     *
     * @param InvPedido $pedido
     * @param int       $cajeroId
     * @return void
     */
    public static function prepararEntregas(InvPedido $pedido, int $cajeroId): void
    {
        $pedido->loadMissing('items.producto');

        foreach ($pedido->items as $item) {
            if ($item->producto->tipo === 'kit') {
                InvEntregaKitService::prepararPendiente($item, $cajeroId);
            } else {
                InvEntregaSimpleService::prepararPendiente($item);
            }
        }
    }

    /**
     * Despacha los ítems de un pedido pagado.
     *
     * @param InvPedido        $pedido          Pedido con status='pagado'
     * @param int              $cajeroId        Usuario que ejecuta el despacho
     * @param array            $variantesKit    Variantes para ítems de kit: [{pedido_item_id, componentes: [{kit_componente_id, producto_entregado_id}]}]
     * @param array<int,int>|null $itemsAEntregar  IDs de inv_pedido_items a despachar ahora; null despacha todos
     * @return void
     */
    public static function despacharPedido(
        InvPedido $pedido,
        int $cajeroId,
        array $variantesKit = [],
        ?array $itemsAEntregar = null
    ): void {
        $pedido->update(['status' => InvPedido::STATUS_ENTREGANDO]);

        // Indexar variantes por pedido_item_id
        $variantesIndexadas = collect($variantesKit)->keyBy('pedido_item_id');

        $pedido->loadMissing('items.producto');

        foreach ($pedido->items as $item) {
            if ($itemsAEntregar !== null && ! in_array($item->id, $itemsAEntregar, true)) {
                continue;
            }

            if ($item->producto->tipo === 'kit') {
                $variantes   = $variantesIndexadas->get($item->id, []);
                $componentes = $variantes['componentes'] ?? [];
                InvEntregaKitService::iniciarEntrega($item, $cajeroId, $componentes);
            } else {
                InvEntregaSimpleService::entregar($item, $cajeroId);
            }
            // La marca entrega_completa del ítem se respeta dentro de cada servicio:
            // el despacho automático nunca fuerza una entrega parcial.
        }

        static::actualizarStatusPedido($pedido);
    }

    /**
     * Recalcula el status del pedido en función del estado de sus entregas.
     *
     * Solo pasa a 'entregado' cuando todos los ítems están completamente entregados;
     * una entrega parcial mantiene el pedido en 'entregando'.
     *
     * @param InvPedido $pedido
     * @return void
     */
    public static function actualizarStatusPedido(InvPedido $pedido): void
    {
        $pedido->load(['items.producto', 'items.entregaSimple', 'items.entregaKit']);

        $todoEntregado = $pedido->items->every(function ($item) {
            if ($item->producto?->tipo === 'kit') {
                return $item->entregaKit
                    && $item->entregaKit->status === InvEntregaKit::STATUS_COMPLETO;
            }

            return $item->entregaSimple
                && $item->entregaSimple->status === InvEntregaSimple::STATUS_ENTREGADO;
        });

        if ($todoEntregado) {
            $pedido->update(['status' => InvPedido::STATUS_ENTREGADO]);
        }
    }
}
