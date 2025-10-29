<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Dashboard\Kpi;

/**
 * Middleware ValidateKpiSecurity
 *
 * Valida la seguridad de las peticiones relacionadas con KPIs.
 * Asegura que solo se usen modelos y campos permitidos.
 */
class ValidateKpiSecurity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Validar modelos numerador y denominador si vienen en el request
        $numeratorModel = $request->input('numerator_model');
        $denominatorModel = $request->input('denominator_model');

        // Si la ruta tiene un KPI (compute), tomar modelos desde ese KPI
        $routeKpi = $request->route('kpi');
        if ($routeKpi instanceof Kpi) {
            $numeratorModel = $numeratorModel ?? $routeKpi->numerator_model;
            $denominatorModel = $denominatorModel ?? $routeKpi->denominator_model;
        }

        if (!is_null($numeratorModel)) {
            $this->validateModelId((int)$numeratorModel);
        }
        if (!is_null($denominatorModel)) {
            $this->validateModelId((int)$denominatorModel);
        }

        // Validar campos y operaciones si vienen
        $numeratorField = $request->input('numerator_field');
        $denominatorField = $request->input('denominator_field');
        $numeratorOp = $request->input('numerator_operation');
        $denominatorOp = $request->input('denominator_operation');

        if (!is_null($numeratorModel)) {
            $this->validateFieldInModel((int)$numeratorModel, $numeratorField);
            $this->validateOperation((string)$numeratorOp, $numeratorField, (int)$numeratorModel);
        }

        if (!is_null($denominatorModel)) {
            $this->validateFieldInModel((int)$denominatorModel, $denominatorField);
            $this->validateOperation((string)$denominatorOp, $denominatorField, (int)$denominatorModel);
        }

        // Validar date_field si viene: debe existir en date_fields de alguno de los modelos
        if ($request->filled('date_field') && (!is_null($numeratorModel) || !is_null($denominatorModel))) {
            $this->validateDateField($request->input('date_field'), $numeratorModel, $denominatorModel);
        }

        // Validar group_by si viene en compute
        if ($request->filled('group_by') && (!is_null($numeratorModel) || !is_null($denominatorModel))) {
            $groupBy = $request->input('group_by');
            $this->validateFieldInEitherModel($numeratorModel, $denominatorModel, $groupBy);
        }

        // Validar filtros: keys deben existir en alguno de los modelos
        if (is_array($request->input('filters'))) {
            foreach (array_keys($request->input('filters')) as $filterField) {
                $this->validateFieldInEitherModel($numeratorModel, $denominatorModel, $filterField);
            }
        }

        return $next($request);
    }

    /**
     * Valida que el ID de modelo exista en la configuración.
     *
     * @param int $modelId
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateModelId(int $modelId): void
    {
        $availableModels = config('kpis.available_kpi_models', []);

        if (!array_key_exists($modelId, $availableModels)) {
            throw new \InvalidArgumentException("El modelo con ID '{$modelId}' no está permitido para KPIs.");
        }
    }

    /**
     * Valida que el campo exista para el modelo indicado.
     *
     * @param int $modelId
     * @param string|null $fieldName
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateFieldInModel(int $modelId, ?string $fieldName): void
    {
        // Campo opcional: si no viene (p.ej. count(*)), no validar
        if (empty($fieldName)) {
            return;
        }

        $availableModels = config('kpis.available_kpi_models', []);
        $modelConfig = $availableModels[$modelId] ?? null;

        if (!$modelConfig || !isset($modelConfig['fields'])) {
            throw new \InvalidArgumentException("Configuración no válida para el modelo seleccionado.");
        }

        // c/fields puede ser array de meta {label,type}
        $allowedFields = array_keys($modelConfig['fields']);

        if (!in_array($fieldName, $allowedFields)) {
            throw new \InvalidArgumentException("El campo '{$fieldName}' no está permitido para el modelo seleccionado.");
        }
    }

    /**
     * Valida que un campo exista en alguno de los dos modelos proporcionados.
     */
    private function validateFieldInEitherModel(?int $numeratorModel, ?int $denominatorModel, string $fieldName): void
    {
        $errors = 0;
        try {
            if (!is_null($numeratorModel)) {
                $this->validateFieldInModel((int)$numeratorModel, $fieldName);
                return;
            }
        } catch (\Throwable) {
            $errors++;
        }

        try {
            if (!is_null($denominatorModel)) {
                $this->validateFieldInModel((int)$denominatorModel, $fieldName);
                return;
            }
        } catch (\Throwable) {
            $errors++;
        }

        if ($errors > 0) {
            throw new \InvalidArgumentException("El campo '{$fieldName}' no es válido para los modelos seleccionados.");
        }
    }

    /**
     * Valida que la operación sea válida para el tipo del campo.
     * Si no hay campo (count(*)), solo se permite 'count'.
     */
    private function validateOperation(string $operation, ?string $fieldName, int $modelId): void
    {
        $allowedOps = config('kpis.allowed_operations', []);
        $models = config('kpis.available_kpi_models', []);
        $model = $models[$modelId] ?? null;

        if (!$model) {
            throw new \InvalidArgumentException("Modelo no válido para operación.");
        }

        if (empty($fieldName)) {
            if ($operation !== 'count') {
                throw new \InvalidArgumentException("La operación '{$operation}' requiere un campo válido.");
            }
            return;
        }

        $fieldMeta = $model['fields'][$fieldName] ?? null;
        $type = is_array($fieldMeta) ? ($fieldMeta['type'] ?? 'string') : 'string';
        $opsForType = $allowedOps[$type] ?? ['count'];

        if (!in_array($operation, $opsForType)) {
            throw new \InvalidArgumentException("La operación '{$operation}' no está permitida para campos de tipo '{$type}'.");
        }
    }

    /**
     * Valida que el campo de fecha exista en alguno de los modelos seleccionados.
     */
    private function validateDateField(string $dateField, ?int $numeratorModel, ?int $denominatorModel): void
    {
        $models = config('kpis.available_kpi_models', []);
        $valid = false;

        foreach ([$numeratorModel, $denominatorModel] as $id) {
            if (is_null($id)) continue;
            $cfg = $models[$id] ?? null;
            $dateFields = $cfg['date_fields'] ?? [];
            if (in_array($dateField, $dateFields)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            throw new \InvalidArgumentException("El campo de fecha '{$dateField}' no es válido para los modelos seleccionados.");
        }
    }
}
