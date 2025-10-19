<?php

namespace App\Http\Resources\Api\Dashboard;

use App\Models\Dashboard\KpiFieldRelation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource KpiFieldRelationResource
 *
 * Transforma el modelo KpiFieldRelation para la respuesta de la API.
 * Incluye información de la relación y los campos involucrados.
 */
class KpiFieldRelationResource extends JsonResource
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
            'field_a_id' => $this->field_a_id,
            'field_b_id' => $this->field_b_id,
            'operation' => $this->operation,
            'operation_display' => $this->getOperationDisplay(),
            'field_a_model' => $this->field_a_model,
            'field_b_model' => $this->field_b_model,
            'field_a_conditions' => $this->field_a_conditions,
            'field_b_conditions' => $this->field_b_conditions,
            'multiplier' => $this->multiplier,
            'is_active' => $this->is_active,
            'order' => $this->order,
            'field_a' => new KpiFieldResource($this->whenLoaded('fieldA')),
            'field_b' => new KpiFieldResource($this->whenLoaded('fieldB')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Obtiene el nombre de visualización de la operación.
     *
     * @return string
     */
    private function getOperationDisplay(): string
    {
        $operations = KpiFieldRelation::getAvailableOperations();
        return $operations[$this->operation] ?? $this->operation;
    }
}
