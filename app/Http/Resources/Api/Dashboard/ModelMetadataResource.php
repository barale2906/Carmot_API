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
            'class' => $this->resource['class'],
            'display_name' => $this->resource['display_name'],
            'short_name' => class_basename($this->resource['class']),
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
        $reflection = new \ReflectionClass($this->resource['class']);
        return $reflection->getNamespaceName();
    }

    /**
     * Obtiene el número de campos disponibles.
     *
     * @return int
     */
    private function getFieldsCount(): int
    {
        $config = config("kpis.available_kpi_models.{$this->resource['class']}");
        return count($config['fields'] ?? []);
    }
}
