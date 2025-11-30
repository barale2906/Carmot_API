<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AsistenciaClaseProgramadaResource extends JsonResource
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
            'grupo' => $this->whenLoaded('grupo', [
                'id' => $this->grupo->id,
                'nombre' => $this->grupo->nombre,
                'inscritos' => $this->grupo->inscritos,
            ]),

            'ciclo' => $this->whenLoaded('ciclo', [
                'id' => $this->ciclo->id,
                'nombre' => $this->ciclo->nombre,
                'descripcion' => $this->ciclo->descripcion,
                'fecha_inicio' => $this->ciclo->fecha_inicio?->format('Y-m-d'),
                'fecha_fin' => $this->ciclo->fecha_fin?->format('Y-m-d'),
            ]),

            'creado_por' => $this->whenLoaded('creadoPor', [
                'id' => $this->creadoPor->id,
                'name' => $this->creadoPor->name,
                'email' => $this->creadoPor->email,
            ]),

            'asistencias' => $this->whenLoaded('asistencias', function () {
                return AsistenciaResource::collection($this->asistencias);
            }),

            // Campos principales
            'grupo_id' => $this->grupo_id,
            'ciclo_id' => $this->ciclo_id,
            'fecha_clase' => $this->fecha_clase?->format('Y-m-d'),
            'hora_inicio' => $this->hora_inicio,
            'hora_fin' => $this->hora_fin,
            'duracion_horas' => (float) $this->duracion_horas,
            'estado' => $this->estado,
            'estado_text' => $this->getEstadoText(),
            'observaciones' => $this->observaciones,
            'fecha_programacion' => $this->fecha_programacion?->format('Y-m-d H:i:s'),
            'creado_por_id' => $this->creado_por_id,

            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Obtiene el texto legible del estado.
     *
     * @return string
     */
    private function getEstadoText(): string
    {
        return match($this->estado) {
            'programada' => 'Programada',
            'dictada' => 'Dictada',
            'cancelada' => 'Cancelada',
            'reprogramada' => 'Reprogramada',
            default => $this->estado,
        };
    }
}
