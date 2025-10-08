<?php

namespace App\Http\Resources\Api\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeguimientoResource extends JsonResource
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
            'id' => $this->id,
            'referido_id' => $this->referido_id,
            'seguidor_id' => $this->seguidor_id,
            'fecha' => $this->fecha,
            'seguimiento' => $this->seguimiento,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,

            // Relaciones
            'referido' => $this->whenLoaded('referido'),
            'seguidor' => $this->whenLoaded('seguidor'),
        ];
    }
}
