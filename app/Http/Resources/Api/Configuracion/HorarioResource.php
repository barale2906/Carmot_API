<?php

namespace App\Http\Resources\Api\Configuracion;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HorarioResource extends JsonResource
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
            'sede_id' => $this->sede_id,
            'area_id' => $this->area_id,
            'grupo_id' => $this->grupo_id,
            'grupo_nombre' => $this->grupo_nombre,
            'tipo' => $this->tipo,
            'tipo_text' => $this->tipo ? 'Horario de Sede' : 'Horario de Grupo',
            'periodo' => $this->periodo,
            'periodo_text' => $this->periodo ? 'Inicio' : 'Fin',
            'dia' => $this->dia,
            'hora' => $this->hora?->format('H:i:s'),
            'status' => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'sede' => $this->whenLoaded('sede', function () {
                return [
                    'id' => $this->sede->id,
                    'nombre' => $this->sede->nombre,
                    'direccion' => $this->sede->direccion,
                    'telefono' => $this->sede->telefono,
                    'email' => $this->sede->email,
                    'hora_inicio' => $this->sede->hora_inicio?->format('H:i:s'),
                    'hora_fin' => $this->sede->hora_fin?->format('H:i:s'),
                    'poblacion_id' => $this->sede->poblacion_id,
                ];
            }),

            'area' => $this->whenLoaded('area', function () {
                return [
                    'id' => $this->area->id,
                    'nombre' => $this->area->nombre,
                    'status' => $this->area->status,
                    'status_text' => self::getActiveStatusText($this->area->status),
                ];
            }),

            // Contadores
            'sede_count' => $this->when(isset($this->sede_count), $this->sede_count),
            'area_count' => $this->when(isset($this->area_count), $this->area_count),
        ];
    }

}
