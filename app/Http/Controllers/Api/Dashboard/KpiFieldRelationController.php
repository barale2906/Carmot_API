<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Dashboard\StoreKpiFieldRelationRequest;
use App\Http\Requests\Api\Dashboard\UpdateKpiFieldRelationRequest;
use App\Http\Resources\Api\Dashboard\KpiFieldRelationResource;
use App\Models\Dashboard\Kpi;
use App\Models\Dashboard\KpiFieldRelation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador KpiFieldRelationController
 *
 * Maneja las operaciones CRUD para las relaciones entre campos de KPIs.
 * Permite crear, leer, actualizar y eliminar relaciones entre campos.
 */
class KpiFieldRelationController extends Controller
{
    /**
     * Obtiene las relaciones de campos de un KPI específico.
     *
     * @param int $kpiId ID del KPI
     * @return JsonResponse Lista de relaciones
     */
    public function index(int $kpiId): JsonResponse
    {
        $kpi = Kpi::findOrFail($kpiId);
        $relations = $kpi->fieldRelations()->with(['fieldA', 'fieldB'])->get();

        return KpiFieldRelationResource::collection($relations)->response();
    }

    /**
     * Crea una nueva relación entre campos.
     *
     * @param StoreKpiFieldRelationRequest $request Datos de la petición
     * @param int $kpiId ID del KPI
     * @return JsonResponse Relación creada
     */
    public function store(StoreKpiFieldRelationRequest $request, int $kpiId): JsonResponse
    {
        $kpi = Kpi::findOrFail($kpiId);
        $validated = $request->validated();
        $validated['kpi_id'] = $kpiId;

        $relation = KpiFieldRelation::create($validated);

        return (new KpiFieldRelationResource($relation->load(['fieldA', 'fieldB'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Obtiene una relación específica.
     *
     * @param int $kpiId ID del KPI
     * @param int $relationId ID de la relación
     * @return JsonResponse Relación encontrada
     */
    public function show(int $kpiId, int $relationId): JsonResponse
    {
        $relation = KpiFieldRelation::where('kpi_id', $kpiId)
            ->with(['fieldA', 'fieldB'])
            ->findOrFail($relationId);

        return (new KpiFieldRelationResource($relation))->response();
    }

    /**
     * Actualiza una relación existente.
     *
     * @param UpdateKpiFieldRelationRequest $request Datos de la petición
     * @param int $kpiId ID del KPI
     * @param int $relationId ID de la relación
     * @return JsonResponse Relación actualizada
     */
    public function update(UpdateKpiFieldRelationRequest $request, int $kpiId, int $relationId): JsonResponse
    {
        $relation = KpiFieldRelation::where('kpi_id', $kpiId)->findOrFail($relationId);
        $validated = $request->validated();

        $relation->update($validated);

        return (new KpiFieldRelationResource($relation->load(['fieldA', 'fieldB'])))->response();
    }

    /**
     * Elimina una relación.
     *
     * @param int $kpiId ID del KPI
     * @param int $relationId ID de la relación
     * @return JsonResponse Respuesta de confirmación
     */
    public function destroy(int $kpiId, int $relationId): JsonResponse
    {
        $relation = KpiFieldRelation::where('kpi_id', $kpiId)->findOrFail($relationId);
        $relation->delete();

        return response()->json(['message' => 'Relación eliminada exitosamente.']);
    }

    /**
     * Obtiene las operaciones disponibles para relaciones.
     *
     * @return JsonResponse Lista de operaciones
     */
    public function availableOperations(): JsonResponse
    {
        return response()->json([
            'operations' => KpiFieldRelation::getAvailableOperations()
        ]);
    }
}
