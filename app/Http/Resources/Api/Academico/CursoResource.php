<?php

namespace App\Http\Resources\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasTipo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de transformación para el modelo Curso.
 *
 * Serializa un curso con sus campos base, texto legible de tipo y estado,
 * y opcionalmente las colecciones de referidos, estudiantes y módulos
 * cuando las relaciones han sido cargadas con eager loading.
 *
 * @mixin \App\Models\Academico\Curso
 * @package App\Http\Resources\Api\Academico
 */
class CursoResource extends JsonResource
{
    use HasTipo, HasActiveStatus;

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
            'duracion' => $this->duracion,
            'tipo' => $this->tipo,
            'tipo_text' => self::getTipoText($this->tipo),
            'status' => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'referidos' => $this->whenLoaded('referidos', function () {
                return $this->referidos->map(function ($referido) {
                    return [
                        'id' => $referido->id,
                        'nombre' => $referido->nombre,
                        'celular' => $referido->celular,
                        'ciudad' => $referido->ciudad,
                        'status' => $referido->status,
                        'status_text' => $this->getReferidoStatusText($referido->status),
                    ];
                });
            }),

            'estudiantes' => $this->whenLoaded('estudiantes', function () {
                return $this->estudiantes->map(function ($estudiante) {
                    return [
                        'id' => $estudiante->id,
                        'name' => $estudiante->name,
                        'email' => $estudiante->email,
                        'pivot' => [
                            'created_at' => $estudiante->pivot->created_at?->format('Y-m-d H:i:s'),
                            'updated_at' => $estudiante->pivot->updated_at?->format('Y-m-d H:i:s'),
                        ],
                    ];
                });
            }),

            'modulos' => $this->whenLoaded('modulos', function () {
                return $this->modulos->map(function ($modulo) {
                    return [
                        'id' => $modulo->id,
                        'nombre' => $modulo->nombre,
                        'duracion' => (float) $modulo->duracion,
                        'status' => $modulo->status,
                        'status_text' => self::getActiveStatusText($modulo->status),
                        'pivot' => [
                            'orden' => $modulo->pivot->orden ?? 0,
                            'created_at' => $modulo->pivot->created_at?->format('Y-m-d H:i:s'),
                            'updated_at' => $modulo->pivot->updated_at?->format('Y-m-d H:i:s'),
                        ],
                    ];
                });
            }),

            // Contadores
            'referidos_count' => $this->when(isset($this->referidos_count), $this->referidos_count),
            'estudiantes_count' => $this->when(isset($this->estudiantes_count), $this->estudiantes_count),
            'modulos_count' => $this->when(isset($this->modulos_count), $this->modulos_count),
        ];
    }


    /**
     * Obtiene el texto del estado del referido.
     *
     * @param int $status
     * @return string
     */
    private function getReferidoStatusText(int $status): string
    {
        return match ($status) {
            0 => 'Creado',
            1 => 'Interesado',
            2 => 'Pendiente por matricular',
            3 => 'Matriculado',
            4 => 'Declinado',
            default => 'Desconocido',
        };
    }
}
