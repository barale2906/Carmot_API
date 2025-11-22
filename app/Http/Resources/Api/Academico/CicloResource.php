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
            'fecha_inicio' => $this->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $this->fecha_fin?->format('Y-m-d'),
            'fecha_fin_automatica' => $this->fecha_fin_automatica,
            'duracion_dias' => $this->duracion_dias,
            'duracion_estimada' => $this->duracion_estimada,
            'inscritos' => $this->inscritos,
            'total_horas' => $this->total_horas,
            'horas_por_semana' => $this->horas_por_semana,
            'en_curso' => $this->en_curso,
            'finalizado' => $this->finalizado,
            'por_iniciar' => $this->por_iniciar,
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

            'grupos' => $this->getGruposData(),

            // Contadores
            'sede_count' => $this->when(isset($this->sede_count), (int) $this->sede_count),
            'curso_count' => $this->when(isset($this->curso_count), (int) $this->curso_count),
            'grupos_count' => $this->when(isset($this->grupos_count), (int) $this->grupos_count),
        ];
    }

    /**
     * Obtiene los datos de grupos como array.
     *
     * @return array<int, array{id: int, nombre: string, inscritos: int, jornada: int, jornada_nombre: string, status: int, status_text: string, orden: int|null, fecha_inicio_grupo: string|null, fecha_fin_grupo: string|null, modulo: array{id: int, nombre: string, duracion: int}|null, profesor: array{id: int, name: string, email: string}|null, horarios: array<int, array{id: int, dia: string, hora: string, duracion_horas: int}>|null}>
     */
    private function getGruposData(): array
    {
        if (!$this->relationLoaded('grupos')) {
            return [];
        }

        return $this->grupos->map(function ($grupo) {
            return [
                'id' => $grupo->id,
                'nombre' => $grupo->nombre,
                'inscritos' => $grupo->inscritos,
                'jornada' => $grupo->jornada,
                'jornada_nombre' => $grupo->jornada_nombre,
                'status' => $grupo->status,
                'status_text' => self::getActiveStatusText($grupo->status),
                'orden' => $grupo->pivot->orden ?? null,
                'fecha_inicio_grupo' => $grupo->pivot->fecha_inicio_grupo ?? null,
                'fecha_fin_grupo' => $grupo->pivot->fecha_fin_grupo ?? null,
                'modulo' => $grupo->relationLoaded('modulo') ? [
                    'id' => $grupo->modulo->id,
                    'nombre' => $grupo->modulo->nombre,
                    'duracion' => $grupo->modulo->duracion ?? 0,
                ] : null,
                'profesor' => $grupo->relationLoaded('profesor') ? [
                    'id' => $grupo->profesor->id,
                    'name' => $grupo->profesor->name,
                    'email' => $grupo->profesor->email,
                ] : null,
                'horarios' => $grupo->relationLoaded('horarios') ? $grupo->horarios->map(function ($horario) {
                    return [
                        'id' => $horario->id,
                        'dia' => $horario->dia,
                        'hora' => $horario->hora?->format('H:i:s'),
                        'duracion_horas' => $horario->duracion_horas,
                    ];
                })->toArray() : null,
            ];
        })->toArray();
    }

}
