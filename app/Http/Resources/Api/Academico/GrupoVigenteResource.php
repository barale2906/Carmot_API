<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Grupo resumido para los selectores de asistencia y calificaciones.
 */
class GrupoVigenteResource extends JsonResource
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
            'id'        => $this->id,
            'nombre'    => $this->nombre,
            'jornada'   => $this->jornada,
            'inscritos' => $this->inscritos,
            'modulo'    => $this->modulo ? ['id' => $this->modulo->id, 'nombre' => $this->modulo->nombre] : null,
            'sede'      => $this->sede ? ['id' => $this->sede->id, 'nombre' => $this->sede->nombre] : null,
            'profesor'  => $this->profesor ? ['id' => $this->profesor->id, 'name' => $this->profesor->name] : null,
        ];
    }
}
