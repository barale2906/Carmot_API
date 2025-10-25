<?php

namespace App\Traits;

/**
 * Trait HasGenericScopes
 *
 * Proporciona scopes genéricos para filtros dinámicos en modelos Eloquent.
 * Permite aplicar filtros de forma flexible sin necesidad de crear scopes específicos.
 *
 * @package App\Traits
 */
trait HasGenericScopes
{
    /**
     * Filtro genérico por cualquier campo con operador personalizable.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field Nombre del campo a filtrar
     * @param mixed $value Valor a comparar
     * @param string $operator Operador de comparación (=, !=, >, <, >=, <=, LIKE, etc.)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterBy($query, $field, $value, $operator = '=')
    {
        return $query->where($field, $operator, $value);
    }

    /**
     * Filtro por múltiples valores usando WHERE IN.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field Nombre del campo a filtrar
     * @param array $values Array de valores a buscar
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterByIn($query, $field, $values)
    {
        if (empty($values)) {
            return $query->whereRaw('1 = 0'); // No devolver resultados si no hay valores
        }

        return $query->whereIn($field, $values);
    }

    /**
     * Filtro por rango de fechas usando WHERE BETWEEN.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field Nombre del campo de fecha
     * @param string $startDate Fecha de inicio (formato Y-m-d)
     * @param string $endDate Fecha de fin (formato Y-m-d)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterByDateRange($query, $field, $startDate, $endDate)
    {
        return $query->whereBetween($field, [$startDate, $endDate]);
    }

    /**
     * Filtro por texto usando LIKE con comodines.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field Nombre del campo a filtrar
     * @param string $text Texto a buscar (se agregan comodines automáticamente)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterByText($query, $field, $text)
    {
        return $query->where($field, 'LIKE', "%{$text}%");
    }

    /**
     * Filtro por múltiples condiciones en un solo scope.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $conditions Array de condiciones con estructura:
     *   [
     *     ['field' => 'nombre', 'operator' => '=', 'value' => 'Juan'],
     *     ['field' => 'edad', 'operator' => '>', 'value' => 18]
     *   ]
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterByConditions($query, $conditions)
    {
        foreach ($conditions as $condition) {
            $field = $condition['field'];
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'];

            $query->where($field, $operator, $value);
        }

        return $query;
    }

    /**
     * Filtro por valores nulos o no nulos.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field Nombre del campo a verificar
     * @param bool $isNull Si es true, busca valores NULL; si es false, busca valores no NULL
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterByNull($query, $field, $isNull = true)
    {
        return $isNull ? $query->whereNull($field) : $query->whereNotNull($field);
    }

    /**
     * Filtro por rango numérico.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field Nombre del campo numérico
     * @param float $min Valor mínimo
     * @param float $max Valor máximo
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterByRange($query, $field, $min, $max)
    {
        return $query->whereBetween($field, [$min, $max]);
    }

    /**
     * Filtro por ordenamiento personalizado.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field Campo por el cual ordenar
     * @param string $direction Dirección del ordenamiento (asc, desc)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByField($query, $field, $direction = 'asc')
    {
        return $query->orderBy($field, $direction);
    }
}
