<?php

namespace App\Http\Resources\Api\Academico;

use App\Models\Academico\Matricula;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Registro del historial de estados académicos de una matrícula.
 */
class MatriculaEstadoResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'matricula_id'          => $this->matricula_id,
            'estado_anterior'       => $this->estado_anterior,
            'estado_anterior_texto' => Matricula::ESTADOS_ACADEMICOS[$this->estado_anterior] ?? null,
            'estado_nuevo'          => $this->estado_nuevo,
            'estado_nuevo_texto'    => Matricula::ESTADOS_ACADEMICOS[$this->estado_nuevo] ?? null,
            'fecha'                 => $this->fecha?->format('Y-m-d'),
            'motivo'                => $this->motivo,
            'usuario'               => $this->whenLoaded('usuario', fn () => $this->usuario ? [
                'id'   => $this->usuario->id,
                'name' => $this->usuario->name,
            ] : null),
            'created_at'            => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
