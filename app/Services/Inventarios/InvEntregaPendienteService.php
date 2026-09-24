<?php

namespace App\Services\Inventarios;

use App\Models\Inventarios\InvEntregaKitComponente;
use App\Models\Inventarios\InvEntregaSimple;
use App\Models\Inventarios\InvKitComponente;
use App\Models\Inventarios\InvProducto;
use App\Models\Inventarios\InvStock;
use Illuminate\Support\Collection;

/**
 * InvEntregaPendienteService — completa los datos que necesita la pantalla de
 * Entregas Pendientes y que no viven en las tablas de entrega.
 *
 * El cajero necesita saber qué componente es ("Camisa"), cuánto stock hay en el
 * almacén del pedido y, si el componente es un grupo, qué variantes puede elegir.
 * Nada de eso está en `inv_entregas_kit_componente`, y resolverlo desde el Resource
 * dispararía un query por línea.
 *
 * Los valores se adjuntan como atributos transitorios sobre los modelos ya cargados
 * — nunca se persisten — y los Resources los publican solo cuando están presentes,
 * de modo que el resto de endpoints conserva su respuesta actual.
 */
class InvEntregaPendienteService
{
    /**
     * Enriquece los pedidos de la pantalla de entregas pendientes.
     *
     * @param Collection $pedidos  Pedidos con items.entregaSimple e items.entregaKit.componentes cargados
     * @return void
     */
    public static function enriquecer(Collection $pedidos): void
    {
        if ($pedidos->isEmpty()) {
            return;
        }

        $lineasKit = $pedidos
            ->flatMap(fn ($pedido) => $pedido->items)
            ->map(fn ($item) => $item->entregaKit)
            ->filter()
            ->flatMap(fn ($entregaKit) => $entregaKit->componentes)
            ->values();

        $definiciones = static::definicionesDeComponente($lineasKit);
        $variantes    = static::variantesPorGrupo($definiciones, $pedidos);
        $stocks       = static::stocksNecesarios($pedidos, $definiciones, $variantes);

        foreach ($pedidos as $pedido) {
            $almacenId = $pedido->almacen_id;

            foreach ($pedido->items as $item) {
                if ($item->entregaSimple) {
                    static::enriquecerSimple($item, $almacenId, $stocks);
                }

                if ($item->entregaKit) {
                    foreach ($item->entregaKit->componentes as $linea) {
                        static::enriquecerComponente($linea, $almacenId, $definiciones, $variantes, $stocks);
                    }
                }
            }
        }
    }

    /**
     * Adjunta cantidad pendiente y stock a la entrega de un ítem simple.
     *
     * @param \App\Models\Inventarios\InvPedidoItem $item
     * @param int                                   $almacenId
     * @param Collection                            $stocks
     * @return void
     */
    private static function enriquecerSimple($item, int $almacenId, Collection $stocks): void
    {
        $entrega = $item->entregaSimple;

        $entrega->setAttribute(
            'cantidad_pendiente',
            max(0, $item->cantidad - $entrega->cantidad_entregada)
        );

        $entrega->setAttribute(
            'stock_disponible',
            $stocks->get(static::clave($almacenId, $entrega->producto_id), 0)
        );
    }

    /**
     * Adjunta nombre, tipo, stock y variantes a una línea de componente de kit.
     *
     * @param InvEntregaKitComponente $linea
     * @param int                     $almacenId
     * @param Collection              $definiciones
     * @param Collection              $variantes
     * @param Collection              $stocks
     * @return void
     */
    private static function enriquecerComponente(
        InvEntregaKitComponente $linea,
        int $almacenId,
        Collection $definiciones,
        Collection $variantes,
        Collection $stocks
    ): void {
        $definicion = $definiciones->get($linea->kit_componente_id);

        $linea->setAttribute('componente_nombre', $definicion['nombre'] ?? null);
        $linea->setAttribute('componente_tipo', $definicion['tipo'] ?? null);

        // Sin variante elegida no hay producto concreto del cual informar stock.
        $linea->setAttribute(
            'stock_disponible',
            $linea->producto_entregado_id
                ? $stocks->get(static::clave($almacenId, $linea->producto_entregado_id), 0)
                : null
        );

        $opciones = [];

        if (($definicion['tipo'] ?? null) === 'grupo') {
            $opciones = collect($variantes->get($definicion['producto_id'], []))
                ->map(fn (array $v) => [
                    'id'               => $v['id'],
                    'codigo'           => $v['codigo'],
                    'nombre'           => $v['nombre'],
                    'stock_disponible' => $stocks->get(static::clave($almacenId, $v['id']), 0),
                ])
                ->values()
                ->all();
        }

        $linea->setAttribute('variantes', $opciones);
    }

    /**
     * Resuelve nombre, tipo y producto de cada definición de componente del kit.
     *
     * @param Collection $lineasKit
     * @return Collection  kit_componente_id => {nombre, tipo, producto_id}
     */
    private static function definicionesDeComponente(Collection $lineasKit): Collection
    {
        if ($lineasKit->isEmpty()) {
            return collect();
        }

        return InvKitComponente::with('grupoProducto')
            ->whereIn('id', $lineasKit->pluck('kit_componente_id')->unique())
            ->get()
            ->mapWithKeys(fn (InvKitComponente $kc) => [
                $kc->id => [
                    'nombre'      => $kc->grupoProducto?->nombre,
                    'tipo'        => $kc->grupoProducto?->tipo,
                    'producto_id' => $kc->grupo_producto_id,
                ],
            ]);
    }

    /**
     * Lista las variantes activas de cada componente de tipo grupo.
     *
     * @param Collection $definiciones
     * @param Collection $pedidos
     * @return Collection  grupo_producto_id => [{id, codigo, nombre}]
     */
    private static function variantesPorGrupo(Collection $definiciones, Collection $pedidos): Collection
    {
        $gruposIds = $definiciones
            ->filter(fn (array $d) => ($d['tipo'] ?? null) === 'grupo')
            ->pluck('producto_id')
            ->unique();

        if ($gruposIds->isEmpty()) {
            return collect();
        }

        return InvProducto::whereIn('producto_padre_id', $gruposIds)
            ->where('status', 1)
            ->orderBy('nombre')
            ->get()
            ->groupBy('producto_padre_id')
            ->map(fn ($variantes) => $variantes->map(fn (InvProducto $v) => [
                'id'     => $v->id,
                'codigo' => $v->codigo,
                'nombre' => $v->nombre,
            ])->all());
    }

    /**
     * Consulta en un solo query el stock de todos los productos involucrados.
     *
     * @param Collection $pedidos
     * @param Collection $definiciones
     * @param Collection $variantes
     * @return Collection  "almacenId:productoId" => cantidad_disponible
     */
    private static function stocksNecesarios(
        Collection $pedidos,
        Collection $definiciones,
        Collection $variantes
    ): Collection {
        $almacenes = $pedidos->pluck('almacen_id')->unique()->values();

        $productos = collect()
            ->merge($definiciones->pluck('producto_id'))
            ->merge($variantes->flatten(1)->pluck('id'))
            ->merge(
                $pedidos->flatMap(fn ($pedido) => $pedido->items)
                    ->flatMap(fn ($item) => [
                        $item->entregaSimple?->producto_id,
                        ...($item->entregaKit?->componentes->pluck('producto_entregado_id')->all() ?? []),
                    ])
            )
            ->filter()
            ->unique()
            ->values();

        if ($almacenes->isEmpty() || $productos->isEmpty()) {
            return collect();
        }

        return InvStock::whereIn('almacen_id', $almacenes)
            ->whereIn('producto_id', $productos)
            ->get(['almacen_id', 'producto_id', 'cantidad_disponible'])
            ->mapWithKeys(fn (InvStock $s) => [
                static::clave($s->almacen_id, $s->producto_id) => (int) $s->cantidad_disponible,
            ]);
    }

    /**
     * Adjunta el pedido_id a cada necesidad de compra resolviéndolo desde su entregable.
     *
     * La necesidad apunta al registro de entrega (simple o componente de kit), no al
     * pedido; la pantalla de necesidades sí necesita mostrarlo.
     *
     * @param Collection $necesidades
     * @return void
     */
    public static function asignarPedidoId(Collection $necesidades): void
    {
        if ($necesidades->isEmpty()) {
            return;
        }

        $porTipo = $necesidades->groupBy('entregable_type');

        $pedidoPorEntregable = collect();

        $idsSimple = $porTipo->get(InvEntregaSimple::class, collect())->pluck('entregable_id')->unique();

        if ($idsSimple->isNotEmpty()) {
            InvEntregaSimple::with('pedidoItem:id,pedido_id')
                ->whereIn('id', $idsSimple)
                ->get()
                ->each(function (InvEntregaSimple $entrega) use ($pedidoPorEntregable) {
                    $pedidoPorEntregable->put(
                        InvEntregaSimple::class . ':' . $entrega->id,
                        $entrega->pedidoItem?->pedido_id
                    );
                });
        }

        $idsComponente = $porTipo->get(InvEntregaKitComponente::class, collect())->pluck('entregable_id')->unique();

        if ($idsComponente->isNotEmpty()) {
            InvEntregaKitComponente::with('entregaKit.pedidoItem:id,pedido_id')
                ->whereIn('id', $idsComponente)
                ->get()
                ->each(function (InvEntregaKitComponente $linea) use ($pedidoPorEntregable) {
                    $pedidoPorEntregable->put(
                        InvEntregaKitComponente::class . ':' . $linea->id,
                        $linea->entregaKit?->pedidoItem?->pedido_id
                    );
                });
        }

        foreach ($necesidades as $necesidad) {
            $necesidad->setAttribute(
                'pedido_id',
                $pedidoPorEntregable->get($necesidad->entregable_type . ':' . $necesidad->entregable_id)
            );
        }
    }

    /**
     * Construye la clave compuesta usada para indexar el stock.
     *
     * @param int $almacenId
     * @param int $productoId
     * @return string
     */
    private static function clave(int $almacenId, int $productoId): string
    {
        return $almacenId . ':' . $productoId;
    }
}
