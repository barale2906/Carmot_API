<?php

namespace App\Http\Resources\Api\Dashboard;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para KPIs (CRUD)
 */
class KpiResource extends JsonResource
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'unit' => $this->unit,
            'is_active' => (bool)$this->is_active,

            'numerator_model' => $this->numerator_model,
            'numerator_field' => $this->numerator_field,
            'numerator_operation' => $this->numerator_operation,

            'denominator_model' => $this->denominator_model,
            'denominator_field' => $this->denominator_field,
            'denominator_operation' => $this->denominator_operation,

            'calculation_factor' => (float)($this->calculation_factor ?? 1),
            'target_value' => $this->target_value,
            'date_field' => $this->date_field,
            'period_type' => $this->period_type,

            'chart_type' => $this->chart_type,
            'chart_schema' => $this->chart_schema,

            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
