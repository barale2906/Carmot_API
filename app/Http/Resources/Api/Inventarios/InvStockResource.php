<?php

namespace App\Http\Resources\Api\Inventarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para la representación del stock de un producto en un almacén.
 */
class InvStockResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'almacen_id'           => $this->almacen_id,
            'producto_id'          => $this->producto_id,
            'cantidad_total'       => $this->cantidad_total,
            'cantidad_reservada'   => $this->cantidad_reservada,
            'cantidad_disponible'  => $this->cantidad_disponible,
            'ultimo_movimiento_at' => $this->ultimo_movimiento_at?->format('Y-m-d H:i:s'),
            'updated_at'           => $this->updated_at?->format('Y-m-d H:i:s'),

            'almacen' => $this->when(
                $this->relationLoaded('almacen'),
                fn () => $this->almacen ? [
                    'id'     => $this->almacen->id,
                    'nombre' => $this->almacen->nombre,
                ] : null
            ),

            'producto' => $this->when(
                $this->relationLoaded('producto'),
                fn () => $this->producto ? [
                    'id'     => $this->producto->id,
                    'codigo' => $this->producto->codigo,
                    'nombre' => $this->producto->nombre,
                    'tipo'   => $this->producto->tipo,
                    'punto_reorden' => $this->producto->punto_reorden,
                ] : null
            ),

            'bajo_stock' => $this->when(
                $this->relationLoaded('producto') && $this->producto,
                fn () => $this->producto->punto_reorden > 0
                    && $this->cantidad_disponible <= $this->producto->punto_reorden
            ),
        ];
    }
}
