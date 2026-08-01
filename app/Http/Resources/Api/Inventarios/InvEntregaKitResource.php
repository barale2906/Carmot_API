<?php

namespace App\Http\Resources\Api\Inventarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para entregas de kit (agrupan componentes de tipo grupo y simple).
 */
class InvEntregaKitResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'pedido_item_id'   => $this->pedido_item_id,
            'kit_producto_id'  => $this->kit_producto_id,
            'cantidad_kits'    => $this->cantidad_kits,
            'status'           => $this->status,
            'usuario'          => $this->whenLoaded('usuario', fn () => [
                'id'     => $this->usuario?->id,
                'nombre' => $this->usuario?->name,
            ]),
            'componentes'      => InvEntregaKitComponenteResource::collection(
                $this->whenLoaded('componentes')
            ),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
