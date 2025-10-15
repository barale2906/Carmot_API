<?php

namespace App\Http\Resources\Api\Academico;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuloResource extends JsonResource
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
            'status' => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'cursos' => $this->whenLoaded('cursos', function () {
                return $this->cursos->map(function ($curso) {
                    return [
                        'id' => $curso->id,
                        'nombre' => $curso->nombre,
                        'duracion' => $curso->duracion,
                        'tipo' => $curso->tipo,
                        'status' => $curso->status,
                        'status_text' => self::getActiveStatusText($curso->status),
                        'pivot' => [
                            'created_at' => $curso->pivot->created_at?->format('Y-m-d H:i:s'),
                            'updated_at' => $curso->pivot->updated_at?->format('Y-m-d H:i:s'),
                        ],
                    ];
                });
            }),

            'grupos' => $this->whenLoaded('grupos', function () {
                return $this->grupos->map(function ($grupo) {
                    return [
                        'id' => $grupo->id,
                        'nombre' => $grupo->nombre,
                        'inscritos' => $grupo->inscritos,
                        'jornada' => $grupo->jornada,
                        'jornada_nombre' => $grupo->jornada_nombre,
                        'status' => $grupo->status,
                        'status_text' => self::getActiveStatusText($grupo->status),
                        'sede' => $grupo->whenLoaded('sede', function () use ($grupo) {
                            return [
                                'id' => $grupo->sede->id,
                                'nombre' => $grupo->sede->nombre,
                            ];
                        }),
                        'profesor' => $grupo->whenLoaded('profesor', function () use ($grupo) {
                            return [
                                'id' => $grupo->profesor->id,
                                'name' => $grupo->profesor->name,
                            ];
                        }),
                    ];
                });
            }),

            // Contadores
            'cursos_count' => $this->when(isset($this->cursos_count), $this->cursos_count),
            'grupos_count' => $this->when(isset($this->grupos_count), $this->grupos_count),
        ];
    }
}
