<?php

namespace App\Services\Inventarios;

use App\Models\Inventarios\InvKitComponente;
use App\Models\Inventarios\InvProducto;

/**
 * InvKitExplosionService — descompone un kit en sus componentes para despacho.
 *
 * Devuelve una lista normalizada de componentes con la información necesaria
 * para que InvEntregaKitService decida cómo procesar cada uno.
 */
class InvKitExplosionService
{
    /**
     * Explota un kit en sus componentes activos.
     *
     * Cada elemento del array resultante incluye:
     *   - kit_componente_id: id del registro en inv_kit_componentes
     *   - componente_tipo:   tipo del producto referenciado ('simple' o 'grupo')
     *   - componente_id:     id del inv_producto del componente
     *   - cantidad:          cantidad_por_kit × cantidad_kits
     *
     * @param int $kitProductoId  id del producto de tipo kit
     * @param int $cantidadKits   cuántos kits se van a entregar
     * @return array<int, array{
     *   kit_componente_id: int,
     *   componente_tipo: string,
     *   componente_id: int,
     *   cantidad: int
     * }>
     */
    public static function explotar(int $kitProductoId, int $cantidadKits): array
    {
        $componentes = InvKitComponente::with('grupoProducto')
            ->where('kit_id', $kitProductoId)
            ->orderBy('orden')
            ->get();

        return $componentes->map(function (InvKitComponente $kc) use ($cantidadKits) {
            return [
                'kit_componente_id' => $kc->id,
                'componente_tipo'   => $kc->grupoProducto->tipo,
                'componente_id'     => $kc->grupo_producto_id,
                'cantidad'          => $kc->cantidad * $cantidadKits,
            ];
        })->all();
    }
}
