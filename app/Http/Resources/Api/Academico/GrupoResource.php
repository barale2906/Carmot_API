<?php

namespace App\Http\Resources\Api\Academico;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrupoResource extends JsonResource
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
            'inscritos' => $this->inscritos,
            'jornada' => $this->jornada,
            'jornada_nombre' => $this->jornada_nombre,
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

            'modulo' => $this->whenLoaded('modulo', [
                'id' => $this->modulo->id,
                'nombre' => $this->modulo->nombre,
                'status' => $this->modulo->status,
                'status_text' => self::getActiveStatusText($this->modulo->status),
            ]),

            'profesor' => $this->whenLoaded('profesor', [
                'id' => $this->profesor->id,
                'name' => $this->profesor->name,
                'email' => $this->profesor->email,
                'documento' => $this->profesor->documento,
            ]),

            'horarios' => $this->whenLoaded('horarios', function () {
                return $this->horarios->map(function ($horario) {
                    return [
                        'id' => $horario->id,
                        'dia' => $horario->dia,
                        'hora' => $horario->hora?->format('H:i:s'),
                        'duracion_horas' => $horario->duracion_horas,
                        'hora_fin' => $horario->hora ? $horario->hora->addHours($horario->duracion_horas)->format('H:i:s') : null,
                        'area' => $horario->whenLoaded('area', [
                            'id' => $horario->area->id,
                            'nombre' => $horario->area->nombre,
                        ]),
                        'status' => $horario->status,
                        'status_text' => self::getActiveStatusText($horario->status),
                    ];
                });
            }),

            // Contadores
            'sede_count' => $this->when(isset($this->sede_count), $this->sede_count),
            'modulo_count' => $this->when(isset($this->modulo_count), $this->modulo_count),
            'profesor_count' => $this->when(isset($this->profesor_count), $this->profesor_count),
            'horarios_count' => $this->when(isset($this->horarios_count), $this->horarios_count),

            // Información adicional de horarios
            'total_horas_semana' => $this->when($this->relationLoaded('horarios'), $this->total_horas_semana),
            'dias_clase' => $this->when($this->relationLoaded('horarios'), $this->dias_clase),
            'tiene_horarios' => $this->when($this->relationLoaded('horarios'), $this->tieneHorarios()),
        ];
    }
}
