<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Dashboard\ModelMetadataResource;
use App\Http\Resources\Api\Dashboard\FieldMetadataResource;
use App\Services\KpiMetadataService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
        $models = $this->kpiMetadataService->getAvailableKpiModels();
        return ModelMetadataResource::collection($models)->response();
    }

    /**
     * Obtiene los campos disponibles de un modelo específico.
     *
     * @param Request $request Datos de la petición
     * @param string $modelClass Clase del modelo (URL encoded)
     * @return JsonResponse Lista de campos del modelo
     */
    public function getFields(Request $request, string $modelClass): JsonResponse
    {
        $modelClass = urldecode($modelClass);

        if (!$this->kpiMetadataService->isModelAllowed($modelClass)) {
            return response()->json(['error' => 'Modelo no permitido o no encontrado.'], 403);
        }

        $fields = $this->kpiMetadataService->getModelFields($modelClass);
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
