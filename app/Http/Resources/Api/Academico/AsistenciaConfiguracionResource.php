<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AsistenciaConfiguracionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Relaciones cargadas
            'curso' => $this->whenLoaded('curso', [
                'id' => $this->curso->id,
                'nombre' => $this->curso->nombre,
                'duracion' => $this->curso->duracion,
            ]),

            'modulo' => $this->whenLoaded('modulo', [
                'id' => $this->modulo->id,
                'nombre' => $this->modulo->nombre,
            ]),

            // Campos principales
            'curso_id' => $this->curso_id,
            'modulo_id' => $this->modulo_id,
            'porcentaje_minimo' => (float) $this->porcentaje_minimo,
            'horas_minimas' => $this->horas_minimas,
            'aplicar_justificaciones' => (bool) $this->aplicar_justificaciones,
            'perder_por_fallas' => (bool) $this->perder_por_fallas,
            'fecha_inicio_vigencia' => $this->fecha_inicio_vigencia?->format('Y-m-d'),
            'fecha_fin_vigencia' => $this->fecha_fin_vigencia?->format('Y-m-d'),
            'observaciones' => $this->observaciones,
            'es_vigente' => $this->esVigente(),

            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }
}
