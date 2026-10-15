<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Datos de una persona para los buscadores de estudiantes (matrícula, ventas, recibos).
 * Solo identificación y contacto: no expone permisos ni sedes.
 *
 * @mixin \App\Models\User
 */
class EstudianteResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'primer_nombre'    => $this->primer_nombre,
            'segundo_nombre'   => $this->segundo_nombre,
            'primer_apellido'  => $this->primer_apellido,
            'segundo_apellido' => $this->segundo_apellido,
            'name'             => $this->name,
            'email'            => $this->email,
            'documento'        => $this->documento,
            /** @var array<string> */
            'roles'            => $this->getRoleNames()->all(),
        ];
    }
}
