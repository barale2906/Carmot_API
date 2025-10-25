<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Servicio DynamicFilterService
 *
 * Maneja la aplicación de filtros dinámicos a modelos Eloquent.
 * Permite aplicar filtros de forma flexible basándose en configuración JSON.
 *
 * @package App\Services
 */
class DynamicFilterService
{
    /**
     * Aplica filtros dinámicos a cualquier modelo.
     *
     * @param string $modelClass Clase del modelo a filtrar
     * @param array $filters Array de filtros con estructura:
     *   [
     *     ['field' => 'nombre', 'type' => 'exact', 'value' => 'Juan', 'operator' => '='],
     *     ['field' => 'edad', 'type' => 'range', 'value' => ['min' => 18, 'max' => 65]]
     *   ]
     * @return \Illuminate\Database\Eloquent\Builder Query builder con filtros aplicados
     * @throws \InvalidArgumentException Si el modelo no existe o no es válido
     */
    public function applyFilters($modelClass, $filters = [])
    {
        if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException("La clase de modelo '{$modelClass}' no es válida.");
        }

        $query = $modelClass::query();

        if (empty($filters)) {
            return $query;
        }

        foreach ($filters as $filter) {
            try {
                $this->applyFilter($query, $filter);
            } catch (\Exception $e) {
                Log::warning("Error aplicando filtro dinámico", [
                    'filter' => $filter,
                    'error' => $e->getMessage()
                ]);
                // Continuar con el siguiente filtro en caso de error
            }
        }

        return $query;
    }

    /**
     * Aplica un filtro específico al query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query Query builder
     * @param array $filter Configuración del filtro
     * @return \Illuminate\Database\Eloquent\Builder Query builder modificado
     * @throws \InvalidArgumentException Si el filtro no tiene la estructura correcta
     */
    private function applyFilter($query, $filter)
    {
        if (!isset($filter['field']) || !isset($filter['type']) || !isset($filter['value'])) {
            throw new \InvalidArgumentException("El filtro debe contener 'field', 'type' y 'value'.");
        }

        $field = $filter['field'];
        $value = $filter['value'];
        $operator = $filter['operator'] ?? '=';

        switch ($filter['type']) {
            case 'exact':
                $query->filterBy($field, $value, $operator);
                break;

            case 'in':
                if (!is_array($value)) {
                    throw new \InvalidArgumentException("El valor para filtro 'in' debe ser un array.");
                }
                $query->filterByIn($field, $value);
                break;

            case 'date_range':
                if (!isset($value['start']) || !isset($value['end'])) {
                    throw new \InvalidArgumentException("El filtro 'date_range' requiere 'start' y 'end'.");
                }
                $query->filterByDateRange($field, $value['start'], $value['end']);
                break;

            case 'text':
                $query->filterByText($field, $value);
                break;

            case 'multiple':
                if (!is_array($value)) {
                    throw new \InvalidArgumentException("El valor para filtro 'multiple' debe ser un array.");
                }
                $query->filterByConditions($value);
                break;

            case 'null':
                $isNull = $value === true || $value === 'true' || $value === 1;
                $query->filterByNull($field, $isNull);
                break;

            case 'range':
                if (!isset($value['min']) || !isset($value['max'])) {
                    throw new \InvalidArgumentException("El filtro 'range' requiere 'min' y 'max'.");
                }
                $query->filterByRange($field, $value['min'], $value['max']);
                break;

            case 'custom':
                // Para filtros personalizados que requieren lógica específica
                $this->applyCustomFilter($query, $filter);
                break;

            default:
                throw new \InvalidArgumentException("Tipo de filtro '{$filter['type']}' no soportado.");
        }

        return $query;
    }

    /**
     * Aplica filtros personalizados con lógica específica.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query Query builder
     * @param array $filter Configuración del filtro personalizado
     * @return \Illuminate\Database\Eloquent\Builder Query builder modificado
     */
    private function applyCustomFilter($query, $filter)
    {
        $field = $filter['field'];
        $value = $filter['value'];
        $operator = $filter['operator'] ?? '=';

        // Aquí puedes agregar lógica específica para filtros personalizados
        // Por ejemplo, filtros que requieren joins o subconsultas

        switch ($filter['custom_type'] ?? '') {
            case 'has_relation':
                $query->whereHas($field, function ($q) use ($value) {
                    $q->where($value['field'], $value['operator'] ?? '=', $value['value']);
                });
                break;

            case 'doesnt_have_relation':
                $query->whereDoesntHave($field, function ($q) use ($value) {
                    $q->where($value['field'], $value['operator'] ?? '=', $value['value']);
                });
                break;

            default:
                // Filtro genérico como fallback
                $query->where($field, $operator, $value);
                break;
        }

        return $query;
    }

    /**
     * Valida la estructura de un filtro.
     *
     * @param array $filter Filtro a validar
     * @return bool True si el filtro es válido
     */
    public function validateFilter($filter)
    {
        $requiredFields = ['field', 'type', 'value'];

        foreach ($requiredFields as $field) {
            if (!isset($filter[$field])) {
                return false;
            }
        }

        $validTypes = ['exact', 'in', 'date_range', 'text', 'multiple', 'null', 'range', 'custom'];

        return in_array($filter['type'], $validTypes);
    }

    /**
     * Obtiene los tipos de filtros disponibles.
     *
     * @return array Array con los tipos de filtros soportados
     */
    public function getAvailableFilterTypes()
    {
        return [
            'exact' => [
                'name' => 'Exacto',
                'description' => 'Búsqueda exacta por valor',
                'requires' => ['field', 'value', 'operator (opcional)']
            ],
            'in' => [
                'name' => 'En lista',
                'description' => 'Búsqueda por múltiples valores',
                'requires' => ['field', 'value (array)']
            ],
            'date_range' => [
                'name' => 'Rango de fechas',
                'description' => 'Filtro por rango de fechas',
                'requires' => ['field', 'value (start, end)']
            ],
            'text' => [
                'name' => 'Texto',
                'description' => 'Búsqueda de texto con comodines',
                'requires' => ['field', 'value']
            ],
            'multiple' => [
                'name' => 'Múltiples condiciones',
                'description' => 'Aplicar múltiples condiciones',
                'requires' => ['field', 'value (array de condiciones)']
            ],
            'null' => [
                'name' => 'Nulo/No nulo',
                'description' => 'Filtrar por valores nulos o no nulos',
                'requires' => ['field', 'value (boolean)']
            ],
            'range' => [
                'name' => 'Rango numérico',
                'description' => 'Filtro por rango numérico',
                'requires' => ['field', 'value (min, max)']
            ]
        ];
    }
}
