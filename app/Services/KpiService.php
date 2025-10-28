<?php

namespace App\Services;

use App\Models\Dashboard\Kpi;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use App\Services\DynamicFilterService;

/**
 * Servicio KpiService
 *
 * Maneja la lógica de negocio para el cálculo de KPIs.
 * Proporciona métodos para calcular valores de KPIs basados en configuraciones de campos.
 */
class KpiService
{
    /**
     * Calcula el valor de un KPI para un tenant específico en un rango de fechas.
     *
     * @param int $kpiId ID del KPI a calcular
     * @param int|null $tenantId ID del tenant (opcional, puede ser null)
     * @param Carbon|null $startDate Fecha de inicio del periodo (opcional, usa el rango por defecto del KPI si no se especifica)
     * @param Carbon|null $endDate Fecha de fin del periodo (opcional, usa el rango por defecto del KPI si no se especifica)
     * @return float|null Valor calculado del KPI o null si no se puede calcular
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Si el KPI no existe
     */
    public function getKpiValue(int $kpiId, ?int $tenantId, ?Carbon $startDate = null, ?Carbon $endDate = null): ?float
    {
        $kpi = Kpi::with(['kpiFields', 'fieldRelations.fieldA', 'fieldRelations.fieldB'])->findOrFail($kpiId);

        if (!$kpi->hasValidBaseModel()) {
            return null;
        }

        // Si no se proporcionan fechas, usar el rango por defecto del KPI
        if ($startDate === null || $endDate === null) {
            $defaultRange = $kpi->getDefaultTimeRange();
            $startDate = $startDate ?? $defaultRange['start'];
            $endDate = $endDate ?? $defaultRange['end'];
        }

        // Verificar si hay relaciones entre campos
        $activeRelations = $kpi->fieldRelations()->where('is_active', true)->orderBy('order')->get();

        if ($activeRelations->isNotEmpty()) {
            return $this->calculateKpiWithRelations($kpi, $activeRelations, $tenantId, $startDate, $endDate);
        }

        // Si no hay relaciones, usar el método tradicional
        return $this->calculateKpiTraditional($kpi, $tenantId, $startDate, $endDate, []);
    }

    /**
     * Calcula el valor de un KPI para una tarjeta de dashboard específica.
     *
     * @param int $kpiId ID del KPI a calcular
     * @param int $cardId ID de la tarjeta de dashboard
     * @param int|null $tenantId ID del tenant (opcional)
     * @return float|null Valor calculado del KPI o null si no se puede calcular
     */
    public function getKpiValueForCard(int $kpiId, int $cardId, ?int $tenantId = null): ?float
    {
        $kpi = Kpi::with(['kpiFields', 'fieldRelations.fieldA', 'fieldRelations.fieldB'])->findOrFail($kpiId);
        $card = \App\Models\Dashboard\DashboardCard::findOrFail($cardId);

        if (!$kpi->hasValidBaseModel()) {
            return null;
        }

        // Usar fechas de la tarjeta si están definidas, sino usar las fechas por defecto del KPI
        $startDate = $card->period_start_date ? Carbon::parse($card->period_start_date) : null;
        $endDate = $card->period_end_date ? Carbon::parse($card->period_end_date) : null;

        // Si no hay fechas específicas, usar el rango por defecto del KPI
        if ($startDate === null || $endDate === null) {
            $defaultRange = $kpi->getDefaultTimeRange();
            $startDate = $startDate ?? $defaultRange['start'];
            $endDate = $endDate ?? $defaultRange['end'];
        }

        // Verificar si hay relaciones entre campos
        $activeRelations = $kpi->fieldRelations()->where('is_active', true)->orderBy('order')->get();

        if ($activeRelations->isNotEmpty()) {
            return $this->calculateKpiWithRelations($kpi, $activeRelations, $tenantId, $startDate, $endDate, $card->getFilters());
        }

        // Si no hay relaciones, usar el método tradicional con filtros de la tarjeta
        return $this->calculateKpiTraditional($kpi, $tenantId, $startDate, $endDate, $card->getFilters());
    }

    /**
     * Calcula un KPI usando el método tradicional (sin relaciones).
     *
     * @param \App\Models\Dashboard\Kpi $kpi
     * @param int|null $tenantId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array $additionalFilters Filtros adicionales de la dashboard card
     * @return float|null
     */
    private function calculateKpiTraditional($kpi, ?int $tenantId, Carbon $startDate, Carbon $endDate, array $additionalFilters = []): ?float
    {
        $modelClass = $kpi->getBaseModelClass();
        $query = $modelClass::query();

        // Aplicar filtro de tenant si está disponible
        if ($tenantId && method_exists($modelClass, 'where')) {
            $query->where('tenant_id', $tenantId);
        }

        // Para KPIs predefined sin campos, usar COUNT por defecto
        if ($kpi->calculation_type === 'predefined' && $kpi->kpiFields->isEmpty()) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
            return $query->count();
        }

        $mainOperationField = null;
        $mainOperation = null;

        // Procesar cada campo de configuración del KPI
        foreach ($kpi->kpiFields as $field) {
            switch ($field->operation) {
                case 'where':
                    $this->applyWhereCondition($query, $field);
                    break;
                case 'sum':
                case 'count':
                case 'avg':
                case 'min':
                case 'max':
                    $mainOperation = $field->operation;
                    $mainOperationField = $field->field_name;
                    break;
            }
        }

        // Aplicar filtro de fechas
        $query->whereBetween('created_at', [$startDate, $endDate]);

        // Aplicar filtros adicionales de la dashboard card usando DynamicFilterService
        if (!empty($additionalFilters)) {
            $dynamicFilterService = new DynamicFilterService();
            $query = $dynamicFilterService->applyFilters($query, $additionalFilters);
        }

        // Ejecutar la operación principal
        return $this->executeMainOperation($query, $mainOperation, $mainOperationField);
    }

    /**
     * Calcula un KPI con relaciones entre campos.
     *
     * @param \App\Models\Dashboard\Kpi $kpi
     * @param \Illuminate\Database\Eloquent\Collection $relations
     * @param int|null $tenantId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float|null
     */
    private function calculateKpiWithRelations($kpi, $relations, ?int $tenantId, Carbon $startDate, Carbon $endDate): ?float
    {
        $fieldValues = [];

        // Calcular el valor de cada campo involucrado en las relaciones
        foreach ($relations as $relation) {
            if (!$relation->isValid()) {
                continue;
            }

            // Calcular valor del campo A si no se ha calculado
            if (!isset($fieldValues[$relation->field_a_id])) {
                $fieldValues[$relation->field_a_id] = $this->calculateFieldValue(
                    $relation->fieldA,
                    $relation->getFieldAModel(),
                    $relation->field_a_conditions,
                    $tenantId,
                    $startDate,
                    $endDate
                );
            }

            // Calcular valor del campo B si no se ha calculado
            if (!isset($fieldValues[$relation->field_b_id])) {
                $fieldValues[$relation->field_b_id] = $this->calculateFieldValue(
                    $relation->fieldB,
                    $relation->getFieldBModel(),
                    $relation->field_b_conditions,
                    $tenantId,
                    $startDate,
                    $endDate
                );
            }

            // Realizar la operación entre los campos
            $valueA = $fieldValues[$relation->field_a_id];
            $valueB = $fieldValues[$relation->field_b_id];

            if ($valueA === null || $valueB === null) {
                continue;
            }

            $result = $this->executeFieldRelation($valueA, $valueB, $relation->operation);

            if ($result !== null) {
                $result *= $relation->multiplier;
                $fieldValues[$relation->id] = $result;
            }
        }

        // Retornar el resultado de la última relación o el primer valor calculado
        return $fieldValues[array_key_last($fieldValues)] ?? null;
    }

    /**
     * Calcula el valor de un campo específico.
     *
     * @param \App\Models\Dashboard\KpiField $field
     * @param string $modelClass
     * @param array|null $conditions
     * @param int|null $tenantId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float|null
     */
    private function calculateFieldValue($field, string $modelClass, ?array $conditions, ?int $tenantId, Carbon $startDate, Carbon $endDate): ?float
    {
        if (!class_exists($modelClass)) {
            return null;
        }

        $query = $modelClass::query();

        // Aplicar filtro de tenant si está disponible
        if ($tenantId && method_exists($modelClass, 'where')) {
            $query->where('tenant_id', $tenantId);
        }

        // Aplicar condiciones adicionales
        if ($conditions) {
            foreach ($conditions as $condition) {
                $query->where($condition['field'], $condition['operator'], $condition['value']);
            }
        }

        // Aplicar filtro de fechas
        $query->whereBetween('created_at', [$startDate, $endDate]);

        // Ejecutar la operación del campo
        return $this->executeMainOperation($query, $field->operation, $field->field_name);
    }

    /**
     * Ejecuta una operación entre dos valores.
     *
     * @param float $valueA
     * @param float $valueB
     * @param string $operation
     * @return float|null
     */
    private function executeFieldRelation(float $valueA, float $valueB, string $operation): ?float
    {
        switch ($operation) {
            case 'divide':
                return $valueB != 0 ? $valueA / $valueB : null;
            case 'multiply':
                return $valueA * $valueB;
            case 'add':
                return $valueA + $valueB;
            case 'subtract':
                return $valueA - $valueB;
            case 'percentage':
                return $valueB != 0 ? ($valueA / $valueB) * 100 : null;
            default:
                return null;
        }
    }

    /**
     * Aplica una condición WHERE a la consulta.
     *
     * @param Builder $query Consulta a modificar
     * @param \App\Models\Dashboard\KpiField $field Campo de configuración
     * @return void
     */
    private function applyWhereCondition(Builder $query, $field): void
    {
        // Validar que el campo esté permitido para el modelo
        if (!$this->isFieldAllowed($field)) {
            throw new \InvalidArgumentException("El campo '{$field->field_name}' no está permitido para este modelo.");
        }

        $value = $field->value;

        // Convertir el valor según el tipo de campo
        if ($field->field_type === 'boolean') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } elseif ($field->field_type === 'numeric') {
            $value = (float) $value;
        }

        // Validar operador para prevenir inyección SQL
        $allowedOperators = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];
        if (!in_array($field->operator, $allowedOperators)) {
            throw new \InvalidArgumentException("El operador '{$field->operator}' no está permitido.");
        }

        $query->where($field->field_name, $field->operator, $value);
    }

    /**
     * Valida que el campo esté permitido para el modelo.
     *
     * @param \App\Models\Dashboard\KpiField $field
     * @return bool
     */
    private function isFieldAllowed($field): bool
    {
        $kpi = $field->kpi;
        if (!$kpi || !$kpi->hasValidBaseModel()) {
            return false;
        }

        $allowedFields = $kpi->getBaseModelFields();
        return in_array($field->field_name, $allowedFields);
    }

    /**
     * Ejecuta la operación principal sobre la consulta.
     *
     * @param Builder $query Consulta preparada
     * @param string|null $operation Operación a ejecutar
     * @param string|null $field Campo sobre el que aplicar la operación
     * @return float|null Resultado de la operación
     */
    private function executeMainOperation(Builder $query, ?string $operation, ?string $field): ?float
    {
        if (!$operation) {
            return null;
        }

        switch ($operation) {
            case 'sum':
                return $query->sum($field);
            case 'count':
                return $field ? $query->count($field) : $query->count();
            case 'avg':
                return $query->avg($field);
            case 'min':
                return $query->min($field);
            case 'max':
                return $query->max($field);
            default:
                return null;
        }
    }
}
