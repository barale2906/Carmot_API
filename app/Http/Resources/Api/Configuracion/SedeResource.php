<?php

namespace App\Http\Resources\Api\Configuracion;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SedeResource extends JsonResource
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
            'direccion' => $this->direccion,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'hora_inicio' => $this->hora_inicio?->format('H:i:s'),
            'hora_fin' => $this->hora_fin?->format('H:i:s'),
            'duracion_horas' => $this->duracion_en_horas,
            'duracion_minutos' => $this->duracion_en_minutos,
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

            'areas' => $this->whenLoaded('areas', function () {
                $statusOptions = self::getActiveStatusOptions();
                return $this->areas->map(function ($area) use ($statusOptions) {
                    return [
                        'id' => $area->id,
                        'nombre' => $area->nombre,
                        'status' => $area->status,
                        'status_text' => $statusOptions[$area->status] ?? 'Desconocido',
                    ];
                });
            }),

            // Contadores
            'poblacion_count' => $this->when(isset($this->poblacion_count), $this->poblacion_count),
            'areas_count' => $this->when(isset($this->areas_count), $this->areas_count),
        ];
    }

}
