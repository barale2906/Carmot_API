<?php

namespace App\Services\Inventarios;

use App\Models\Configuracion\Sede;
use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
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
 * - Un abono por transferencia no cuenta en el pedido hasta que se aprueba: mientras
 *   tanto no mueve abono_acumulado/saldo ni genera entregas ni movimientos de stock.
 *   Al aprobarse (confirmarTransferencia) las entregas quedan pendientes, nunca se
 *   despachan en ese momento: el estudiante las retira después.
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
     * @throws \RuntimeException Si no hay precio vigente para algún producto o el abono supera el total.
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

            // Los sobrecargos van al recibo, no al pedido: el abono nunca supera el valor de los productos.
            if ($saldo < 0) {
                throw new \RuntimeException(
                    "El abono ({$montoAbono}) supera el valor total del pedido ({$valorTotal})."
                );
            }

            // Una transferencia por aprobar todavía no es pago: el pedido nace sin abono.
            if (static::esTransferencia($datos['medios_pago'])) {
                $montoAbono = 0.0;
                $saldo      = round($valorTotal, 2);
            }

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

            // Crear el recibo de pago por el monto recibido, aunque aún no cuente en el pedido
            $reciboResult = static::crearRecibo($pedido, (float) $datos['monto_abono'], array_merge($datos, [
                'descuento_total' => round($descuentoTotalItems, 2),
            ]));

            if ($pedido->status === InvPedido::STATUS_PAGADO) {
                // Todo ítem de un pedido pagado necesita su registro de entrega,
                // se despache ahora o quede para que el estudiante lo retire después.
                $variantesKit = static::resolverVariantes($datos['variantes_kit'] ?? [], $idsPorIndice);

                InvDespachoService::prepararEntregas($pedido, $datos['cajero_id'], $variantesKit);

                if ($datos['entrega_inmediata'] ?? true) {
                    InvDespachoService::despacharPedido(
                        $pedido,
                        $datos['cajero_id'],
                        $variantesKit,
                        static::resolverItemsAEntregar($datos['items'], $idsPorIndice)
                    );
                }

                $pedido->refresh();
            }

            return [
                'pedido'            => $pedido->load(['items.producto', 'almacen', 'sede'])->cargarAbonoPendienteAprobacion(),
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
     * @throws \RuntimeException Si el abono supera el saldo descontando las transferencias por aprobar.
     */
    public static function abonarPedido(InvPedido $pedido, array $datos): array
    {
        return DB::transaction(function () use ($pedido, $datos) {
            if ($pedido->status !== InvPedido::STATUS_ACTIVO) {
                throw new \RuntimeException('Solo se pueden abonar pedidos activos.');
            }

            $montoAbono = (float) $datos['monto_abono'];
            $pendiente  = $pedido->montoPendienteAprobacion();
            $disponible = round((float) $pedido->saldo - $pendiente, 2);

            if ($montoAbono > $disponible) {
                throw new \RuntimeException($pendiente > 0
                    ? "El abono ({$montoAbono}) supera el saldo pendiente ({$disponible}), descontando {$pendiente} en transferencias por aprobar."
                    : "El abono ({$montoAbono}) supera el saldo pendiente ({$pedido->saldo})."
                );
            }

            // La transferencia solo se registra; el pedido se actualiza al aprobarla.
            if (static::esTransferencia($datos['medios_pago'])) {
                $reciboResult = static::crearRecibo($pedido, $montoAbono, $datos);

                return [
                    'pedido'            => $pedido->load(['items.producto', 'almacen', 'sede'])->cargarAbonoPendienteAprobacion(),
                    'recibo'            => $reciboResult['recibo'],
                    'mediosPagoCreados' => $reciboResult['mediosPagoCreados'],
                    'esTransferencia'   => true,
                ];
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
                InvDespachoService::prepararEntregas($pedido, $datos['cajero_id'], $datos['variantes_kit'] ?? []);

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
                'pedido'            => $pedido->load(['items.producto', 'almacen', 'sede'])->cargarAbonoPendienteAprobacion(),
                'recibo'            => $reciboResult['recibo'],
                'mediosPagoCreados' => $reciboResult['mediosPagoCreados'],
                'esTransferencia'   => $reciboResult['esTransferencia'],
            ];
        });
    }

    /**
     * Aplica a sus pedidos el abono de un recibo de transferencia recién aprobado.
     *
     * Suma el monto al abono_acumulado y, si el pedido queda saldado, lo pasa a
     * 'pagado' y crea los registros de entrega como PENDIENTES aunque haya stock:
     * la aprobación ocurre sin el estudiante presente, así que el despacho (y el
     * movimiento de inventario) se hace cuando él regrese, desde Entregas.
     *
     * Debe ejecutarse dentro de la misma transacción que aprueba el recibo.
     *
     * @param ReciboPago $recibo
     * @param int        $usuarioId  Validador que aprueba
     * @return void
     * @throws \RuntimeException Si el pedido ya no está activo o el abono supera su saldo.
     */
    public static function confirmarTransferencia(ReciboPago $recibo, int $usuarioId): void
    {
        $recibo->loadMissing('pedidoLinks.pedido');

        foreach ($recibo->pedidoLinks as $link) {
            $pedido = $link->pedido;
            $monto  = (float) $link->monto_abonado;

            if ($pedido->status !== InvPedido::STATUS_ACTIVO) {
                throw new \RuntimeException(
                    "El pedido #{$pedido->id} está {$pedido->status}; no se puede aplicar la transferencia."
                );
            }

            if ($monto > (float) $pedido->saldo + 0.01) {
                throw new \RuntimeException(
                    "La transferencia ({$monto}) supera el saldo actual del pedido #{$pedido->id} ({$pedido->saldo})."
                );
            }

            $nuevoAcumulado = round((float) $pedido->abono_acumulado + $monto, 2);
            $nuevoSaldo     = round((float) $pedido->valor_total - $nuevoAcumulado, 2);

            $pedido->update([
                'abono_acumulado' => $nuevoAcumulado,
                'saldo'           => $nuevoSaldo,
                'status'          => $nuevoSaldo <= 0 ? InvPedido::STATUS_PAGADO : InvPedido::STATUS_ACTIVO,
            ]);

            if ($pedido->status === InvPedido::STATUS_PAGADO) {
                InvDespachoService::prepararEntregas($pedido, $usuarioId);
            }
        }
    }

    /**
     * Revierte en sus pedidos el abono de un recibo de inventario que se va a anular.
     *
     * Solo descuenta lo que el recibo efectivamente sumó: un recibo por aprobar o
     * rechazado nunca contó en el pedido. Si el pedido ya está pagado o entregado,
     * el recibo no se anula suelto: hay que anular el pedido, que además reintegra
     * el inventario y anula todos sus recibos.
     *
     * Debe ejecutarse dentro de la misma transacción que anula el recibo.
     *
     * @param ReciboPago $recibo
     * @return void
     * @throws \RuntimeException Si algún pedido vinculado no está activo.
     */
    public static function revertirAbono(ReciboPago $recibo): void
    {
        if ($recibo->status !== ReciboPago::STATUS_CREADO) {
            return;
        }

        $recibo->loadMissing('pedidoLinks.pedido');

        foreach ($recibo->pedidoLinks as $link) {
            $pedido = $link->pedido;

            if ($pedido->status !== InvPedido::STATUS_ACTIVO) {
                throw new \RuntimeException(
                    "El pedido #{$pedido->id} está {$pedido->status}: anula el pedido desde Inventario → Ventas."
                );
            }

            $nuevoAcumulado = round((float) $pedido->abono_acumulado - (float) $link->monto_abonado, 2);

            $pedido->update([
                'abono_acumulado' => $nuevoAcumulado,
                'saldo'           => round((float) $pedido->valor_total - $nuevoAcumulado, 2),
            ]);
        }
    }

    /**
     * Indica si los medios de pago incluyen transferencia (el recibo queda por aprobar).
     *
     * @param array $mediosPago
     * @return bool
     */
    public static function esTransferencia(array $mediosPago): bool
    {
        return collect($mediosPago)->contains('medio_pago', 'transferencia');
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

        $esTransferencia = static::esTransferencia($datos['medios_pago']);

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
            'turno_caja_id'     => LdTurnoCaja::abiertoDe((int) $datos['cajero_id'])->value('id'),
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
