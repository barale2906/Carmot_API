<?php

namespace App\Services\Inventarios;

use App\Models\Inventarios\InvProducto;
use App\Models\Inventarios\InvStock;
use Illuminate\Support\Collection;

/**
 * InvDisponibilidadService — consulta de stock previa a la venta.
 *
 * Responde qué se puede entregar en el acto y qué queda pendiente, sin bloquear
 * nunca la venta: el stock insuficiente solo determina si el ítem se despacha de
 * una vez o si genera una entrega pendiente con su necesidad de compra.
 *
 * Para kits calcula el faltante componente a componente, de modo que un kit al
 * que le falta un solo componente siga siendo vendible y entregable parcialmente.
 */
class InvDisponibilidadService
{
    /**
     * Verifica la disponibilidad de un conjunto de ítems en un almacén.
     *
     * @param int   $almacenId
     * @param array $items  [{producto_id, cantidad, entrega_completa?: bool, variantes?: [{kit_componente_id, producto_entregado_id}]}]
     * @return array{
     *   almacen_id: int,
     *   entregable_completo: bool,
     *   requiere_seleccion_variante: bool,
     *   items: array<int, array<string, mixed>>
     * }
     */
    public static function verificar(int $almacenId, array $items): array
    {
        $productos = InvProducto::whereIn('id', collect($items)->pluck('producto_id'))
            ->get()
            ->keyBy('id');

        $resultado = [];

        foreach ($items as $indice => $item) {
            $producto = $productos->get($item['producto_id']);

            if (! $producto) {
                continue;
            }

            $analisis = $producto->tipo === 'kit'
                ? static::analizarKit($indice, $producto, (int) $item['cantidad'], $almacenId, $item['variantes'] ?? [])
                : static::analizarSimple($indice, $producto, (int) $item['cantidad'], $almacenId);

            $resultado[] = static::aplicarEntregaCompleta(
                $analisis,
                (bool) ($item['entrega_completa'] ?? false)
            );
        }

        return [
            'almacen_id'                  => $almacenId,
            'entregable_completo'         => collect($resultado)->every(fn ($i) => $i['entregable_ahora']),
            'requiere_seleccion_variante' => collect($resultado)->contains('requiere_variante', true),
            'items'                       => $resultado,
        ];
    }

    /**
     * Analiza la disponibilidad de un producto simple (o de una variante concreta).
     *
     * @param int         $indice
     * @param InvProducto $producto
     * @param int         $cantidad
     * @param int         $almacenId
     * @return array<string, mixed>
     */
    private static function analizarSimple(int $indice, InvProducto $producto, int $cantidad, int $almacenId): array
    {
        // Un producto de tipo grupo no tiene stock propio: se vende eligiendo una variante.
        if ($producto->tipo === 'grupo') {
            return [
                'item_index'          => $indice,
                'producto_id'         => $producto->id,
                'codigo'              => $producto->codigo,
                'nombre'              => $producto->nombre,
                'tipo'                => $producto->tipo,
                'cantidad_solicitada' => $cantidad,
                'vendible'            => true,
                'requiere_variante'   => true,
                'stock_disponible'    => null,
                'cantidad_entregable' => 0,
                'faltante'            => $cantidad,
                'entregable_ahora'    => false,
                'variantes'           => static::variantesConStock($producto->id, $almacenId),
                'componentes'         => [],
            ];
        }

        $stock       = static::stockDe($almacenId, [$producto->id])->get($producto->id, 0);
        $entregables = min($stock, $cantidad);

        return [
            'item_index'          => $indice,
            'producto_id'         => $producto->id,
            'codigo'              => $producto->codigo,
            'nombre'              => $producto->nombre,
            'tipo'                => $producto->tipo,
            'cantidad_solicitada' => $cantidad,
            'vendible'            => true,
            'requiere_variante'   => false,
            'stock_disponible'    => $stock,
            'cantidad_entregable' => $entregables,
            'faltante'            => $cantidad - $entregables,
            'entregable_ahora'    => $entregables === $cantidad,
            'variantes'           => [],
            'componentes'         => [],
        ];
    }

    /**
     * Analiza la disponibilidad de un kit componente a componente.
     *
     * El kit siempre es vendible: `entregable_ahora` solo indica si alcanza el
     * stock para despacharlo completo en el momento de la venta.
     *
     * @param int         $indice
     * @param InvProducto $producto
     * @param int         $cantidad    Cantidad de kits solicitada
     * @param int         $almacenId
     * @param array       $variantes   Variantes preseleccionadas [{kit_componente_id, producto_entregado_id}]
     * @return array<string, mixed>
     */
    private static function analizarKit(
        int $indice,
        InvProducto $producto,
        int $cantidad,
        int $almacenId,
        array $variantes
    ): array {
        $componentes  = InvKitExplosionService::explotar($producto->id, $cantidad);
        $seleccionadas = collect($variantes)->keyBy('kit_componente_id');

        // Resolver de antemano el producto concreto de cada componente para consultar el stock en un solo query.
        $productosConsultados = collect($componentes)
            ->map(fn ($c) => static::resolverProductoComponente($c, $seleccionadas))
            ->filter()
            ->unique()
            ->values();

        $stocks = static::stockDe($almacenId, $productosConsultados->all());

        // Nombres de los componentes definidos en el kit (grupo o simple), en un solo query.
        $nombresComponente = InvProducto::whereIn('id', collect($componentes)->pluck('componente_id'))
            ->pluck('nombre', 'id');

        $detalle           = [];
        $kitsEntregables   = $cantidad;
        $requiereVariante  = false;

        foreach ($componentes as $comp) {
            $productoEntregadoId = static::resolverProductoComponente($comp, $seleccionadas);
            $esGrupoSinElegir    = $comp['componente_tipo'] === 'grupo' && ! $productoEntregadoId;

            if ($esGrupoSinElegir) {
                $requiereVariante = true;
            }

            $stock       = $productoEntregadoId ? $stocks->get($productoEntregadoId, 0) : 0;
            $entregables = min($stock, $comp['cantidad']);

            // Cuántos kits completos cubre este componente por sí solo.
            $kitsEntregables = min(
                $kitsEntregables,
                (int) floor($stock / max(1, $comp['cantidad_unitaria']))
            );

            $detalle[] = [
                'kit_componente_id'     => $comp['kit_componente_id'],
                'componente_tipo'       => $comp['componente_tipo'],
                'componente_id'         => $comp['componente_id'],
                'componente_nombre'     => $nombresComponente->get($comp['componente_id']),
                'requiere_variante'     => $comp['componente_tipo'] === 'grupo',
                'producto_entregado_id' => $productoEntregadoId,
                'cantidad_unitaria'     => $comp['cantidad_unitaria'],
                'cantidad_requerida'    => $comp['cantidad'],
                'stock_disponible'      => $stock,
                'cantidad_entregable'   => $entregables,
                'faltante'              => $comp['cantidad'] - $entregables,
                'entregable_ahora'      => $entregables === $comp['cantidad'],
                'variantes'             => $comp['componente_tipo'] === 'grupo'
                    ? static::variantesConStock($comp['componente_id'], $almacenId)
                    : [],
            ];
        }

        $kitsEntregables = max(0, $kitsEntregables);

        return [
            'item_index'          => $indice,
            'producto_id'         => $producto->id,
            'codigo'              => $producto->codigo,
            'nombre'              => $producto->nombre,
            'tipo'                => $producto->tipo,
            'cantidad_solicitada' => $cantidad,
            'vendible'            => true,
            'requiere_variante'   => $requiereVariante,
            'stock_disponible'    => null,
            'cantidad_entregable' => $kitsEntregables,
            'faltante'            => $cantidad - $kitsEntregables,
            'entregable_ahora'    => $kitsEntregables === $cantidad && ! $requiereVariante,
            'variantes'           => [],
            'componentes'         => $detalle,
        ];
    }

    /**
     * Ajusta el análisis cuando el comprador exige recibir el ítem completo.
     *
     * Con esa marca no hay entrega parcial posible: si falta aunque sea una unidad,
     * no se descarga nada. El preview debe mostrar exactamente lo que ocurrirá.
     *
     * @param array $analisis
     * @param bool  $entregaCompleta
     * @return array
     */
    private static function aplicarEntregaCompleta(array $analisis, bool $entregaCompleta): array
    {
        $analisis['entrega_completa'] = $entregaCompleta;

        if ($entregaCompleta && ! $analisis['entregable_ahora']) {
            $analisis['cantidad_entregable'] = 0;
            $analisis['faltante']            = $analisis['cantidad_solicitada'];
        }

        return $analisis;
    }

    /**
     * Determina el producto concreto que cubre un componente del kit.
     * Para componentes de tipo grupo depende de la variante elegida por el cajero.
     *
     * @param array      $componente
     * @param Collection $seleccionadas
     * @return int|null
     */
    private static function resolverProductoComponente(array $componente, Collection $seleccionadas): ?int
    {
        if ($componente['componente_tipo'] !== 'grupo') {
            return $componente['componente_id'];
        }

        $variante = $seleccionadas->get($componente['kit_componente_id']);

        return isset($variante['producto_entregado_id'])
            ? (int) $variante['producto_entregado_id']
            : null;
    }

    /**
     * Lista las variantes activas de un producto de tipo grupo con su stock en el almacén.
     *
     * @param int $grupoProductoId
     * @param int $almacenId
     * @return array<int, array<string, mixed>>
     */
    private static function variantesConStock(int $grupoProductoId, int $almacenId): array
    {
        $variantes = InvProducto::where('producto_padre_id', $grupoProductoId)
            ->where('status', 1)
            ->orderBy('nombre')
            ->get();

        $stocks = static::stockDe($almacenId, $variantes->pluck('id')->all());

        return $variantes->map(fn (InvProducto $v) => [
            'id'               => $v->id,
            'codigo'           => $v->codigo,
            'nombre'           => $v->nombre,
            'stock_disponible' => $stocks->get($v->id, 0),
        ])->all();
    }

    /**
     * Consulta el stock disponible de varios productos en un almacén.
     *
     * @param int             $almacenId
     * @param array<int, int> $productoIds
     * @return Collection<int, int>  producto_id => cantidad_disponible
     */
    private static function stockDe(int $almacenId, array $productoIds): Collection
    {
        if (empty($productoIds)) {
            return collect();
        }

        return InvStock::where('almacen_id', $almacenId)
            ->whereIn('producto_id', $productoIds)
            ->pluck('cantidad_disponible', 'producto_id')
            ->map(fn ($c) => (int) $c);
    }
}
