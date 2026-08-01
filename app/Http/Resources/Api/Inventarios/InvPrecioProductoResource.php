<?php

namespace App\Http\Resources\Api\Inventarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para precios de producto en listas de precios de inventario.
 */
class InvPrecioProductoResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'lista_precio'   => [
                'id'     => $this->lista_precio_id,
                'nombre' => $this->whenLoaded('listaPrecio', fn () => $this->listaPrecio->nombre),
            ],
            'producto'       => [
                'id'     => $this->producto_id,
                'nombre' => $this->whenLoaded('producto', fn () => $this->producto->nombre),
                'codigo' => $this->whenLoaded('producto', fn () => $this->producto->codigo),
            ],
            'precio'         => $this->precio,
            'observaciones'  => $this->observaciones,
            'deleted_at'     => $this->deleted_at,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
