<?php

namespace App\Http\Resources\Api\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para respuesta de cálculo de KPI
 *
 * Estandariza la estructura de salida para facilitar el consumo
 * por el frontend y la integración con ECharts.
 */
class KpiComputeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        // La estructura ya viene normalizada desde el servicio.
        // Aquí garantizamos claves consistentes para el frontend.
        $base = [
            'is_grouped' => (bool)($this['is_grouped'] ?? false),
            'factor' => $this['factor'] ?? null,
            'formula' => $this['formula'] ?? null,
            'description' => $this['description'] ?? null,
            'range' => $this['range'] ?? null,
        ];

        if ($base['is_grouped']) {
            $base['series'] = $this['series'] ?? [];
            $base['chart'] = $this['chart'] ?? null;
        } else {
            $base['value'] = $this['value'] ?? 0;
            $base['numerator'] = $this['numerator'] ?? 0;
            $base['denominator'] = $this['denominator'] ?? 0;
            $base['chart'] = $this['chart'] ?? null;
        }

        return $base;
    }
}
