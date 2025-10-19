<?php

namespace App\Http\Resources\Api\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource KpiResource
 *
 * Transforma el modelo Kpi para la respuesta de la API.
 * Incluye información del KPI y sus campos de configuración.
 */
class KpiResource extends JsonResource
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
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'unit' => $this->unit,
            'is_active' => $this->is_active,
            'calculation_type' => $this->calculation_type,
            'base_model' => $this->base_model,
            'base_model_display_name' => $this->getBaseModelDisplayName(),
            'kpi_fields' => KpiFieldResource::collection($this->whenLoaded('kpiFields')),
            'dashboard_cards_count' => $this->when($this->relationLoaded('dashboardCards'), $this->dashboardCards->count()),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Obtiene el nombre de visualización del modelo base.
     *
     * @return string|null
     */
    private function getBaseModelDisplayName(): ?string
    {
        if (!$this->base_model) {
            return null;
        }

        $config = config("kpis.available_kpi_models.{$this->base_model}");
        return $config['display_name'] ?? class_basename($this->base_model);
    }
}
