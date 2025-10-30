<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Controlador de configuración de KPIs
 *
 * Expone la configuración necesaria para construir KPIs en el frontend,
 * incluyendo modelos disponibles, operaciones permitidas y presets de periodos.
 */
class KpiConfigController extends Controller
{
    /**
     * Devuelve configuración normalizada para construir KPIs en el frontend.
     *
     * Estructura de respuesta:
     * - models: Lista de modelos disponibles con metadatos
     * - allowed_operations: Operaciones permitidas por tipo de campo
     * - period_presets: Periodos soportados por defecto
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $response = Cache::remember('kpi_config_payload', 3600, function () {
            $config = config('kpis');

            $models = [];
            foreach ($config['available_kpi_models'] as $id => $model) {
                $fields = [];
                foreach ($model['fields'] as $key => $meta) {
                    // Soporta tanto formato antiguo (label string) como nuevo (array con label/type)
                    if (is_array($meta)) {
                        $fields[] = [
                            'key' => $key,
                            'label' => $meta['label'] ?? $key,
                            'type' => $meta['type'] ?? 'string',
                        ];
                    } else {
                        $fields[] = [
                            'key' => $key,
                            'label' => $meta,
                            'type' => 'string',
                        ];
                    }
                }

                $models[] = [
                    'id' => $id,
                    'display_name' => $model['display_name'] ?? ('Model ' . $id),
                    'class' => $model['class'] ?? null,
                    'display_field' => $model['display_field'] ?? null,
                    'date_fields' => $model['date_fields'] ?? [],
                    'default_date_field' => $model['default_date_field'] ?? null,
                    'fields' => $fields,
                ];
            }

            return [
                'models' => $models,
                'allowed_operations' => $config['allowed_operations'] ?? [],
                'period_presets' => $config['period_presets'] ?? [],
            ];
        });

        return response()->json($response);
    }

    /**
     * Lista de modelos disponibles (id y nombre para mostrar).
     */
    public function models(): JsonResponse
    {
        $modelsConfig = config('kpis.available_kpi_models', []);

        $models = [];
        foreach ($modelsConfig as $id => $model) {
            $models[] = [
                'id' => $id,
                'display_name' => $model['display_name'] ?? ('Model ' . $id),
            ];
        }

        return response()->json(['data' => $models]);
    }

    /**
     * Campos del modelo elegido por id.
     */
    public function modelFields(int $modelId): JsonResponse
    {
        $modelsConfig = config('kpis.available_kpi_models', []);
        $model = $modelsConfig[$modelId] ?? null;

        if (!$model) {
            return response()->json([
                'message' => 'Modelo no encontrado',
            ], 404);
        }

        $fields = [];
        foreach ($model['fields'] as $key => $meta) {
            $fields[] = [
                'key' => $key,
                'label' => $meta['label'] ?? $key,
                'type' => $meta['type'] ?? 'string',
            ];
        }

        return response()->json([
            'model' => [
                'id' => $modelId,
                'display_name' => $model['display_name'] ?? ('Model ' . $modelId),
            ],
            'fields' => $fields,
        ]);
    }

    /**
     * Operaciones permitidas para un tipo de campo (e.g., integer, string, datetime).
     */
    public function operationsByType(string $fieldType): JsonResponse
    {
        $allowed = config('kpis.allowed_operations', []);
        $ops = $allowed[$fieldType] ?? [];

        return response()->json([
            'field_type' => $fieldType,
            'operations' => array_values($ops),
        ]);
    }

    /**
     * Periodos disponibles con etiquetas en Español.
     */
    public function periods(): JsonResponse
    {
        $presets = config('kpis.period_presets', []);

        $labelsEs = [
            'daily' => 'Diario',
            'weekly' => 'Semanal',
            'monthly' => 'Mensual',
            'quarterly' => 'Trimestral',
            'yearly' => 'Anual',
        ];

        $data = [];
        foreach ($presets as $key) {
            $data[] = [
                'key' => $key,
                'label' => $labelsEs[$key] ?? $key,
            ];
        }

        return response()->json(['data' => $data]);
    }
}
