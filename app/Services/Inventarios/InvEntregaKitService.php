<?php

namespace App\Services\Inventarios;

use App\Models\Inventarios\InvDocumentoMovimiento;
use App\Models\Inventarios\InvEntregaKit;
use App\Models\Inventarios\InvEntregaKitComponente;
use App\Models\Inventarios\InvMovimiento;
use App\Models\Inventarios\InvPedidoItem;
use App\Models\Inventarios\InvStock;

/**
 * InvEntregaKitService — gestiona la entrega de un ítem de tipo kit.
 *
 * Un kit se entrega componente a componente, no como un todo: el cajero puede
 * despachar lo que hay en bodega y dejar el resto pendiente. Para cada componente:
 * - Si es simple con stock: descuenta stock y lo marca entregado (o parcial).
 * - Si es grupo: usa la variante elegida por el cajero.
 * - Sin stock o sin variante elegida: crea la necesidad de compra y lo deja pendiente.
 *
 * Que a un componente le falte stock nunca impide vender ni entregar el resto del kit.
 *
 * Excepción: si el ítem está marcado con `entrega_completa`, el comprador exigió
 * llevarse el kit armado. Mientras no alcance el stock de todos sus componentes no
 * se descarga ninguno, salvo que el cajero fuerce la entrega parcial.
 *
 * Debe llamarse dentro de DB::transaction().
 */
class InvEntregaKitService
{
    /**
     * Inicia la entrega de un kit procesando todos sus componentes.
     *
     * Se usa en el despacho automático al quedar el pedido pagado: entrega todo
     * lo que el stock permita y deja pendiente lo demás.
     *
     * @param InvPedidoItem $item                    Ítem del pedido (tipo=kit)
     * @param int           $cajeroId
     * @param array         $variantesSeleccionadas  [{kit_componente_id, producto_entregado_id}]
     *                                               Solo para componentes de tipo=grupo.
     * @param bool          $forzarParcial           Ignora la marca entrega_completa del ítem
     * @return InvEntregaKit
     */
    public static function iniciarEntrega(
        InvPedidoItem $item,
        int $cajeroId,
        array $variantesSeleccionadas = [],
        bool $forzarParcial = false
    ): InvEntregaKit {
        $entregaKit = static::obtenerOCrearEntrega($item, $cajeroId);

        if ($entregaKit->status === InvEntregaKit::STATUS_COMPLETO) {
            return $entregaKit;
        }

        $variantes   = collect($variantesSeleccionadas)->keyBy('kit_componente_id');
        $componentes = InvKitExplosionService::explotar($item->producto_id, $item->cantidad);

        // El comprador pidió el kit completo: si falta stock de algún componente,
        // no se descarga ninguno y el kit entero queda pendiente.
        if (! static::puedeDespachar($item, $entregaKit, $componentes, $variantes, $forzarParcial)) {
            static::registrarPendientes($entregaKit, $item, $componentes, $variantes);
            $entregaKit->recalcularStatus();

            return $entregaKit->fresh('componentes');
        }

        $documento = null;

        foreach ($componentes as $comp) {
            static::procesarComponente($entregaKit, $item, $comp, $variantes, $cajeroId, null, $documento);
        }

        $entregaKit->recalcularStatus();

        return $entregaKit->fresh('componentes');
    }

    /**
     * Entrega únicamente los componentes indicados por el cajero (entrega parcial dirigida).
     *
     * A diferencia de iniciarEntrega(), no recorre todo el kit: procesa solo las
     * líneas listadas, en la cantidad pedida. Permite despachar un kit por partes
     * a medida que el estudiante retira o que llega el stock faltante.
     *
     * @param InvEntregaKit $entregaKit
     * @param int           $cajeroId
     * @param array         $seleccion      [{kit_componente_id, producto_entregado_id?, cantidad?}]
     * @param bool          $forzarParcial  Ignora la marca entrega_completa del ítem
     * @return InvEntregaKit
     */
    public static function entregarComponentes(
        InvEntregaKit $entregaKit,
        int $cajeroId,
        array $seleccion,
        bool $forzarParcial = false
    ): InvEntregaKit {
        $item = $entregaKit->pedidoItem()->with('pedido')->firstOrFail();

        $componentes = collect(InvKitExplosionService::explotar($item->producto_id, $item->cantidad))
            ->keyBy('kit_componente_id');

        $variantes = collect($seleccion)->keyBy('kit_componente_id');

        // Un kit marcado como entrega completa no admite despachos por partes.
        if (! static::puedeDespachar($item, $entregaKit, $componentes->values()->all(), $variantes, $forzarParcial)) {
            return $entregaKit->fresh('componentes.productoEntregado');
        }

        $documento = null;

        foreach ($seleccion as $sel) {
            $comp = $componentes->get($sel['kit_componente_id']);

            if (! $comp) {
                continue;
            }

            static::procesarComponente(
                $entregaKit,
                $item,
                $comp,
                $variantes,
                $cajeroId,
                isset($sel['cantidad']) ? (int) $sel['cantidad'] : null,
                $documento
            );
        }

        $entregaKit->recalcularStatus();

        return $entregaKit->fresh('componentes.productoEntregado');
    }

    /**
     * Crea la entrega del kit y sus líneas de componente en pendiente, sin descargar stock.
     *
     * Se llama al quedar pagado el pedido para TODOS los ítems de tipo kit, incluso
     * los excluidos del despacho inmediato, para que la pantalla de Entregas
     * Pendientes siempre disponga del id de la entrega y de sus componentes.
     *
     * @param InvPedidoItem $item
     * @param int           $cajeroId
     * @param array         $variantesSeleccionadas
     * @return InvEntregaKit
     */
    public static function prepararPendiente(
        InvPedidoItem $item,
        int $cajeroId,
        array $variantesSeleccionadas = []
    ): InvEntregaKit {
        $entregaKit = static::obtenerOCrearEntrega($item, $cajeroId);

        if ($entregaKit->status === InvEntregaKit::STATUS_COMPLETO) {
            return $entregaKit;
        }

        static::registrarPendientes(
            $entregaKit,
            $item,
            InvKitExplosionService::explotar($item->producto_id, $item->cantidad),
            collect($variantesSeleccionadas)->keyBy('kit_componente_id')
        );

        $entregaKit->recalcularStatus();

        return $entregaKit->fresh('componentes');
    }

    /**
     * Obtiene la cabecera de entrega del kit o la crea si es el primer despacho.
     *
     * @param InvPedidoItem $item
     * @param int           $cajeroId
     * @return InvEntregaKit
     */
    private static function obtenerOCrearEntrega(InvPedidoItem $item, int $cajeroId): InvEntregaKit
    {
        return InvEntregaKit::firstOrCreate(
            ['pedido_item_id' => $item->id],
            [
                'kit_producto_id' => $item->producto_id,
                'cantidad_kits'   => $item->cantidad,
                'status'          => InvEntregaKit::STATUS_PENDIENTE,
                'user_id'         => $cajeroId,
            ]
        );
    }

    /**
     * Determina si el kit puede despacharse en este momento.
     *
     * Solo restringe cuando el ítem exige entrega completa: en ese caso todos los
     * componentes pendientes deben tener stock suficiente y variante resuelta. Sin
     * esa marca el despacho siempre procede y entrega lo que haya.
     *
     * @param InvPedidoItem                  $item
     * @param InvEntregaKit                  $entregaKit
     * @param array                          $componentes    Componentes explotados del kit
     * @param \Illuminate\Support\Collection $variantes      Variantes elegidas por kit_componente_id
     * @param bool                           $forzarParcial
     * @return bool
     */
    private static function puedeDespachar(
        InvPedidoItem $item,
        InvEntregaKit $entregaKit,
        array $componentes,
        $variantes,
        bool $forzarParcial
    ): bool {
        if (! $item->entrega_completa || $forzarParcial) {
            return true;
        }

        $almacenId = $item->pedido->almacen_id;

        $lineas = InvEntregaKitComponente::where('entrega_kit_id', $entregaKit->id)
            ->get()
            ->keyBy('kit_componente_id');

        foreach ($componentes as $comp) {
            $linea     = $lineas->get($comp['kit_componente_id']);
            $entregado = $linea->cantidad_entregada ?? 0;
            $pendiente = $comp['cantidad'] - $entregado;

            if ($pendiente <= 0) {
                continue;
            }

            if ($comp['componente_tipo'] === 'grupo') {
                $seleccion        = $variantes->get($comp['kit_componente_id']);
                $productoEntregId = $seleccion['producto_entregado_id'] ?? $linea?->producto_entregado_id;
            } else {
                $productoEntregId = $comp['componente_id'];
            }

            // Sin variante resuelta no hay stock que verificar: el kit no está listo.
            if (! $productoEntregId) {
                return false;
            }

            $stock = InvStock::where('almacen_id', $almacenId)
                ->where('producto_id', $productoEntregId)
                ->value('cantidad_disponible') ?? 0;

            if ($stock < $pendiente) {
                return false;
            }
        }

        return true;
    }

    /**
     * Crea las líneas de componente y sus necesidades de compra sin descargar stock.
     *
     * Se usa cuando un kit de entrega completa todavía no puede despacharse: el
     * pedido debe reflejar qué falta y disparar la necesidad de compra igual.
     *
     * @param InvEntregaKit                  $entregaKit
     * @param InvPedidoItem                  $item
     * @param array                          $componentes
     * @param \Illuminate\Support\Collection $variantes
     * @return void
     */
    private static function registrarPendientes(
        InvEntregaKit $entregaKit,
        InvPedidoItem $item,
        array $componentes,
        $variantes
    ): void {
        $pedido = $item->pedido;

        foreach ($componentes as $comp) {
            $linea = InvEntregaKitComponente::firstOrCreate(
                [
                    'entrega_kit_id'    => $entregaKit->id,
                    'kit_componente_id' => $comp['kit_componente_id'],
                ],
                [
                    'producto_entregado_id' => null,
                    'cantidad_solicitada'   => $comp['cantidad'],
                    'cantidad_entregada'    => 0,
                    'status'                => InvEntregaKitComponente::STATUS_PENDIENTE,
                ]
            );

            if ($linea->status === InvEntregaKitComponente::STATUS_ENTREGADO) {
                continue;
            }

            if ($comp['componente_tipo'] === 'grupo') {
                $seleccion        = $variantes->get($comp['kit_componente_id']);
                $productoEntregId = $seleccion['producto_entregado_id'] ?? $linea->producto_entregado_id;
            } else {
                $productoEntregId = $comp['componente_id'];
            }

            if ($productoEntregId && ! $linea->producto_entregado_id) {
                $linea->update(['producto_entregado_id' => $productoEntregId]);
            }

            $faltante = $comp['cantidad'] - $linea->cantidad_entregada;

            if ($faltante > 0) {
                InvNecesidadService::registrarSiFaltaStock(
                    $productoEntregId ?? $comp['componente_id'],
                    $faltante,
                    $pedido->almacen_id,
                    $pedido->estudiante_id,
                    InvEntregaKitComponente::class,
                    $linea->id
                );
            }
        }
    }

    /**
     * Procesa un componente del kit: descuenta el stock disponible y deja el faltante pendiente.
     *
     * El documento de salida se crea de forma perezosa y se comparte entre todos
     * los componentes del mismo despacho, por lo que se recibe por referencia.
     *
     * @param InvEntregaKit                 $entregaKit
     * @param InvPedidoItem                 $item
     * @param array                         $comp       Componente explotado
     * @param \Illuminate\Support\Collection $variantes  Variantes elegidas, indexadas por kit_componente_id
     * @param int                           $cajeroId
     * @param int|null                      $cantidad   Cantidad a entregar ahora; null entrega todo lo posible
     * @param InvDocumentoMovimiento|null   $documento  Documento de salida compartido
     * @return void
     */
    private static function procesarComponente(
        InvEntregaKit $entregaKit,
        InvPedidoItem $item,
        array $comp,
        $variantes,
        int $cajeroId,
        ?int $cantidad,
        ?InvDocumentoMovimiento &$documento
    ): void {
        $pedido       = $item->pedido;
        $almacenId    = $pedido->almacen_id;
        $estudianteId = $pedido->estudiante_id;

        $linea = InvEntregaKitComponente::firstOrCreate(
            [
                'entrega_kit_id'    => $entregaKit->id,
                'kit_componente_id' => $comp['kit_componente_id'],
            ],
            [
                'producto_entregado_id' => null,
                'cantidad_solicitada'   => $comp['cantidad'],
                'cantidad_entregada'    => 0,
                'status'                => InvEntregaKitComponente::STATUS_PENDIENTE,
            ]
        );

        if ($linea->status === InvEntregaKitComponente::STATUS_ENTREGADO) {
            return;
        }

        // Producto concreto que cubre el componente: la variante elegida si es grupo,
        // el propio componente si es simple. Se conserva la variante ya registrada.
        if ($comp['componente_tipo'] === 'grupo') {
            $seleccion        = $variantes->get($comp['kit_componente_id']);
            $productoEntregId = $seleccion['producto_entregado_id'] ?? $linea->producto_entregado_id;
        } else {
            $productoEntregId = $comp['componente_id'];
        }

        if (! $productoEntregId) {
            // Sin variante elegida no se puede descontar stock: queda pendiente.
            InvNecesidadService::generarSiNoExiste(
                $comp['componente_id'],
                $comp['cantidad'],
                $almacenId,
                $estudianteId,
                InvEntregaKitComponente::class,
                $linea->id
            );

            return;
        }

        $linea->update(['producto_entregado_id' => $productoEntregId]);

        $pendiente = $comp['cantidad'] - $linea->cantidad_entregada;

        if ($pendiente <= 0) {
            $linea->update(['status' => InvEntregaKitComponente::STATUS_ENTREGADO]);

            return;
        }

        $solicitado = $cantidad !== null ? min($cantidad, $pendiente) : $pendiente;

        $stockDisp = InvStock::where('almacen_id', $almacenId)
            ->where('producto_id', $productoEntregId)
            ->value('cantidad_disponible') ?? 0;

        $aEntregar = (int) min($stockDisp, $solicitado);

        if ($aEntregar > 0) {
            if (! $documento) {
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
                'cantidad'        => $aEntregar,
                'referencia_type' => InvEntregaKitComponente::class,
                'referencia_id'   => $linea->id,
                'user_id'         => $cajeroId,
            ]);

            InvStockService::decrementar($almacenId, $productoEntregId, $aEntregar);

            $totalEntregado = $linea->cantidad_entregada + $aEntregar;

            $linea->update([
                'cantidad_entregada' => $totalEntregado,
                'status'             => $totalEntregado >= $comp['cantidad']
                    ? InvEntregaKitComponente::STATUS_ENTREGADO
                    : InvEntregaKitComponente::STATUS_PARCIAL,
                'fecha_entrega'      => now(),
                'user_id'            => $cajeroId,
            ]);
        }

        $faltante = $comp['cantidad'] - $linea->fresh()->cantidad_entregada;

        if ($faltante > 0) {
            InvNecesidadService::registrarSiFaltaStock(
                $productoEntregId,
                $faltante,
                $almacenId,
                $estudianteId,
                InvEntregaKitComponente::class,
                $linea->id
            );
        }
    }
}
