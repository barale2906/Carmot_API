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
            'base_model_config' => $this->getBaseModelConfig(),
            'base_model_display_name' => $this->getBaseModelDisplayName(),
            'base_model_fields' => $this->getBaseModelFields(),
            'default_period_type' => $this->default_period_type,
            'default_period_start_date' => $this->default_period_start_date?->format('Y-m-d'),
            'default_period_end_date' => $this->default_period_end_date?->format('Y-m-d'),
            'use_custom_time_range' => $this->use_custom_time_range,
            'has_time_range' => $this->hasTimeRange(),
            'kpi_fields' => KpiFieldResource::collection($this->whenLoaded('kpiFields')),
            'field_relations' => KpiFieldRelationResource::collection($this->whenLoaded('fieldRelations')),
            'has_field_relations' => $this->when($this->relationLoaded('fieldRelations'), $this->fieldRelations->count() > 0),
            'dashboard_cards_count' => $this->when($this->relationLoaded('dashboardCards'), $this->dashboardCards->count()),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

}
