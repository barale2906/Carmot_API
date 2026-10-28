<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Estado del registro de notas de un ciclo (abierto o cerrado) con su bitácora de
 * cierres y reaperturas. Recibe un {@see \App\Models\Academico\Ciclo}.
 */
class CierreNotasCicloResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ciclo'             => ['id' => $this->id, 'nombre' => $this->nombre],
            'notas_cerradas'    => $this->notasCerradas(),
            'notas_cerradas_at' => $this->notas_cerradas_at?->format('Y-m-d H:i:s'),
            'cerradas_por'      => $this->whenLoaded('notasCerradasPor', fn () => $this->notasCerradasPor
                ? ['id' => $this->notasCerradasPor->id, 'name' => $this->notasCerradasPor->name]
                : null),
            'historial'         => $this->whenLoaded('cierresNotas', fn () => $this->cierresNotas->map(fn ($evento) => [
                'id'         => $evento->id,
                'accion'     => $evento->accion,
                'motivo'     => $evento->motivo,
                'usuario'    => $evento->usuario ? ['id' => $evento->usuario->id, 'name' => $evento->usuario->name] : null,
                'created_at' => $evento->created_at?->format('Y-m-d H:i:s'),
            ])->values()),
        ];
    }
}
