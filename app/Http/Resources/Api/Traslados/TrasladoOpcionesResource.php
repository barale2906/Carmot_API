<?php

namespace App\Http\Resources\Api\Traslados;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ciclos (con su programación y jornada) y precios de lista disponibles en la sede y el curso de destino.
 *
 * @property array<string, mixed> $resource
 */
class TrasladoOpcionesResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ciclos' => $this->resource['ciclos'],
            'precios' => $this->resource['precios'],
        ];
    }
}
