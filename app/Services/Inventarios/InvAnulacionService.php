<?php

namespace App\Services\Inventarios;

use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\Inventarios\InvDocumentoMovimiento;
use App\Models\Inventarios\InvEntregaKitComponente;
use App\Models\Inventarios\InvEntregaSimple;
use App\Models\Inventarios\InvMovimiento;
use App\Models\Inventarios\InvPedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * InvAnulacionService — cancela/anula un pedido de inventario.
 *
 * Inventario: en pedidos 'activo' no se movió stock; en 'pagado'|'entregando'|'entregado'
 * se crea un documento de devolución por todo lo entregado (completo o parcial).
 * Dinero: todos los recibos vigentes del pedido se anulan (devolución al estudiante),
 * incluidas las transferencias por aprobar.
 */
class InvAnulacionService
{
    /**
     * Cancela/anula el pedido: reintegra al almacén lo entregado, cancela sus
     * necesidades de compra, anula sus recibos (el dinero se devuelve al estudiante)
     * y registra motivo, usuario y fecha.
     *
     * Los recibos ya cerrados en caja no se pueden anular (misma regla que el módulo
     * académico); si el pedido tiene alguno, la operación se rechaza completa.
     *
     * @param InvPedido $pedido
     * @param int       $usuarioId
     * @param string    $motivo
     * @return int Cantidad de recibos anulados
     * @throws \RuntimeException Si el pedido ya está cancelado o tiene recibos cerrados.
     */
    public static function anularPedido(InvPedido $pedido, int $usuarioId, string $motivo): int
    {
        if ($pedido->status === InvPedido::STATUS_CANCELADO) {
            throw new \RuntimeException('El pedido ya está cancelado.');
        }

        $recibos = $pedido->reciboLinks()->with('reciboPago')->get()
            ->pluck('reciboPago')
            ->filter(fn (?ReciboPago $recibo) => $recibo && ! $recibo->estaAnulado());

        $cerrados = $recibos->filter(fn (ReciboPago $recibo) => $recibo->estaCerrado());

        if ($cerrados->isNotEmpty()) {
            throw new \RuntimeException(
                'No se puede anular el pedido: los recibos ' . $cerrados->pluck('numero_recibo')->join(', ')
                . ' ya están cerrados en caja.'
            );
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $motivo, $recibos) {
            if ($pedido->status !== InvPedido::STATUS_ACTIVO) {
                // Primero se cancelan: el stock reintegrado no debe atender las
                // necesidades del mismo pedido que se está anulando.
                InvNecesidadService::cancelarDePedido($pedido);
                static::reintegrarStock($pedido, $usuarioId);
            }

            $recibos->each(fn (ReciboPago $recibo) => $recibo->anular(
                Str::limit("Anulación del pedido de inventario #{$pedido->id}: {$motivo}", 500, '')
            ));

            $pedido->update([
                'status'             => InvPedido::STATUS_CANCELADO,
                'motivo_cancelacion' => $motivo,
                'cancelado_por_id'   => $usuarioId,
                'fecha_cancelacion'  => now(),
            ]);

            return $recibos->count();
        });
    }

    /**
     * Recorre las entregas del pedido y reintegra al almacén de origen todo lo que
     * salió (cantidad_entregada), con un documento de devolución.
     *
     * Se usa la cantidad y no el status: una entrega 'parcial' ya descargó stock y,
     * si solo se miraba 'entregado', esas unidades se perdían al anular.
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
            // Lo ya entregado de cada ítem simple, también si la entrega quedó parcial
            if ($item->entregaSimple?->cantidad_entregada > 0) {
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

            // Componentes de kit ya entregados, completos o parciales
            if ($item->entregaKit) {
                foreach ($item->entregaKit->componentes as $comp) {
                    if ($comp->producto_entregado_id && $comp->cantidad_entregada > 0) {
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
