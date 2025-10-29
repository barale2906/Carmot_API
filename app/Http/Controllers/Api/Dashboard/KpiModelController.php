<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para operaciones con modelos de KPIs
 *
 * Proporciona endpoints para obtener opciones de agrupación
 * y filtros dinámicos basados en los modelos configurados.
 */
class KpiModelController extends Controller
{
    /**
     * Obtiene opciones de agrupación para un campo específico de un modelo.
     *
     * Útil para poblar selects de group_by y filtros en el frontend.
     * Ejemplo: GET /dashboard/kpis/models/1/group-by/curso_id?filters[sede_id]=10
     *
     * @param Request $request
     * @param int $modelId ID del modelo en config/kpis.php
     * @param string $field Campo por el cual agrupar
     * @return JsonResponse
     */
    public function groupBy(Request $request, int $modelId, string $field): JsonResponse
    {
        // Validar que el modelo existe
        $modelConfig = $this->getModelConfig($modelId);
        if (!$modelConfig) {
            return response()->json(['error' => 'Modelo no encontrado'], 404);
        }

        // Validar que el campo existe en el modelo
        if (!array_key_exists($field, $modelConfig['fields'])) {
            return response()->json(['error' => 'Campo no válido para este modelo'], 400);
        }

        // Obtener la clase del modelo
        $modelClass = $modelConfig['class'];
        if (!class_exists($modelClass)) {
            return response()->json(['error' => 'Clase de modelo no encontrada'], 500);
        }

        // Construir query con GROUP BY
        $query = $modelClass::query();

        // Aplicar filtros si vienen
        $filters = $request->input('filters', []);
        foreach ($filters as $filterField => $filterValue) {
            if ($filterValue !== null && $filterValue !== '') {
                $query->where($filterField, $filterValue);
            }
        }

        // Aplicar filtro de fecha si viene
        if ($request->filled('date_field') && $request->filled('start_date') && $request->filled('end_date')) {
            $dateField = $request->input('date_field');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $query->whereBetween($dateField, [$startDate, $endDate]);
        }

        // Agrupar por el campo solicitado y contar
        $results = $query
            ->select($field)
            ->selectRaw('COUNT(*) as count')
            ->groupBy($field)
            ->orderBy($field, 'asc')
            ->limit($request->integer('limit', 100))
            ->get();

        // Construir opciones
        $options = [];
        foreach ($results as $result) {
            $value = $result->{$field};
            $label = $this->getFieldLabel($modelConfig, $field, $value);

            $options[] = [
                'value' => $value,
                'label' => $label,
                'count' => $result->count,
            ];
        }

        return response()->json([
            'field' => $field,
            'model' => [
                'id' => $modelId,
                'display_name' => $modelConfig['display_name'],
            ],
            'options' => $options,
            'total' => count($options),
        ]);
    }

    /**
     * Obtiene la configuración de un modelo desde config/kpis.php
     *
     * @param int $modelId
     * @return array|null
     */
    private function getModelConfig(int $modelId): ?array
    {
        $models = config('kpis.available_kpi_models', []);
        return $models[$modelId] ?? null;
    }

    /**
     * Genera una etiqueta legible para un valor de campo
     * Resuelve automáticamente las relaciones definidas en el modelo Eloquent
     *
     * @param array $modelConfig
     * @param string $field
     * @param mixed $value
     * @return string
     */
    private function getFieldLabel(array $modelConfig, string $field, $value): string
    {
        // Si el campo termina en _id, intentar resolver la relación
        if (str_ends_with($field, '_id')) {
            $relationName = str_replace('_id', '', $field);
            $modelClass = $modelConfig['class'];

            try {
                // Verificar si el modelo tiene la relación definida
                $model = new $modelClass();
                if (method_exists($model, $relationName)) {
                    // Obtener el modelo relacionado
                    $relatedModel = $model->{$relationName}()->getRelated();
                    $relatedRecord = $relatedModel->find($value);

                    if ($relatedRecord) {
                        // Intentar obtener un campo 'name' o 'nombre' o el primer campo de texto
                        $labelField = $this->getLabelField($relatedRecord);
                        $label = $relatedRecord->{$labelField} ?? (string)$value;
                        return "{$value} - {$label}";
                    }
                }
            } catch (\Exception $e) {
                // Si hay error en la relación, usar solo el valor
            }
        }

        return (string)$value;
    }

    /**
     * Obtiene el campo más apropiado para usar como etiqueta
     * Busca campos como 'name', 'nombre', 'title', 'titulo' o el primer campo de texto
     *
     * @param mixed $model
     * @return string
     */
    private function getLabelField($model): string
    {
        $preferredFields = ['name', 'nombre', 'title', 'titulo', 'descripcion', 'description'];

        // Buscar campos preferidos
        foreach ($preferredFields as $field) {
            if (isset($model->{$field})) {
                return $field;
            }
        }

        // Si no encuentra campos preferidos, buscar el primer campo de texto
        $attributes = $model->getAttributes();
        foreach ($attributes as $key => $value) {
            if (is_string($value) && !in_array($key, ['id', 'created_at', 'updated_at'])) {
                return $key;
            }
        }

        // Fallback al primer campo disponible
        $firstField = array_key_first($attributes);
        return $firstField ?? 'id';
    }
}
