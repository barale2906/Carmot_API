<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Dashboard\ModelMetadataResource;
use App\Http\Resources\Api\Dashboard\FieldMetadataResource;
use App\Services\KpiMetadataService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controlador KpiMetadataController
 *
 * Maneja la obtención de metadatos para la configuración de KPIs.
 * Proporciona información sobre modelos disponibles y campos de modelos.
 */
class KpiMetadataController extends Controller
{
    protected $kpiMetadataService;

    public function __construct(KpiMetadataService $kpiMetadataService)
    {
        $this->kpiMetadataService = $kpiMetadataService;
    }

    /**
     * Obtiene la lista de modelos disponibles para KPIs.
     *
     * @return JsonResponse Lista de modelos disponibles
     */
    public function getModels(): JsonResponse
    {
        try {
            $models = $this->kpiMetadataService->getAvailableKpiModels();

            // Debug: Log de los modelos obtenidos
            //Log::info('KPI Metadata Models:', $models);

            return ModelMetadataResource::collection($models)->response();
        } catch (\Exception $e) {
            /* Log::error('Error en getModels: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]); */

            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene los campos disponibles de un modelo específico.
     *
     * @param Request $request Datos de la petición
     * @param int $modelId ID del modelo
     * @return JsonResponse Lista de campos del modelo
     */
    public function getFields(Request $request, int $modelId): JsonResponse
    {
        if (!$this->kpiMetadataService->isModelAllowed($modelId)) {
            return response()->json(['error' => 'Modelo no permitido o no encontrado.'], 403);
        }

        $fields = $this->kpiMetadataService->getModelFields($modelId);
        return FieldMetadataResource::collection($fields)->response();
    }

    /**
     * Obtiene los KPIs disponibles (alias para getModels).
     * Mantiene compatibilidad con la ruta anterior.
     *
     * @return JsonResponse Lista de modelos disponibles para KPIs
     */
    public function getAvailableKpis(): JsonResponse
    {
        return $this->getModels();
    }
}
