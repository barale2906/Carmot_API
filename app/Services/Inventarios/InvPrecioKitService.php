<?php

namespace App\Services\Inventarios;

use App\Models\Inventarios\InvKitComponente;
use App\Models\Inventarios\InvPrecioProducto;
use App\Models\Inventarios\InvProducto;

/**
 * InvPrecioKitService — referencia de precio de un kit a partir de sus componentes.
 *
 * Calcula, para una lista de precios, cuánto suman los componentes del kit
 * (precio unitario × cantidad armada). Un componente de tipo grupo toma el precio
 * de sus variantes activas: si las variantes tienen precios distintos se reporta el
 * rango (mínimo y máximo), porque el estudiante elige la variante al recibir el kit.
 */
class InvPrecioKitService
{
    /**
     * Desglose de precios de los componentes de un kit en una lista.
     *
     * Los componentes sin ningún precio en la lista no suman al total y se marcan
     * con `completo = false`, al igual que los grupos con variantes sin precio.
     *
     * @param int         $listaPrecioId
     * @param InvProducto $kit
     * @return array{
     *   kit: array{id: int, codigo: string, nombre: string},
     *   lista_precio_id: int,
     *   precio_actual: float|null,
     *   componentes: array<int, array<string, mixed>>,
     *   total_min: float,
     *   total_max: float,
     *   completo: bool
     * }
     */
    public static function desglose(int $listaPrecioId, InvProducto $kit): array
    {
        $componentes = $kit->kitComponentes()
            ->with('grupoProducto')
            ->orderBy('orden')
            ->get();

        // Productos que reciben precio por componente (grupo → variantes activas)
        $receptores = $componentes->mapWithKeys(fn (InvKitComponente $c) => [
            $c->id => $c->grupoProducto->idsReceptoresDePrecio(),
        ]);

        $idsConsultados = $receptores->flatten()->push($kit->id)->unique();
        $productos      = InvProducto::whereIn('id', $idsConsultados)->pluck('nombre', 'id');
        $precios        = InvPrecioProducto::where('lista_precio_id', $listaPrecioId)
            ->whereIn('producto_id', $idsConsultados)
            ->pluck('precio', 'producto_id')
            ->map(fn ($precio) => (float) $precio);

        $detalle = $componentes->map(function (InvKitComponente $c) use ($receptores, $productos, $precios) {
            $variantes = $receptores[$c->id]->map(fn (int $id) => [
                'id'     => $id,
                'nombre' => $productos[$id] ?? null,
                'precio' => $precios[$id] ?? null,
            ])->values();

            $conPrecio = $variantes->pluck('precio')->filter(fn ($p) => $p !== null);
            $min       = $conPrecio->min();
            $max       = $conPrecio->max();

            return [
                'kit_componente_id'   => $c->id,
                'producto_id'         => $c->grupo_producto_id,
                'nombre'              => $c->grupoProducto->nombre,
                'tipo'                => $c->grupoProducto->tipo,
                'cantidad'            => $c->cantidad,
                'precio_unitario_min' => $min,
                'precio_unitario_max' => $max,
                'subtotal_min'        => $min !== null ? $min * $c->cantidad : null,
                'subtotal_max'        => $max !== null ? $max * $c->cantidad : null,
                'completo'            => $variantes->isNotEmpty() && $conPrecio->count() === $variantes->count(),
                'variantes'           => $c->grupoProducto->tipo === 'grupo' ? $variantes->all() : [],
            ];
        });

        return [
            'kit'             => ['id' => $kit->id, 'codigo' => $kit->codigo, 'nombre' => $kit->nombre],
            'lista_precio_id' => $listaPrecioId,
            'precio_actual'   => $precios[$kit->id] ?? null,
            'componentes'     => $detalle->values()->all(),
            'total_min'       => (float) $detalle->sum('subtotal_min'),
            'total_max'       => (float) $detalle->sum('subtotal_max'),
            'completo'        => $detalle->isNotEmpty() && $detalle->every(fn ($c) => $c['completo']),
        ];
    }
}
