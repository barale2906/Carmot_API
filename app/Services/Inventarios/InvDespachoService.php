<?php

namespace App\Services\Inventarios;

use App\Models\Inventarios\InvPedido;

/**
 * InvDespachoService — punto de entrada único al despacho cuando el saldo llega a 0.
 *
 * Itera los ítems del pedido y delega a InvEntregaSimpleService o InvEntregaKitService
 * según el tipo de producto. Actualiza el status del pedido al finalizar.
 *
 * Debe llamarse dentro de la misma DB::transaction() del abono que cerró el saldo.
 */
class InvDespachoService
{
    /**
     * Despacha todos los ítems de un pedido pagado.
     *
     * @param InvPedido $pedido          Pedido con status='pagado'
     * @param int       $cajeroId        Usuario que ejecuta el despacho
     * @param array     $variantesKit    Variantes para ítems de kit: [{pedido_item_id, componentes: [{kit_componente_id, producto_entregado_id}]}]
     * @return void
     */
    public static function despacharPedido(
        InvPedido $pedido,
        int $cajeroId,
        array $variantesKit = []
    ): void {
        $pedido->update(['status' => InvPedido::STATUS_ENTREGANDO]);

        // Indexar variantes por pedido_item_id
        $variantesIndexadas = collect($variantesKit)->keyBy('pedido_item_id');

        $pedido->loadMissing('items.producto');

        foreach ($pedido->items as $item) {
            if ($item->producto->tipo === 'simple') {
                InvEntregaSimpleService::entregar($item, $cajeroId);
            } elseif ($item->producto->tipo === 'kit') {
                $variantes = $variantesIndexadas->get($item->id, []);
                $componentes = $variantes['componentes'] ?? [];
                InvEntregaKitService::iniciarEntrega($item, $cajeroId, $componentes);
            }
        }

        static::actualizarStatusPedido($pedido);
    }

    /**
     * Recalcula el status del pedido en función del estado de sus entregas.
     *
     * @param InvPedido $pedido
     * @return void
     */
    public static function actualizarStatusPedido(InvPedido $pedido): void
    {
        $pedido->load(['items.entregaSimple', 'items.entregaKit']);

        $todoEntregado = $pedido->items->every(function ($item) {
            if ($item->producto_id && $item->entregaSimple) {
                return $item->entregaSimple->status === 'entregado';
            }
            if ($item->entregaKit) {
                return $item->entregaKit->status === 'completo';
            }
            return false;
        });

        if ($todoEntregado) {
            $pedido->update(['status' => InvPedido::STATUS_ENTREGADO]);
        }
    }
}
