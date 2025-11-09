<?php

namespace App\Http\Resources\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para transformar el modelo Tema en una respuesta JSON.
 *
 * Este resource formatea los datos del tema y sus relaciones
 * para ser enviados como respuesta de la API.
 */
class TemaResource extends JsonResource
{
    use HasActiveStatus, HasActiveStatusValidation;

    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request Solicitud HTTP actual
     * @return array<string, mixed> Array con los datos formateados del tema
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'duracion' => $this->duracion,
            'status' => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'topicos' => $this->whenLoaded('topicos', function () {
                return $this->topicos->map(function ($topico) {
                    return [
                        'id' => $topico->id,
                        'nombre' => $topico->nombre,
                        'descripcion' => $topico->descripcion,
                        'duracion' => $topico->duracion,
                        'status' => $topico->status,
                        'status_text' => self::getActiveStatusText($topico->status),
                        'pivot' => [
                            'created_at' => $topico->pivot->created_at?->format('Y-m-d H:i:s'),
                            'updated_at' => $topico->pivot->updated_at?->format('Y-m-d H:i:s'),
                        ],
                    ];
                });
            }),

            // Contadores
            'topicos_count' => $this->when(isset($this->topicos_count), $this->topicos_count),
        ];
    }
}
