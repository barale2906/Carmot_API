<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SabanaNotasGrupalResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     * Este resource representa la sabana de notas de un grupo en un módulo específico.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'grupo' => [
                'id' => $this->resource['grupo']['id'],
                'nombre' => $this->resource['grupo']['nombre'],
            ],
            'modulo' => [
                'id' => $this->resource['modulo']['id'],
                'nombre' => $this->resource['modulo']['nombre'],
            ],
            'esquema_calificacion' => $this->when(
                isset($this->resource['esquema_calificacion']),
                new EsquemaCalificacionResource($this->resource['esquema_calificacion'])
            ),
            'estudiantes' => $this->resource['estudiantes'] ?? [],
            'total_estudiantes' => $this->resource['total_estudiantes'] ?? 0,
            'estudiantes_con_notas' => $this->resource['estudiantes_con_notas'] ?? 0,
        ];
    }
}
