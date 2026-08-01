<?php

namespace App\Http\Resources\Api\Inventarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para la representación de un componente de kit.
 */
class InvKitComponenteResource extends JsonResource
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
            'kit_id'             => $this->kit_id,
            'grupo_producto_id'  => $this->grupo_producto_id,
            'cantidad'           => $this->cantidad,
            'orden'              => $this->orden,
            'created_at'         => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'         => $this->updated_at?->format('Y-m-d H:i:s'),

            // Datos del producto componente cuando se carga la relación
            'grupo_producto' => $this->when(
                $this->relationLoaded('grupoProducto'),
                fn () => [
                    'id'     => $this->grupoProducto?->id,
                    'codigo' => $this->grupoProducto?->codigo,
                    'nombre' => $this->grupoProducto?->nombre,
                    'tipo'   => $this->grupoProducto?->tipo,
                ]
            ),
        ];
    }
}
