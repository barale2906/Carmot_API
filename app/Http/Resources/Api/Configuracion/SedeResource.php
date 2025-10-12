<?php

namespace App\Http\Resources\Api\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SedeResource extends JsonResource
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
            'nombre' => $this->nombre,
            'direccion' => $this->direccion,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'hora_inicio' => $this->hora_inicio?->format('H:i:s'),
            'hora_fin' => $this->hora_fin?->format('H:i:s'),
            'poblacion_id' => $this->poblacion_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'poblacion' => $this->whenLoaded('poblacion', function () {
                return [
                    'id' => $this->poblacion->id,
                    'nombre' => $this->poblacion->nombre,
                    'pais' => $this->poblacion->pais,
                    'provincia' => $this->poblacion->provincia,
                ];
            }),

            // Contadores
            'poblacion_count' => $this->when(isset($this->poblacion_count), $this->poblacion_count),
        ];
    }
}
