<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListaAsistenciaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Este resource se usa para la respuesta de lista de asistencia que incluye:
     * - Información del grupo
     * - Estudiantes del grupo (de ciclos activos)
     * - Clases programadas del grupo
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'grupo' => [
                'id' => $this->resource['grupo']->id ?? null,
                'nombre' => $this->resource['grupo']->nombre ?? null,
                'inscritos' => $this->resource['grupo']->inscritos ?? null,
            ],

            'ciclos' => $this->when(isset($this->resource['ciclos']), function () {
                return collect($this->resource['ciclos'])->map(function ($ciclo) {
                    return [
                        'id' => $ciclo->id,
                        'nombre' => $ciclo->nombre,
                        'fecha_inicio' => $ciclo->fecha_inicio?->format('Y-m-d'),
                        'fecha_fin' => $ciclo->fecha_fin?->format('Y-m-d'),
                    ];
                });
            }),

            'estudiantes' => $this->when(isset($this->resource['estudiantes']), function () {
                return collect($this->resource['estudiantes'])->map(function ($estudiante) {
                    return [
                        'id' => $estudiante->id,
                        'name' => $estudiante->name,
                        'email' => $estudiante->email,
                        'documento' => $estudiante->documento,
                        'ciclo_id' => $estudiante->ciclo_id ?? null,
                        'ciclo_nombre' => $estudiante->ciclo_nombre ?? null,
                    ];
                });
            }),

            'clases_programadas' => $this->when(isset($this->resource['clases_programadas']), function () {
                return AsistenciaClaseProgramadaResource::collection($this->resource['clases_programadas']);
            }),

            'total_estudiantes' => isset($this->resource['estudiantes']) ? count($this->resource['estudiantes']) : 0,
            'total_clases' => isset($this->resource['clases_programadas']) ? count($this->resource['clases_programadas']) : 0,
        ];
    }
}
