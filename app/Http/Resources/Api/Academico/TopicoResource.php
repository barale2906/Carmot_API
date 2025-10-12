<?php

namespace App\Http\Resources\Api\Academico;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopicoResource extends JsonResource
{
    use HasActiveStatus;

    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
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
            'modulos' => $this->whenLoaded('modulos', function () {
                return $this->modulos->map(function ($modulo) {
                    return [
                        'id' => $modulo->id,
                        'nombre' => $modulo->nombre,
                        'status' => $modulo->status,
                        'status_text' => self::getActiveStatusText($modulo->status),
                        'pivot' => [
                            'created_at' => $modulo->pivot->created_at?->format('Y-m-d H:i:s'),
                            'updated_at' => $modulo->pivot->updated_at?->format('Y-m-d H:i:s'),
                        ],
                    ];
                });
            }),

            // Contadores
            'modulos_count' => $this->when(isset($this->modulos_count), $this->modulos_count),
        ];
    }
}
