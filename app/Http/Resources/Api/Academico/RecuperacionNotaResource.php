<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecuperacionNotaResource extends JsonResource
{
    /**
     * Transforma la recuperación de un módulo en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'estudiante_id'     => $this->estudiante_id,
            'grupo_id'          => $this->grupo_id,
            'modulo_id'         => $this->modulo_id,
            'nota'              => (float) $this->nota,
            'fecha_registro'    => $this->fecha_registro?->format('Y-m-d'),
            'registrado_por_id' => $this->registrado_por_id,
            'observaciones'     => $this->observaciones,
            'created_at'        => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'        => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at'        => $this->deleted_at?->format('Y-m-d H:i:s'),

            'estudiante' => $this->whenLoaded('estudiante', fn () => [
                'id'        => $this->estudiante->id,
                'name'      => $this->estudiante->name,
                'documento' => $this->estudiante->documento,
            ]),
            'grupo' => $this->whenLoaded('grupo', fn () => [
                'id'     => $this->grupo->id,
                'nombre' => $this->grupo->nombre,
            ]),
            'modulo' => $this->whenLoaded('modulo', fn () => [
                'id'     => $this->modulo->id,
                'nombre' => $this->modulo->nombre,
            ]),
            'registrado_por' => $this->whenLoaded('registradoPor', fn () => [
                'id'   => $this->registradoPor->id,
                'name' => $this->registradoPor->name,
            ]),
        ];
    }
}
