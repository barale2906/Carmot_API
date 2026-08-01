<?php

namespace App\Http\Resources\Api\Inventarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para ítems de órdenes de compra.
 */
class InvOrdenCompraItemResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'orden_id'               => $this->orden_id,
            'producto'               => [
                'id'     => $this->producto_id,
                'nombre' => $this->whenLoaded('producto', fn () => $this->producto->nombre),
                'codigo' => $this->whenLoaded('producto', fn () => $this->producto->codigo),
                'tipo'   => $this->whenLoaded('producto', fn () => $this->producto->tipo),
            ],
            'cantidad_solicitada'    => $this->cantidad_solicitada,
            'cantidad_recibida'      => $this->cantidad_recibida,
            'cantidad_pendiente'     => $this->cantidadPendiente(),
            'precio_costo_unitario'  => $this->precio_costo_unitario,
            'subtotal'               => $this->subtotal,
            'completo'               => $this->estaCompleto(),
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
        ];
    }
}
