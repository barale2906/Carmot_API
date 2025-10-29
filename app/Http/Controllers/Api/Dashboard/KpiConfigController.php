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
}
