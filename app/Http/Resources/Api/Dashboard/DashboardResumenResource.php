<?php

namespace App\Http\Resources\Api\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resumen del Dashboard (ver DashboardResumenService para el contenido de cada sección).
 */
class DashboardResumenResource extends JsonResource
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
            'tarjetas'  => $this->resource['tarjetas'],
            'alertas'   => $this->resource['alertas'],
            'actividad' => $this->resource['actividad'],
            'eventos'   => $this->resource['eventos'],
            'retencion' => $this->resource['retencion'],
        ];
    }
}
