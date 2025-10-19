<?php

namespace App\Http\Resources\Api\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource FieldMetadataResource
 *
 * Transforma los metadatos de campos de modelos para la respuesta de la API.
 * Incluye información sobre campos disponibles para KPIs.
 */
class FieldMetadataResource extends JsonResource
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
            'name' => $this->resource['name'],
            'display_name' => $this->resource['display_name'],
            'type' => $this->resource['type'],
            'is_primary_key' => $this->isPrimaryKey(),
            'is_timestamp' => $this->isTimestamp(),
            'is_foreign_key' => $this->isForeignKey(),
            'is_searchable' => $this->isSearchable(),
            'is_sortable' => $this->isSortable(),
            'is_filterable' => $this->isFilterable(),
            'suggested_operations' => $this->getSuggestedOperations(),
            'validation_rules' => $this->getValidationRules(),
        ];
    }

    /**
     * Determina si el campo es una clave primaria.
     *
     * @return bool
     */
    private function isPrimaryKey(): bool
    {
        return $this->resource['name'] === 'id';
    }

    /**
     * Determina si el campo es un timestamp.
     *
     * @return bool
     */
    private function isTimestamp(): bool
    {
        return in_array($this->resource['name'], ['created_at', 'updated_at', 'deleted_at']);
    }

    /**
     * Determina si el campo es una clave foránea.
     *
     * @return bool
     */
    private function isForeignKey(): bool
    {
        return str_ends_with($this->resource['name'], '_id');
    }

    /**
     * Determina si el campo es buscable.
     *
     * @return bool
     */
    private function isSearchable(): bool
    {
        return in_array($this->resource['type'], ['string', 'text']) &&
               !$this->isTimestamp() &&
               !$this->isPrimaryKey();
    }

    /**
     * Determina si el campo es ordenable.
     *
     * @return bool
     */
    private function isSortable(): bool
    {
        return !$this->isTimestamp() || $this->resource['name'] === 'created_at';
    }

    /**
     * Determina si el campo es filtrable.
     *
     * @return bool
     */
    private function isFilterable(): bool
    {
        return !$this->isPrimaryKey() &&
               !in_array($this->resource['name'], ['created_at', 'updated_at']);
    }

    /**
     * Obtiene las operaciones sugeridas para el campo.
     *
     * @return array<string>
     */
    private function getSuggestedOperations(): array
    {
        $operations = [];

        switch ($this->resource['type']) {
            case 'integer':
            case 'decimal':
            case 'float':
                $operations = ['sum', 'count', 'avg', 'min', 'max'];
                break;
            case 'string':
            case 'text':
                $operations = ['count', 'where'];
                break;
            case 'boolean':
                $operations = ['count', 'where'];
                break;
            case 'date':
            case 'datetime':
                $operations = ['count', 'where'];
                break;
            default:
                $operations = ['count'];
        }

        return $operations;
    }

    /**
     * Obtiene las reglas de validación sugeridas.
     *
     * @return array<string>
     */
    private function getValidationRules(): array
    {
        $rules = [];

        switch ($this->resource['type']) {
            case 'integer':
                $rules[] = 'integer';
                break;
            case 'decimal':
            case 'float':
                $rules[] = 'numeric';
                break;
            case 'string':
                $rules[] = 'string';
                break;
            case 'boolean':
                $rules[] = 'boolean';
                break;
            case 'date':
                $rules[] = 'date';
                break;
            case 'datetime':
                $rules[] = 'date';
                break;
        }

        if ($this->isRequired()) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        return $rules;
    }

    /**
     * Determina si el campo es requerido.
     *
     * @return bool
     */
    private function isRequired(): bool
    {
        return $this->isPrimaryKey() ||
               in_array($this->resource['name'], ['name', 'title', 'email']);
    }
}
