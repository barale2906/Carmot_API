<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\ChartDataService;
use App\Services\DynamicFilterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controlador ChartDataController
 *
 * Maneja la obtención de datos para gráficos de KPIs.
 * Proporciona endpoints para generar datos agrupados y formateados para diferentes tipos de gráficos.
 *
 * @package App\Http\Controllers\Api\Dashboard
 */
class ChartDataController extends Controller
{
    protected $chartDataService;
    protected $dynamicFilterService;

    public function __construct(ChartDataService $chartDataService, DynamicFilterService $dynamicFilterService)
    {
        $this->chartDataService = $chartDataService;
        $this->dynamicFilterService = $dynamicFilterService;
    }

    /**
     * Obtiene datos para gráficos de un KPI específico.
     *
     * @param Request $request Datos de la petición
     * @param int $kpiId ID del KPI
     * @return JsonResponse Datos formateados para el gráfico
     */
    public function getChartData(Request $request, $kpiId): JsonResponse
    {
        try {
            $groupBy = $request->get('group_by');
            $chartType = $request->get('chart_type', 'bar');
            $filters = $request->get('filters', []);

            if (!$groupBy) {
                return response()->json([
                    'error' => 'El parámetro group_by es obligatorio.'
                ], 400);
            }

            $data = $this->chartDataService->getChartData($kpiId, $groupBy, $chartType, $filters);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            Log::error("Error obteniendo datos de gráfico", [
                'kpi_id' => $kpiId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => 'No se pudieron obtener los datos del gráfico.'
            ], 500);
        }
    }

    /**
     * Obtiene datos para una tarjeta de dashboard específica.
     *
     * @param int $cardId ID de la tarjeta de dashboard
     * @return JsonResponse Datos formateados para el gráfico
     */
    public function getChartDataForCard($cardId): JsonResponse
    {
        try {
            $data = $this->chartDataService->getChartDataForCard($cardId);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            Log::error("Error obteniendo datos de gráfico para tarjeta", [
                'card_id' => $cardId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => 'No se pudieron obtener los datos del gráfico.'
            ], 500);
        }
    }

    /**
     * Obtiene parámetros disponibles para un tipo de gráfico específico.
     *
     * @param string $chartType Tipo de gráfico
     * @return JsonResponse Parámetros disponibles
     */
    public function getChartParameters($chartType): JsonResponse
    {
        try {
            $parameters = $this->getParametersForChartType($chartType);

            return response()->json([
                'success' => true,
                'chart_type' => $chartType,
                'parameters' => $parameters
            ]);

        } catch (\Exception $e) {
            Log::error("Error obteniendo parámetros de gráfico", [
                'chart_type' => $chartType,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => 'No se pudieron obtener los parámetros del gráfico.'
            ], 500);
        }
    }

    /**
     * Obtiene tipos de filtros disponibles.
     *
     * @return JsonResponse Tipos de filtros soportados
     */
    public function getAvailableFilterTypes(): JsonResponse
    {
        try {
            $filterTypes = $this->dynamicFilterService->getAvailableFilterTypes();

            return response()->json([
                'success' => true,
                'filter_types' => $filterTypes
            ]);

        } catch (\Exception $e) {
            Log::error("Error obteniendo tipos de filtros", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => 'No se pudieron obtener los tipos de filtros.'
            ], 500);
        }
    }

    /**
     * Obtiene campos disponibles para agrupación de un modelo.
     *
     * @param Request $request Datos de la petición
     * @param int $modelId ID del modelo
     * @return JsonResponse Campos disponibles para agrupación
     */
    public function getGroupByFields(Request $request, $modelId): JsonResponse
    {
        try {
            // Obtener la clase del modelo desde la configuración
            $modelConfig = config("kpis.available_kpi_models.{$modelId}");

            if (!$modelConfig || !isset($modelConfig['class'])) {
                return response()->json([
                    'error' => 'Modelo no encontrado o no configurado.'
                ], 404);
            }

            $modelClass = $modelConfig['class'];
            $fields = $this->chartDataService->getAvailableGroupByFields($modelClass);

            return response()->json([
                'success' => true,
                'model_id' => $modelId,
                'fields' => $fields
            ]);

        } catch (\Exception $e) {
            Log::error("Error obteniendo campos de agrupación", [
                'model_id' => $modelId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => 'No se pudieron obtener los campos de agrupación.'
            ], 500);
        }
    }

    /**
     * Obtiene estadísticas adicionales para los datos de un gráfico.
     *
     * @param Request $request Datos de la petición
     * @param int $kpiId ID del KPI
     * @return JsonResponse Estadísticas calculadas
     */
    public function getChartStatistics(Request $request, $kpiId): JsonResponse
    {
        try {
            $groupBy = $request->get('group_by');
            $chartType = $request->get('chart_type', 'bar');
            $filters = $request->get('filters', []);

            if (!$groupBy) {
                return response()->json([
                    'error' => 'El parámetro group_by es obligatorio.'
                ], 400);
            }

            $data = $this->chartDataService->getChartData($kpiId, $groupBy, $chartType, $filters);
            $statistics = $this->chartDataService->getChartStatistics($data['data']);

            return response()->json([
                'success' => true,
                'statistics' => $statistics,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error("Error obteniendo estadísticas de gráfico", [
                'kpi_id' => $kpiId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => 'No se pudieron obtener las estadísticas del gráfico.'
            ], 500);
        }
    }

    /**
     * Obtiene parámetros específicos por tipo de gráfico.
     *
     * @param string $chartType Tipo de gráfico
     * @return array Parámetros disponibles
     */
    private function getParametersForChartType($chartType)
    {
        $parameters = [
            'bar' => [
                [
                    'name' => 'orientation',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['vertical', 'horizontal'],
                    'default' => 'vertical',
                    'description' => 'Orientación de las barras'
                ],
                [
                    'name' => 'stacked',
                    'type' => 'boolean',
                    'required' => false,
                    'default' => false,
                    'description' => 'Barras apiladas'
                ],
                [
                    'name' => 'show_values',
                    'type' => 'boolean',
                    'required' => false,
                    'default' => true,
                    'description' => 'Mostrar valores en las barras'
                ],
                [
                    'name' => 'color_scheme',
                    'type' => 'select',
                    'required' => false,
                    'options' => ['default', 'custom', 'gradient'],
                    'default' => 'default',
                    'description' => 'Esquema de colores'
                ]
            ],
            'pie' => [
                [
                    'name' => 'show_percentages',
                    'type' => 'boolean',
                    'required' => false,
                    'default' => true,
                    'description' => 'Mostrar porcentajes'
                ],
                [
                    'name' => 'show_legend',
                    'type' => 'boolean',
                    'required' => false,
                    'default' => true,
                    'description' => 'Mostrar leyenda'
                ],
                [
                    'name' => 'legend_position',
                    'type' => 'select',
                    'required' => false,
                    'options' => ['top', 'bottom', 'left', 'right'],
                    'default' => 'right',
                    'description' => 'Posición de la leyenda'
                ],
                [
                    'name' => 'donut',
                    'type' => 'boolean',
                    'required' => false,
                    'default' => false,
                    'description' => 'Gráfico de dona'
                ],
                [
                    'name' => 'donut_size',
                    'type' => 'number',
                    'required' => false,
                    'min' => 0,
                    'max' => 1,
                    'default' => 0.5,
                    'description' => 'Tamaño del agujero del dona'
                ]
            ],
            'line' => [
                [
                    'name' => 'smooth',
                    'type' => 'boolean',
                    'required' => false,
                    'default' => false,
                    'description' => 'Líneas suavizadas'
                ],
                [
                    'name' => 'show_points',
                    'type' => 'boolean',
                    'required' => false,
                    'default' => true,
                    'description' => 'Mostrar puntos en la línea'
                ],
                [
                    'name' => 'fill_area',
                    'type' => 'boolean',
                    'required' => false,
                    'default' => false,
                    'description' => 'Rellenar área bajo la línea'
                ],
                [
                    'name' => 'show_grid',
                    'type' => 'boolean',
                    'required' => false,
                    'default' => true,
                    'description' => 'Mostrar cuadrícula'
                ],
                [
                    'name' => 'y_axis_min',
                    'type' => 'number',
                    'required' => false,
                    'description' => 'Valor mínimo del eje Y'
                ],
                [
                    'name' => 'y_axis_max',
                    'type' => 'number',
                    'required' => false,
                    'description' => 'Valor máximo del eje Y'
                ]
            ],
            'area' => [
                [
                    'name' => 'stacked',
                    'type' => 'boolean',
                    'required' => false,
                    'default' => false,
                    'description' => 'Áreas apiladas'
                ],
                [
                    'name' => 'smooth',
                    'type' => 'boolean',
                    'required' => false,
                    'default' => false,
                    'description' => 'Líneas suavizadas'
                ],
                [
                    'name' => 'opacity',
                    'type' => 'number',
                    'required' => false,
                    'min' => 0,
                    'max' => 1,
                    'default' => 0.7,
                    'description' => 'Opacidad del área'
                ],
                [
                    'name' => 'gradient',
                    'type' => 'boolean',
                    'required' => false,
                    'default' => false,
                    'description' => 'Gradiente en el área'
                ]
            ],
            'scatter' => [
                [
                    'name' => 'x_field',
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Campo para el eje X'
                ],
                [
                    'name' => 'y_field',
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Campo para el eje Y'
                ],
                [
                    'name' => 'point_size',
                    'type' => 'number',
                    'required' => false,
                    'min' => 1,
                    'max' => 20,
                    'default' => 5,
                    'description' => 'Tamaño de los puntos'
                ],
                [
                    'name' => 'show_trend_line',
                    'type' => 'boolean',
                    'required' => false,
                    'default' => false,
                    'description' => 'Mostrar línea de tendencia'
                ]
            ]
        ];

        return $parameters[$chartType] ?? [];
    }
}
