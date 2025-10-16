<?php

namespace App\Http\Resources\Api\Academico;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CicloResource extends JsonResource
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
            'status' => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'sede' => $this->whenLoaded('sede', [
                'id' => $this->sede->id,
                'nombre' => $this->sede->nombre,
                'direccion' => $this->sede->direccion,
                'telefono' => $this->sede->telefono,
                'email' => $this->sede->email,
                'hora_inicio' => $this->sede->hora_inicio?->format('H:i:s'),
                'hora_fin' => $this->sede->hora_fin?->format('H:i:s'),
                'status' => $this->sede->status,
                'status_text' => self::getActiveStatusText($this->sede->status),
            ]),

            'curso' => $this->whenLoaded('curso', [
                'id' => $this->curso->id,
                'nombre' => $this->curso->nombre,
                'duracion' => $this->curso->duracion,
                'status' => $this->curso->status,
                'status_text' => self::getActiveStatusText($this->curso->status),
            ]),

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
                        'modulo' => $grupo->relationLoaded('modulo') ? [
                            'id' => $grupo->modulo->id,
                            'nombre' => $grupo->modulo->nombre,
                        ] : null,
                        'profesor' => $grupo->relationLoaded('profesor') ? [
                            'id' => $grupo->profesor->id,
                            'name' => $grupo->profesor->name,
                            'email' => $grupo->profesor->email,
                        ] : null,
                    ];
                })->toArray();
            }),

            // Contadores
            'sede_count' => $this->when(isset($this->sede_count), $this->sede_count),
            'curso_count' => $this->when(isset($this->curso_count), $this->curso_count),
            'grupos_count' => $this->when(isset($this->grupos_count), $this->grupos_count),
        ];
    }
}
