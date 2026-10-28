<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AsistenciaResource extends JsonResource
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
            'estudiante_id' => $this->estudiante_id,
            'clase_programada_id' => $this->clase_programada_id,
            'grupo_id' => $this->grupo_id,
            'ciclo_id' => $this->ciclo_id,
            'modulo_id' => $this->modulo_id,
            'curso_id' => $this->curso_id,
            'estado' => $this->estado,
            'estado_text' => $this->getEstadoText(),
            'justificada' => (bool) $this->justificada,
            'justificacion' => $this->justificacion,
            'hora_registro' => $this->hora_registro,
            'observaciones' => $this->observaciones,
            'registrado_por_id' => $this->registrado_por_id,
            'fecha_registro' => $this->fecha_registro?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Mora del estudiante: solo cuando el listado la calculó (Asistencia::cargarMora)
            $this->mergeWhen(array_key_exists('saldo_vencido', $this->resource->getAttributes()), fn () => [
                'saldo_vencido' => (float) $this->saldo_vencido,
                'en_mora' => $this->saldo_vencido > 0,
            ]),

            // Relaciones cargadas
            'estudiante' => $this->whenLoaded('estudiante', [
                'id' => $this->estudiante->id,
                'name' => $this->estudiante->name,
                'email' => $this->estudiante->email,
                'documento' => $this->estudiante->documento,
            ]),

            'clase_programada' => $this->whenLoaded('claseProgramada', function () {
                return [
                    'id' => $this->claseProgramada->id,
                    'fecha_clase' => $this->claseProgramada->fecha_clase?->format('Y-m-d'),
                    'hora_inicio' => $this->claseProgramada->hora_inicio,
                    'hora_fin' => $this->claseProgramada->hora_fin,
                    'duracion_horas' => (float) $this->claseProgramada->duracion_horas,
                    'estado' => $this->claseProgramada->estado,
                ];
            }),

            'grupo' => $this->whenLoaded('grupo', fn () => [
                'id' => $this->grupo->id,
                'nombre' => $this->grupo->nombre,
                'sede' => $this->grupo->relationLoaded('sede') && $this->grupo->sede
                    ? ['id' => $this->grupo->sede->id, 'nombre' => $this->grupo->sede->nombre]
                    : null,
            ]),

            'ciclo' => $this->whenLoaded('ciclo', [
                'id' => $this->ciclo->id,
                'nombre' => $this->ciclo->nombre,
                'fecha_inicio' => $this->ciclo->fecha_inicio?->format('Y-m-d'),
                'fecha_fin' => $this->ciclo->fecha_fin?->format('Y-m-d'),
            ]),

            'modulo' => $this->whenLoaded('modulo', [
                'id' => $this->modulo->id,
                'nombre' => $this->modulo->nombre,
            ]),

            'curso' => $this->whenLoaded('curso', [
                'id' => $this->curso->id,
                'nombre' => $this->curso->nombre,
            ]),

            'registrado_por' => $this->whenLoaded('registradoPor', [
                'id' => $this->registradoPor->id,
                'name' => $this->registradoPor->name,
                'email' => $this->registradoPor->email,
            ]),
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
            'presente' => 'Presente',
            'ausente' => 'Ausente',
            'tardanza' => 'Tardanza',
            default => $this->estado,
        };
    }
}
