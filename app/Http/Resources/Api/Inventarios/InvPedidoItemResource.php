<?php

namespace App\Http\Resources\Api\Inventarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para ítems de un pedido de inventario.
 */
class InvPedidoItemResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'pedido_id'       => $this->pedido_id,
            'producto'        => [
                'id'     => $this->producto_id,
                'nombre' => $this->whenLoaded('producto', fn () => $this->producto->nombre),
                'codigo' => $this->whenLoaded('producto', fn () => $this->producto->codigo),
                'tipo'   => $this->whenLoaded('producto', fn () => $this->producto->tipo),
            ],
            'cantidad'        => $this->cantidad,
            'precio_unitario' => $this->precio_unitario,
            'subtotal'        => $this->subtotal,
            'entrega_simple'  => $this->whenLoaded(
                'entregaSimple',
                fn () => new InvEntregaSimpleResource($this->entregaSimple)
            ),
            'entrega_kit'     => $this->whenLoaded(
                'entregaKit',
                fn () => new InvEntregaKitResource($this->entregaKit)
            ),
            'created_at'      => $this->created_at,
        ];
    }
}
