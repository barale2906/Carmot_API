<?php

namespace App\Http\Resources\Api\Traslados;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resultado de simular un traslado: tipo deducido, origen y destino, lo financiero y la nivelación.
 * No se ha escrito nada: es lo que pasaría si se registra el traslado.
 *
 * @property array<string, mixed> $resource
 */
class TrasladoSimulacionResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tipo' => $this->resource['tipo'],
            'tipo_texto' => $this->resource['tipo_texto'],
            'fecha' => $this->resource['fecha'],
            'origen' => $this->resource['origen'],
            'destino' => $this->resource['destino'],
            'financiero' => $this->resource['financiero'],
            'nivelacion' => $this->resource['nivelacion'],
        ];
    }
}
