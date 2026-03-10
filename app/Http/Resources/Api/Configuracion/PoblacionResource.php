<?php

namespace App\Http\Resources\Api\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PoblacionResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed> Array con los datos de la población
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pais' => $this->pais,
            'provincia' => $this->provincia,
            'nombre' => $this->nombre,
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
