<?php

namespace App\Http\Resources\Api\Inventarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para la representación de una línea de movimiento de inventario.
 */
class InvMovimientoResource extends JsonResource
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
            'id'                 => $this->id,
            'documento_id'       => $this->documento_id,
            'almacen_id'         => $this->almacen_id,
            'almacen_destino_id' => $this->almacen_destino_id,
            'producto_id'        => $this->producto_id,
            'tipo_movimiento'    => $this->tipo_movimiento,
            'cantidad'           => $this->cantidad,
            'precio_costo'       => $this->precio_costo,
            'user_id'            => $this->user_id,
            'created_at'         => $this->created_at?->format('Y-m-d H:i:s'),

            'producto' => $this->when(
                $this->relationLoaded('producto'),
                fn () => $this->producto ? [
                    'id'     => $this->producto->id,
                    'codigo' => $this->producto->codigo,
                    'nombre' => $this->producto->nombre,
                ] : null
            ),

            'almacen' => $this->when(
                $this->relationLoaded('almacen'),
                fn () => $this->almacen ? [
                    'id'     => $this->almacen->id,
                    'nombre' => $this->almacen->nombre,
                ] : null
            ),

            'almacen_destino' => $this->when(
                $this->relationLoaded('almacenDestino'),
                fn () => $this->almacenDestino ? [
                    'id'     => $this->almacenDestino->id,
                    'nombre' => $this->almacenDestino->nombre,
                ] : null
            ),
        ];
    }
}
