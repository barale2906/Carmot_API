<?php

namespace App\Http\Resources\Api\Inventarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para componentes individuales dentro de una entrega de kit.
 */
class InvEntregaKitComponenteResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'entrega_kit_id'       => $this->entrega_kit_id,
            'kit_componente_id'    => $this->kit_componente_id,
            'producto_entregado'   => $this->whenLoaded('productoEntregado', fn () => [
                'id'     => $this->productoEntregado?->id,
                'nombre' => $this->productoEntregado?->nombre,
                'codigo' => $this->productoEntregado?->codigo,
            ]),
            'cantidad_solicitada'  => $this->cantidad_solicitada,
            'cantidad_entregada'   => $this->cantidad_entregada,
            'status'               => $this->status,
            'fecha_entrega'        => $this->fecha_entrega,
            'usuario'              => $this->whenLoaded('usuario', fn () => [
                'id'     => $this->usuario?->id,
                'nombre' => $this->usuario?->name,
            ]),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
