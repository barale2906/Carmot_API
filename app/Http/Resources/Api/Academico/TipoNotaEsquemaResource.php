<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipoNotaEsquemaResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'esquema_calificacion_id' => $this->esquema_calificacion_id,
            'nombre_tipo' => $this->nombre_tipo,
            'peso' => (float) $this->peso,
            'orden' => $this->orden,
            'nota_minima' => (float) $this->nota_minima,
            'nota_maxima' => (float) $this->nota_maxima,
            'descripcion' => $this->descripcion,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'esquema_calificacion' => $this->whenLoaded('esquemaCalificacion', function () {
                return new EsquemaCalificacionResource($this->esquemaCalificacion);
            }),

            // Contadores
            'notas_estudiantes_count' => $this->when(isset($this->notas_estudiantes_count), $this->notas_estudiantes_count),
        ];
    }
}
