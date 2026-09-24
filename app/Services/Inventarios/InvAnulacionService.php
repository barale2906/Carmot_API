<?php

namespace App\Services\Inventarios;

use App\Models\Inventarios\InvDocumentoMovimiento;
use App\Models\Inventarios\InvEntregaKitComponente;
use App\Models\Inventarios\InvEntregaSimple;
use App\Models\Inventarios\InvMovimiento;
use App\Models\Inventarios\InvPedido;
use Illuminate\Support\Facades\DB;

/**
 * InvAnulacionService — anula un pedido de inventario y reintegra el stock.
 *
 * Para pedidos 'activo': solo cambia el status a 'cancelado' (no se movió stock).
 * Para pedidos 'pagado'|'entregando'|'entregado': crea un documento de devolución
 * por las entregas ya completadas e invierte los movimientos de stock.
 *
 * Debe llamarse dentro de DB::transaction() o el método lo crea internamente.
 */
class InvAnulacionService
{
    /**
     * Anula el pedido e invierte el stock de las entregas completadas.
     *
     * @param InvPedido $pedido
     * @param int       $cajeroId
     * @throws \RuntimeException Si el pedido ya está cancelado.
     */
    public static function anularPedido(InvPedido $pedido, int $cajeroId): void
    {
        if ($pedido->status === InvPedido::STATUS_CANCELADO) {
            throw new \RuntimeException('El pedido ya está cancelado.');
        }

        DB::transaction(function () use ($pedido, $cajeroId) {
            if ($pedido->status !== InvPedido::STATUS_ACTIVO) {
                static::reintegrarStock($pedido, $cajeroId);
            }

            $pedido->update(['status' => InvPedido::STATUS_CANCELADO]);
        });
    }

    /**
     * Recorre las entregas completadas del pedido y crea documentos de devolución
     * para reintegrar el stock en el almacén de origen.
     *
     * @param InvPedido $pedido
     * @param int       $cajeroId
     */
    private static function reintegrarStock(InvPedido $pedido, int $cajeroId): void
    {
        $pedido->loadMissing([
            'items.entregaSimple',
            'items.entregaKit.componentes',
        ]);

        $documento = null;

        foreach ($pedido->items as $item) {
            // Ítems simples ya entregados
            if ($item->entregaSimple?->status === InvEntregaSimple::STATUS_ENTREGADO) {
                $documento ??= static::crearDocumentoDevolucion($pedido, $cajeroId);

                InvMovimiento::create([
                    'documento_id'    => $documento->id,
                    'almacen_id'      => $pedido->almacen_id,
                    'producto_id'     => $item->entregaSimple->producto_id,
                    'tipo_movimiento' => 'entrada',
                    'cantidad'        => $item->entregaSimple->cantidad_entregada,
                    'referencia_type' => InvEntregaSimple::class,
                    'referencia_id'   => $item->entregaSimple->id,
                    'user_id'         => $cajeroId,
                ]);

                InvStockService::incrementar(
                    $pedido->almacen_id,
                    $item->entregaSimple->producto_id,
                    $item->entregaSimple->cantidad_entregada
                );
            }

            // Componentes de kit ya entregados
            if ($item->entregaKit) {
                foreach ($item->entregaKit->componentes as $comp) {
                    if ($comp->status === InvEntregaKitComponente::STATUS_ENTREGADO
                        && $comp->producto_entregado_id
                        && $comp->cantidad_entregada > 0
                    ) {
                        $documento ??= static::crearDocumentoDevolucion($pedido, $cajeroId);

                        InvMovimiento::create([
                            'documento_id'    => $documento->id,
                            'almacen_id'      => $pedido->almacen_id,
                            'producto_id'     => $comp->producto_entregado_id,
                            'tipo_movimiento' => 'entrada',
                            'cantidad'        => $comp->cantidad_entregada,
                            'referencia_type' => InvEntregaKitComponente::class,
                            'referencia_id'   => $comp->id,
                            'user_id'         => $cajeroId,
                        ]);

                        InvStockService::incrementar(
                            $pedido->almacen_id,
                            $comp->producto_entregado_id,
                            $comp->cantidad_entregada
                        );
                    }
                }
            }
        }
    }

    /**
     * Crea el documento de movimiento de tipo 'devolucion' para la anulación.
     *
     * @param InvPedido $pedido
     * @param int       $cajeroId
     * @return InvDocumentoMovimiento
     */
    private static function crearDocumentoDevolucion(InvPedido $pedido, int $cajeroId): InvDocumentoMovimiento
    {
        return InvDocumentoMovimiento::create([
            'numero_documento' => InvDocumentoMovimiento::generarNumero('devolucion'),
            'tipo_documento'   => InvDocumentoMovimiento::TIPO_DEVOLUCION,
            'almacen_id'       => $pedido->almacen_id,
            'pedido_id'        => $pedido->id,
            'motivo'           => "Anulación pedido #{$pedido->id}",
            'status'           => InvDocumentoMovimiento::STATUS_CONFIRMADO,
            'user_id'          => $cajeroId,
        ]);
    }
}
