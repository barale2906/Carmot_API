<?php

namespace App\Http\Resources\Api\Inventarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para órdenes de compra de inventario.
 */
class InvOrdenCompraResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'proveedor'       => $this->whenLoaded('proveedor', fn () => [
                'id'          => $this->proveedor->id,
                'razon_social' => $this->proveedor->razon_social,
                'nit'         => $this->proveedor->nit,
            ]),
            'almacen'         => $this->whenLoaded('almacen', fn () => [
                'id'     => $this->almacen->id,
                'nombre' => $this->almacen->nombre,
            ]),
            'responsable'     => $this->whenLoaded('responsable', fn () => [
                'id'     => $this->responsable->id,
                'nombre' => $this->responsable->name,
            ]),
            'status'          => $this->status,
            'subtotal'        => $this->subtotal,
            'total'           => $this->total,
            'observaciones'   => $this->observaciones,
            'fecha_esperada'  => $this->fecha_esperada,
            'items'           => InvOrdenCompraItemResource::collection($this->whenLoaded('items')),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
