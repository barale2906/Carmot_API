<?php

namespace App\Http\Resources\Api\Dashboard;

use App\Http\Resources\Api\Dashboard\KpiResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource DashboardCardResource
 *
 * Transforma el modelo DashboardCard para la respuesta de la API.
 * Incluye información de la tarjeta y su KPI asociado.
 */
class DashboardCardResource extends JsonResource
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
            'dashboard_id' => $this->dashboard_id,
            'kpi_id' => $this->kpi_id,
            'title' => $this->title,
            'background_color' => $this->background_color,
            'text_color' => $this->text_color,
            'width' => $this->width,
            'height' => $this->height,
            'x_position' => $this->x_position,
            'y_position' => $this->y_position,
            'period_type' => $this->period_type,
            'period_start_date' => $this->period_start_date?->format('Y-m-d'),
            'period_end_date' => $this->period_end_date?->format('Y-m-d'),
            'custom_field_values' => $this->custom_field_values,
            'order' => $this->order,
            'kpi_value' => $this->when(isset($this->kpi_value), $this->kpi_value),
            'kpi' => new KpiResource($this->whenLoaded('kpi')),
            'dashboard' => new DashboardResource($this->whenLoaded('dashboard')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
