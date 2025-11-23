<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SabanaNotasEstudianteResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     * Este resource representa la sabana de notas de un estudiante en todos sus módulos.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'estudiante' => [
                'id' => $this->resource['estudiante']['id'],
                'name' => $this->resource['estudiante']['name'],
                'email' => $this->resource['estudiante']['email'],
                'documento' => $this->resource['estudiante']['documento'],
            ],
            'curso' => $this->when(isset($this->resource['curso']), [
                'id' => $this->resource['curso']['id'] ?? null,
                'nombre' => $this->resource['curso']['nombre'] ?? null,
            ]),
            'ciclo' => $this->when(isset($this->resource['ciclo']), [
                'id' => $this->resource['ciclo']['id'] ?? null,
                'nombre' => $this->resource['ciclo']['nombre'] ?? null,
            ]),
            'modulos' => $this->resource['modulos'] ?? [],
            'promedio_general' => $this->resource['promedio_general'] ?? null,
            'total_modulos' => $this->resource['total_modulos'] ?? 0,
            'modulos_completos' => $this->resource['modulos_completos'] ?? 0,
        ];
    }
}
