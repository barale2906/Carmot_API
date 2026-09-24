<?php

namespace App\Services\Inventarios;

use App\Models\Configuracion\Sede;
use App\Models\Financiero\Descuento\Descuento;
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
     *   items: [{producto_id, cantidad, descuento_id?, entregar?: bool, entrega_completa?: bool}],
     *   monto_abono: float,
     *   medios_pago: [{medio_pago, valor, referencia?, banco_id?}],
     *   observaciones?: string,
     *   entrega_inmediata?: bool,
     *   variantes_kit?: [{item_index?: int, pedido_item_id?: int, componentes: [{kit_componente_id, producto_entregado_id}]}]
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

                $precioLista      = (float) $precio->precio;
                $descuentoUnitario = 0.0;
                $precioFinal      = $precioLista;

                if (!empty($item['descuento_id'])) {
                    $descuento = Descuento::find($item['descuento_id']);

                    if ($descuento && $descuento->estaVigente()) {
                        $descuentoUnitario = $descuento->calcularDescuento($precioLista);
                        $precioFinal       = max(0, $precioLista - $descuentoUnitario);
                    }
                }

                $subtotal = round($precioFinal * $item['cantidad'], 2);
                $valorTotal += $subtotal;

                $itemsConPrecio[] = [
                    'producto_id'        => $item['producto_id'],
                    'cantidad'           => $item['cantidad'],
                    'entrega_completa'   => (bool) ($item['entrega_completa'] ?? false),
                    'precio_lista'       => $precioLista,
                    'descuento_unitario' => $descuentoUnitario,
                    'precio_unitario'    => $precioFinal,
                    'subtotal'           => $subtotal,
                ];
            }

            $descuentoTotalItems = collect($itemsConPrecio)
                ->sum(fn ($i) => $i['descuento_unitario'] * $i['cantidad']);

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

            // Crear los ítems conservando el orden del request para poder resolver
            // las variantes de kit y los ítems a entregar por su índice.
            $idsPorIndice = [];

            foreach ($itemsConPrecio as $indice => $itemData) {
                $pedidoItem = InvPedidoItem::create(array_merge($itemData, ['pedido_id' => $pedido->id]));
                $idsPorIndice[$indice] = $pedidoItem->id;
            }

            // Crear el recibo de pago
            $reciboResult = static::crearRecibo($pedido, $montoAbono, array_merge($datos, [
                'descuento_total' => round($descuentoTotalItems, 2),
            ]));

            if ($pedido->status === InvPedido::STATUS_PAGADO) {
                // Todo ítem de un pedido pagado necesita su registro de entrega,
                // se despache ahora o quede para que el estudiante lo retire después.
                InvDespachoService::prepararEntregas($pedido, $datos['cajero_id']);

                if ($datos['entrega_inmediata'] ?? true) {
                    InvDespachoService::despacharPedido(
                        $pedido,
                        $datos['cajero_id'],
                        static::resolverVariantes($datos['variantes_kit'] ?? [], $idsPorIndice),
                        static::resolverItemsAEntregar($datos['items'], $idsPorIndice)
                    );
                }

                $pedido->refresh();
            }

            return [
                'pedido'            => $pedido->load(['items.producto', 'almacen', 'sede']),
                'recibo'            => $reciboResult['recibo'],
                'mediosPagoCreados' => $reciboResult['mediosPagoCreados'],
                'esTransferencia'   => $reciboResult['esTransferencia'],
            ];
        });
    }

    /**
     * Traduce las variantes de kit recibidas en el request a IDs reales de inv_pedido_items.
     *
     * Al crear la venta el frontend todavía no conoce el `pedido_item_id` (los ítems
     * no existen), así que referencia cada kit por `item_index`: la posición del
     * producto dentro del arreglo `items`. Se acepta también `pedido_item_id` para
     * el flujo de abono, donde los ítems ya existen.
     *
     * @param array           $variantesKit
     * @param array<int, int> $idsPorIndice  indice del request => inv_pedido_items.id
     * @return array<int, array<string, mixed>>
     */
    private static function resolverVariantes(array $variantesKit, array $idsPorIndice): array
    {
        return collect($variantesKit)
            ->map(function (array $variante) use ($idsPorIndice) {
                $pedidoItemId = $variante['pedido_item_id']
                    ?? ($idsPorIndice[$variante['item_index']] ?? null);

                if (! $pedidoItemId) {
                    return null;
                }

                return [
                    'pedido_item_id' => $pedidoItemId,
                    'componentes'    => $variante['componentes'] ?? [],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Determina qué ítems del pedido se despachan de inmediato.
     *
     * Cada ítem puede traer `entregar: false` para venderse sin descargar inventario
     * (el estudiante lo retira después). Si ningún ítem lo indica, se despachan todos.
     *
     * @param array           $items         Ítems tal como llegaron en el request
     * @param array<int, int> $idsPorIndice  indice del request => inv_pedido_items.id
     * @return array<int, int>|null  IDs a despachar, o null para despachar todos
     */
    private static function resolverItemsAEntregar(array $items, array $idsPorIndice): ?array
    {
        $algunoExcluido = collect($items)->contains(
            fn ($item) => array_key_exists('entregar', $item) && $item['entregar'] === false
        );

        if (! $algunoExcluido) {
            return null;
        }

        return collect($items)
            ->filter(fn ($item) => ($item['entregar'] ?? true) !== false)
            ->keys()
            ->map(fn ($indice) => $idsPorIndice[$indice] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Registra un abono a un pedido activo.
     *
     * @param InvPedido $pedido
     * @param array $datos {
     *   cajero_id: int,
     *   monto_abono: float,
     *   medios_pago: [{medio_pago, valor, referencia?, banco_id?}],
     *   entrega_inmediata?: bool,
     *   items_a_entregar?: int[],
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

            $reciboResult = static::crearRecibo($pedido, $montoAbono, $datos);

            if ($nuevoStatus === InvPedido::STATUS_PAGADO) {
                InvDespachoService::prepararEntregas($pedido, $datos['cajero_id']);

                if ($datos['entrega_inmediata'] ?? true) {
                    InvDespachoService::despacharPedido(
                        $pedido,
                        $datos['cajero_id'],
                        $datos['variantes_kit'] ?? [],
                        $datos['items_a_entregar'] ?? null
                    );
                }

                $pedido->refresh();
            }

            return [
                'pedido'            => $pedido->load(['items.producto', 'almacen', 'sede']),
                'recibo'            => $reciboResult['recibo'],
                'mediosPagoCreados' => $reciboResult['mediosPagoCreados'],
                'esTransferencia'   => $reciboResult['esTransferencia'],
            ];
        });
    }

    /**
     * Crea el ReciboPago y sus medios de pago, y lo vincula al pedido.
     * Detecta si el pago es por transferencia y ajusta el status a PENDIENTE_APROBACION.
     *
     * @param InvPedido $pedido
     * @param float     $monto
     * @param array     $datos
     * @return array{recibo: ReciboPago, mediosPagoCreados: ReciboPagoMedioPago[], esTransferencia: bool}
     */
    public static function crearRecibo(InvPedido $pedido, float $monto, array $datos): array
    {
        $descuentoTotal = (float) ($datos['descuento_total'] ?? 0);

        $esTransferencia = collect($datos['medios_pago'])->contains('medio_pago', 'transferencia');

        // Resolver nombre del banco para el campo de texto legado
        $bancoNombre = null;
        if ($esTransferencia) {
            $bancoId = collect($datos['medios_pago'])->firstWhere('medio_pago', 'transferencia')['banco_id'] ?? null;
            if ($bancoId) {
                $bancoNombre = \App\Models\Configuracion\Banco::find($bancoId)?->nombre;
            }
        }

        $recibo = ReciboPago::create([
            'origen'            => ReciboPago::ORIGEN_INVENTARIOS,
            'sede_id'           => $pedido->sede_id,
            'estudiante_id'     => $pedido->estudiante_id,
            'cajero_id'         => $datos['cajero_id'],
            'fecha_recibo'      => now()->toDateString(),
            'fecha_transaccion' => now(),
            'valor_total'       => $monto,
            'descuento_total'   => $descuentoTotal,
            'sobrecargo_total'  => 0,
            'banco'             => $esTransferencia ? $bancoNombre : null,
            'status'            => $esTransferencia
                ? ReciboPago::STATUS_PENDIENTE_APROBACION
                : ReciboPago::STATUS_CREADO,
        ]);

        $mediosPagoCreados = [];
        foreach ($datos['medios_pago'] as $mp) {
            $mediosPagoCreados[] = ReciboPagoMedioPago::create([
                'recibo_pago_id'      => $recibo->id,
                'medio_pago'          => $mp['medio_pago'],
                'valor'               => $mp['valor'],
                'referencia'          => $mp['referencia'] ?? null,
                'banco_id'            => $mp['banco_id'] ?? null,
                'banco'               => ($mp['medio_pago'] === 'transferencia') ? $bancoNombre : ($mp['banco'] ?? null),
                'tipo_tarjeta'        => $mp['tipo_tarjeta'] ?? null,
                'numero_transaccion'  => $mp['numero_transaccion'] ?? null,
            ]);
        }

        ReciboPagoInvPedido::create([
            'recibo_pago_id' => $recibo->id,
            'pedido_id'      => $pedido->id,
            'monto_abonado'  => $monto,
        ]);

        return ['recibo' => $recibo, 'mediosPagoCreados' => $mediosPagoCreados, 'esTransferencia' => $esTransferencia];
    }
}
