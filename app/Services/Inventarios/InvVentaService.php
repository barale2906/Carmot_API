<?php

namespace App\Services\Inventarios;

use App\Models\Configuracion\Sede;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\Financiero\ReciboPago\ReciboPagoMedioPago;
use App\Models\Inventarios\InvPedido;
use App\Models\Inventarios\InvPedidoItem;
use App\Models\Inventarios\InvPrecioProducto;
use App\Models\Inventarios\ReciboPagoInvPedido;
use Illuminate\Support\Facades\DB;

/**
 * InvVentaService — crea y gestiona pedidos de inventario con sus recibos de pago.
 *
 * Reglas:
 * - El precio se obtiene de la lista de precios de inventario vigente para la sede.
 * - El valor_total del pedido es inmutable una vez creado.
 * - Cuando el saldo llega a 0, se dispara InvDespachoService::despacharPedido().
 */
class InvVentaService
{
    /**
     * Crea un pedido nuevo con el primer abono (o pago total).
     *
     * @param array $datos {
     *   estudiante_id: int,
     *   sede_id: int,
     *   almacen_id: int,
     *   cajero_id: int,
     *   items: [{producto_id, cantidad}],
     *   monto_abono: float,
     *   medios_pago: [{medio_pago, valor, referencia?, banco_id?}],
     *   observaciones?: string,
     *   variantes_kit?: [{pedido_item_id, componentes: [{kit_componente_id, producto_entregado_id}]}]
     * }
     * @return array{pedido: InvPedido, recibo: ReciboPago}
     * @throws \RuntimeException Si no se encuentra precio vigente para algún producto.
     */
    public static function crearPedido(array $datos): array
    {
        return DB::transaction(function () use ($datos) {
            $sede       = Sede::findOrFail($datos['sede_id']);
            $poblacionId = $sede->poblacion_id;

            // Calcular valor total resolviendo precios
            $itemsConPrecio = [];
            $valorTotal     = 0;

            foreach ($datos['items'] as $item) {
                $precio = InvPrecioProducto::precioVigente($item['producto_id'], $poblacionId);

                if (!$precio) {
                    throw new \RuntimeException(
                        "No hay precio vigente para el producto ID {$item['producto_id']} en la sede seleccionada."
                    );
                }

                $subtotal = $precio->precio * $item['cantidad'];
                $valorTotal += $subtotal;

                $itemsConPrecio[] = [
                    'producto_id'     => $item['producto_id'],
                    'cantidad'        => $item['cantidad'],
                    'precio_unitario' => $precio->precio,
                    'subtotal'        => $subtotal,
                ];
            }

            $montoAbono = (float) $datos['monto_abono'];
            $saldo      = round($valorTotal - $montoAbono, 2);

            // Crear el pedido
            $pedido = InvPedido::create([
                'estudiante_id'   => $datos['estudiante_id'],
                'sede_id'         => $datos['sede_id'],
                'almacen_id'      => $datos['almacen_id'],
                'cajero_id'       => $datos['cajero_id'],
                'valor_total'     => $valorTotal,
                'abono_acumulado' => $montoAbono,
                'saldo'           => $saldo,
                'status'          => $saldo <= 0 ? InvPedido::STATUS_PAGADO : InvPedido::STATUS_ACTIVO,
                'observaciones'   => $datos['observaciones'] ?? null,
            ]);

            // Crear los ítems
            foreach ($itemsConPrecio as $itemData) {
                InvPedidoItem::create(array_merge($itemData, ['pedido_id' => $pedido->id]));
            }

            // Crear el recibo de pago
            $recibo = static::crearRecibo($pedido, $montoAbono, $datos);

            // Si ya está pagado, despachar
            if ($pedido->status === InvPedido::STATUS_PAGADO) {
                InvDespachoService::despacharPedido(
                    $pedido,
                    $datos['cajero_id'],
                    $datos['variantes_kit'] ?? []
                );
                $pedido->refresh();
            }

            return ['pedido' => $pedido->load(['items.producto', 'almacen', 'sede']), 'recibo' => $recibo];
        });
    }

    /**
     * Registra un abono a un pedido activo.
     *
     * @param InvPedido $pedido
     * @param array $datos {
     *   cajero_id: int,
     *   monto_abono: float,
     *   medios_pago: [{medio_pago, valor, referencia?, banco_id?}],
     *   variantes_kit?: array
     * }
     * @return array{pedido: InvPedido, recibo: ReciboPago}
     * @throws \RuntimeException Si el abono supera el saldo.
     */
    public static function abonarPedido(InvPedido $pedido, array $datos): array
    {
        return DB::transaction(function () use ($pedido, $datos) {
            if ($pedido->status !== InvPedido::STATUS_ACTIVO) {
                throw new \RuntimeException('Solo se pueden abonar pedidos activos.');
            }

            $montoAbono = (float) $datos['monto_abono'];

            if ($montoAbono > (float) $pedido->saldo) {
                throw new \RuntimeException(
                    "El abono ({$montoAbono}) supera el saldo pendiente ({$pedido->saldo})."
                );
            }

            $nuevaAcumulado = round((float) $pedido->abono_acumulado + $montoAbono, 2);
            $nuevoSaldo     = round((float) $pedido->valor_total - $nuevaAcumulado, 2);
            $nuevoStatus    = $nuevoSaldo <= 0 ? InvPedido::STATUS_PAGADO : InvPedido::STATUS_ACTIVO;

            $pedido->update([
                'abono_acumulado' => $nuevaAcumulado,
                'saldo'           => $nuevoSaldo,
                'status'          => $nuevoStatus,
            ]);

            $recibo = static::crearRecibo($pedido, $montoAbono, $datos);

            if ($nuevoStatus === InvPedido::STATUS_PAGADO) {
                InvDespachoService::despacharPedido(
                    $pedido,
                    $datos['cajero_id'],
                    $datos['variantes_kit'] ?? []
                );
                $pedido->refresh();
            }

            return ['pedido' => $pedido->load(['items.producto', 'almacen', 'sede']), 'recibo' => $recibo];
        });
    }

    /**
     * Crea el ReciboPago y sus medios de pago, y lo vincula al pedido.
     *
     * @param InvPedido $pedido
     * @param float     $monto
     * @param array     $datos
     * @return ReciboPago
     */
    private static function crearRecibo(InvPedido $pedido, float $monto, array $datos): ReciboPago
    {
        $recibo = ReciboPago::create([
            'origen'            => ReciboPago::ORIGEN_INVENTARIOS,
            'sede_id'           => $pedido->sede_id,
            'estudiante_id'     => $pedido->estudiante_id,
            'cajero_id'         => $datos['cajero_id'],
            'fecha_recibo'      => now()->toDateString(),
            'fecha_transaccion' => now(),
            'valor_total'       => $monto,
            'descuento_total'   => 0,
            'sobrecargo_total'  => 0,
            'status'            => ReciboPago::STATUS_CREADO,
        ]);

        foreach ($datos['medios_pago'] as $mp) {
            ReciboPagoMedioPago::create([
                'recibo_pago_id'      => $recibo->id,
                'medio_pago'          => $mp['medio_pago'],
                'valor'               => $mp['valor'],
                'referencia'          => $mp['referencia'] ?? null,
                'banco_id'            => $mp['banco_id'] ?? null,
            ]);
        }

        ReciboPagoInvPedido::create([
            'recibo_pago_id' => $recibo->id,
            'pedido_id'      => $pedido->id,
            'monto_abonado'  => $monto,
        ]);

        return $recibo;
    }
}
