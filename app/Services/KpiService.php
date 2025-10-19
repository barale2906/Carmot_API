<?php

namespace App\Services;

use App\Models\Dashboard\Kpi;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

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
     * @param Carbon $startDate Fecha de inicio del periodo
     * @param Carbon $endDate Fecha de fin del periodo
     * @return float|null Valor calculado del KPI o null si no se puede calcular
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Si el KPI no existe
     */
    public function getKpiValue(int $kpiId, ?int $tenantId, Carbon $startDate, Carbon $endDate): ?float
    {
        $kpi = Kpi::with('kpiFields')->findOrFail($kpiId);

        if (!$kpi->base_model || !class_exists($kpi->base_model)) {
            return null;
        }

        $query = $kpi->base_model::query();
        //$query = $kpi->base_model::where('tenant_id', $tenantId);

        // Aplicar filtro de tenant si está disponible
        if ($tenantId && method_exists($kpi->base_model, 'where')) {
            $query->where('tenant_id', $tenantId);
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

        // Ejecutar la operación principal
        return $this->executeMainOperation($query, $mainOperation, $mainOperationField);
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
        if (!$kpi || !$kpi->base_model) {
            return false;
        }

        $kpiMetadataService = app(\App\Services\KpiMetadataService::class);
        $allowedFields = $kpiMetadataService->getModelFieldsByClass($kpi->base_model);
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
