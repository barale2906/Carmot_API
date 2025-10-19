<?php

namespace App\Http\Resources\Api\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource ModelMetadataResource
 *
 * Transforma los metadatos de modelos para la respuesta de la API.
 * Incluye información sobre modelos disponibles para KPIs.
 */
class ModelMetadataResource extends JsonResource
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
            'id' => $this->resource['id'] ?? null,
            'class' => $this->resource['class'] ?? null,
            'display_name' => $this->resource['display_name'] ?? 'Unknown Model',
            'short_name' => $this->resource['class'] ? class_basename($this->resource['class']) : 'Unknown',
            'namespace' => $this->getNamespace(),
            'is_available' => true,
            'fields_count' => $this->getFieldsCount(),
        ];
    }

    /**
     * Obtiene el namespace del modelo.
     *
     * @return string
     */
    private function getNamespace(): string
    {
        if (!isset($this->resource['class']) || !$this->resource['class']) {
            return 'Unknown';
        }

        try {
            $reflection = new \ReflectionClass($this->resource['class']);
            return $reflection->getNamespaceName();
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Obtiene el número de campos disponibles.
     *
     * @return int
     */
    private function getFieldsCount(): int
    {
        if (!isset($this->resource['id'])) {
            return 0;
        }

        $config = config("kpis.available_kpi_models.{$this->resource['id']}");
        return count($config['fields'] ?? []);
    }
}
