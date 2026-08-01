<?php

namespace App\Http\Resources\Api\Inventarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para necesidades de compra generadas automáticamente cuando no hay stock.
 */
class InvNecesidadCompraResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'producto'          => [
                'id'     => $this->producto_id,
                'nombre' => $this->whenLoaded('producto', fn () => $this->producto->nombre),
                'codigo' => $this->whenLoaded('producto', fn () => $this->producto->codigo),
            ],
            'cantidad_necesaria' => $this->cantidad_necesaria,
            'almacen'           => [
                'id'     => $this->almacen_id,
                'nombre' => $this->whenLoaded('almacen', fn () => $this->almacen->nombre),
            ],
            'estudiante'        => [
                'id'     => $this->estudiante_id,
                'nombre' => $this->whenLoaded('estudiante', fn () => $this->estudiante->name),
            ],
            'entregable_type'   => $this->entregable_type,
            'entregable_id'     => $this->entregable_id,
            'status'            => $this->status,
            'notificado'        => (bool) $this->notificado,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
