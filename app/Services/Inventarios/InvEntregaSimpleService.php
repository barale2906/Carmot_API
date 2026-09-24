<?php

namespace App\Services\Inventarios;

use App\Models\Inventarios\InvDocumentoMovimiento;
use App\Models\Inventarios\InvEntregaSimple;
use App\Models\Inventarios\InvMovimiento;
use App\Models\Inventarios\InvPedidoItem;
use App\Models\Inventarios\InvStock;

/**
 * InvEntregaSimpleService — despacha la entrega de un ítem de tipo simple.
 *
 * Debe llamarse dentro de DB::transaction().
 * Entrega tanto como permita el stock: si alcanza para todo, la entrega queda
 * 'entregado'; si alcanza para una parte, queda 'parcial' y el faltante mantiene
 * viva su necesidad de compra. Solo queda 'pendiente' si no hay nada que entregar.
 *
 * Excepción: si el ítem está marcado con `entrega_completa`, el comprador exigió
 * recibirlo de una sola vez. En ese caso no se descarga nada mientras el stock no
 * cubra toda la cantidad pendiente, salvo que el cajero fuerce la entrega parcial.
 */
class InvEntregaSimpleService
{
    /**
     * Crea el registro de entrega en estado pendiente, sin descargar inventario.
     *
     * Se llama al quedar pagado el pedido para TODOS los ítems, incluso los que el
     * cajero excluyó del despacho inmediato. Sin esto el ítem no tendría registro de
     * entrega y la pantalla de Entregas Pendientes no tendría un id con el cual
     * despacharlo más adelante.
     *
     * @param InvPedidoItem $item
     * @return InvEntregaSimple
     */
    public static function prepararPendiente(InvPedidoItem $item): InvEntregaSimple
    {
        $pedido = $item->pedido;

        $entrega = InvEntregaSimple::firstOrCreate(
            ['pedido_item_id' => $item->id],
            [
                'producto_id'        => $item->producto_id,
                'cantidad_entregada' => 0,
                'status'             => InvEntregaSimple::STATUS_PENDIENTE,
            ]
        );

        $pendiente = $item->cantidad - $entrega->cantidad_entregada;

        if ($pendiente > 0) {
            InvNecesidadService::registrarSiFaltaStock(
                $item->producto_id,
                $pendiente,
                $pedido->almacen_id,
                $pedido->estudiante_id,
                InvEntregaSimple::class,
                $entrega->id
            );
        }

        return $entrega;
    }

    /**
     * Inicia, avanza o completa la entrega de un ítem simple.
     *
     * @param InvPedidoItem $item           Ítem del pedido (tipo=simple)
     * @param int           $cajeroId       ID del usuario cajero que ejecuta la entrega
     * @param int|null      $cantidad       Cantidad a entregar ahora; null entrega todo lo posible
     * @param bool          $forzarParcial  Ignora la marca entrega_completa del ítem
     * @return InvEntregaSimple
     */
    public static function entregar(
        InvPedidoItem $item,
        int $cajeroId,
        ?int $cantidad = null,
        bool $forzarParcial = false
    ): InvEntregaSimple {
        $pedido       = $item->pedido;
        $almacenId    = $pedido->almacen_id;
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

        $pendiente = $item->cantidad - $entrega->cantidad_entregada;

        if ($pendiente <= 0) {
            $entrega->update(['status' => InvEntregaSimple::STATUS_ENTREGADO]);

            return $entrega->fresh();
        }

        // Nunca se entrega más de lo pendiente, aunque el cajero pida más.
        $solicitado = $cantidad !== null ? min($cantidad, $pendiente) : $pendiente;

        $stockDisponible = InvStock::where('almacen_id', $almacenId)
            ->where('producto_id', $item->producto_id)
            ->value('cantidad_disponible') ?? 0;

        $aEntregar = (int) min($stockDisponible, $solicitado);

        // El comprador pidió el ítem completo: o sale todo, o no sale nada.
        if ($item->entrega_completa && ! $forzarParcial && $aEntregar < $pendiente) {
            $aEntregar = 0;
        }

        if ($aEntregar > 0) {
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
                'documento_id'    => $documento->id,
                'almacen_id'      => $almacenId,
                'producto_id'     => $item->producto_id,
                'tipo_movimiento' => 'salida',
                'cantidad'        => $aEntregar,
                'referencia_type' => InvEntregaSimple::class,
                'referencia_id'   => $entrega->id,
                'user_id'         => $cajeroId,
            ]);

            InvStockService::decrementar($almacenId, $item->producto_id, $aEntregar);

            $totalEntregado = $entrega->cantidad_entregada + $aEntregar;

            $entrega->update([
                'cantidad_entregada' => $totalEntregado,
                'status'             => $totalEntregado >= $item->cantidad
                    ? InvEntregaSimple::STATUS_ENTREGADO
                    : InvEntregaSimple::STATUS_PARCIAL,
                'fecha_entrega'      => now(),
                'user_id'            => $cajeroId,
            ]);
        }

        $faltante = $item->cantidad - $entrega->fresh()->cantidad_entregada;

        if ($faltante > 0) {
            InvNecesidadService::registrarSiFaltaStock(
                $item->producto_id,
                $faltante,
                $almacenId,
                $estudianteId,
                InvEntregaSimple::class,
                $entrega->id
            );
        }

        return $entrega->fresh();
    }
}
