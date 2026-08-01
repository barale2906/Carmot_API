<?php

namespace App\Services\Inventarios;

use App\Models\Inventarios\InvDocumentoMovimiento;
use App\Models\Inventarios\InvEntregaKit;
use App\Models\Inventarios\InvEntregaKitComponente;
use App\Models\Inventarios\InvMovimiento;
use App\Models\Inventarios\InvPedidoItem;
use App\Models\Inventarios\InvProducto;
use App\Models\Inventarios\InvStock;

/**
 * InvEntregaKitService — inicia la entrega de un ítem de tipo kit.
 *
 * Para cada componente:
 * - Si es simple con stock: descuenta stock, marca entregado.
 * - Si es grupo (cajero elige variante): usa la selección del request.
 * - Sin stock: crea necesidad de compra y deja el componente pendiente.
 *
 * Debe llamarse dentro de DB::transaction().
 */
class InvEntregaKitService
{
    /**
     * Inicia la entrega de un kit, procesando cada componente.
     *
     * @param InvPedidoItem $item               Ítem del pedido (tipo=kit)
     * @param int           $cajeroId
     * @param array         $variantesSeleccionadas  [{kit_componente_id, producto_entregado_id}]
     *                                              Solo para componentes de tipo=grupo.
     * @return InvEntregaKit
     */
    public static function iniciarEntrega(
        InvPedidoItem $item,
        int $cajeroId,
        array $variantesSeleccionadas = []
    ): InvEntregaKit {
        $pedido    = $item->pedido;
        $almacenId = $pedido->almacen_id;
        $estudianteId = $pedido->estudiante_id;

        $entregaKit = InvEntregaKit::firstOrCreate(
            ['pedido_item_id' => $item->id],
            [
                'kit_producto_id' => $item->producto_id,
                'cantidad_kits'   => $item->cantidad,
                'status'          => InvEntregaKit::STATUS_PENDIENTE,
                'user_id'         => $cajeroId,
            ]
        );

        if ($entregaKit->status === InvEntregaKit::STATUS_COMPLETO) {
            return $entregaKit;
        }

        // Indexar variantes seleccionadas por kit_componente_id para acceso O(1)
        $variantes = collect($variantesSeleccionadas)->keyBy('kit_componente_id');

        $componentes = InvKitExplosionService::explotar($item->producto_id, $item->cantidad);

        // Crear un único documento de salida para todos los componentes con stock
        $documento = null;

        foreach ($componentes as $comp) {
            $compExistente = InvEntregaKitComponente::where('entrega_kit_id', $entregaKit->id)
                ->where('kit_componente_id', $comp['kit_componente_id'])
                ->first();

            if ($compExistente && $compExistente->status === InvEntregaKitComponente::STATUS_ENTREGADO) {
                continue;
            }

            // Determinar el producto concreto a entregar
            if ($comp['componente_tipo'] === 'grupo') {
                $varianteData    = $variantes->get($comp['kit_componente_id']);
                $productoEntregId = $varianteData['producto_entregado_id'] ?? null;
            } else {
                $productoEntregId = $comp['componente_id'];
            }

            $linea = $compExistente ?? InvEntregaKitComponente::create([
                'entrega_kit_id'        => $entregaKit->id,
                'kit_componente_id'     => $comp['kit_componente_id'],
                'producto_entregado_id' => $productoEntregId,
                'cantidad_solicitada'   => $comp['cantidad'],
                'cantidad_entregada'    => 0,
                'status'                => InvEntregaKitComponente::STATUS_PENDIENTE,
            ]);

            if (!$productoEntregId) {
                // Sin selección de variante → necesidad de compra
                InvNecesidadService::generarSiNoExiste(
                    $comp['componente_id'],
                    $comp['cantidad'],
                    $almacenId,
                    $estudianteId,
                    InvEntregaKitComponente::class,
                    $linea->id
                );
                continue;
            }

            // Verificar stock del producto concreto
            $stockDisp = InvStock::where('almacen_id', $almacenId)
                ->where('producto_id', $productoEntregId)
                ->value('cantidad_disponible') ?? 0;

            if ($stockDisp >= $comp['cantidad']) {
                if (!$documento) {
                    $documento = InvDocumentoMovimiento::create([
                        'numero_documento' => InvDocumentoMovimiento::generarNumero('salida'),
                        'tipo_documento'   => InvDocumentoMovimiento::TIPO_SALIDA,
                        'almacen_id'       => $almacenId,
                        'pedido_id'        => $pedido->id,
                        'motivo'           => "Entrega kit pedido #{$pedido->id}",
                        'status'           => InvDocumentoMovimiento::STATUS_CONFIRMADO,
                        'user_id'          => $cajeroId,
                    ]);
                }

                InvMovimiento::create([
                    'documento_id'    => $documento->id,
                    'almacen_id'      => $almacenId,
                    'producto_id'     => $productoEntregId,
                    'tipo_movimiento' => 'salida',
                    'cantidad'        => $comp['cantidad'],
                    'referencia_type' => InvEntregaKitComponente::class,
                    'referencia_id'   => $linea->id,
                    'user_id'         => $cajeroId,
                ]);

                InvStockService::decrementar($almacenId, $productoEntregId, $comp['cantidad']);

                $linea->update([
                    'producto_entregado_id' => $productoEntregId,
                    'cantidad_entregada'    => $comp['cantidad'],
                    'status'                => InvEntregaKitComponente::STATUS_ENTREGADO,
                    'fecha_entrega'         => now(),
                    'user_id'               => $cajeroId,
                ]);
            } else {
                $linea->update(['producto_entregado_id' => $productoEntregId]);

                InvNecesidadService::generarSiNoExiste(
                    $productoEntregId,
                    $comp['cantidad'],
                    $almacenId,
                    $estudianteId,
                    InvEntregaKitComponente::class,
                    $linea->id
                );
            }
        }

        $entregaKit->recalcularStatus();

        return $entregaKit->fresh('componentes');
    }
}
