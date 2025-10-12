<?php

namespace App\Http\Resources\Api\Configuracion;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AreaResource extends JsonResource
{
    use HasActiveStatus;

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
            'status' => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'sedes' => $this->whenLoaded('sedes', function () {
                return $this->sedes->map(function ($sede) {
                    return [
                        'id' => $sede->id,
                        'nombre' => $sede->nombre,
                        'direccion' => $sede->direccion,
                        'telefono' => $sede->telefono,
                        'email' => $sede->email,
                        'poblacion' => $sede->relationLoaded('poblacion') ? [
                            'id' => $sede->poblacion->id,
                            'nombre' => $sede->poblacion->nombre,
                            'pais' => $sede->poblacion->pais,
                            'provincia' => $sede->poblacion->provincia,
                        ] : null,
                    ];
                });
            }),

            // Contadores
            'sedes_count' => $this->when(isset($this->sedes_count), $this->sedes_count),
        ];
    }
}
