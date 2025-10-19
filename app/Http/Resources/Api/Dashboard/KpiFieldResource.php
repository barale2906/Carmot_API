<?php

namespace App\Http\Resources\Api\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource KpiFieldResource
 *
 * Transforma el modelo KpiField para la respuesta de la API.
 * Incluye información del campo y su configuración.
 */
class KpiFieldResource extends JsonResource
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
            'kpi_id' => $this->kpi_id,
            'field_name' => $this->field_name,
            'display_name' => $this->display_name,
            'field_type' => $this->field_type,
            'operation' => $this->operation,
            'operator' => $this->operator,
            'value' => $this->value,
            'is_required' => $this->is_required,
            'order' => $this->order,
            'kpi' => new KpiResource($this->whenLoaded('kpi')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
