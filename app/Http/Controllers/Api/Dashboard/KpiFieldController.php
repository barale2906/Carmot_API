<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Dashboard\KpiFieldResource;
use App\Http\Requests\Api\Dashboard\StoreKpiFieldRequest;
use App\Http\Requests\Api\Dashboard\UpdateKpiFieldRequest;
use App\Models\Dashboard\KpiField;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controlador KpiFieldController
 *
 * Maneja las operaciones CRUD para los campos de KPIs.
 * Proporciona endpoints para gestionar la configuración de campos de KPIs.
 */
class KpiFieldController extends Controller
{
    /**
     * Obtiene una lista paginada de campos de KPI.
     *
     * @param Request $request Datos de la petición
     * @return JsonResponse Lista de campos de KPI
     */
    public function index(Request $request): JsonResponse
    {
        $query = KpiField::with('kpi');

        // Filtrar por KPI específico
        if ($request->has('kpi_id')) {
            $query->where('kpi_id', $request->kpi_id);
        }

        // Filtrar por operación
        if ($request->has('operation')) {
            $query->where('operation', $request->operation);
        }

        // Filtrar por tipo de campo
        if ($request->has('field_type')) {
            $query->where('field_type', $request->field_type);
        }

        // Ordenar por orden y luego por ID
        $query->orderBy('order')->orderBy('id');

        $fields = $query->paginate($request->get('per_page', 15));

        return KpiFieldResource::collection($fields)->response();
    }

    /**
     * Crea un nuevo campo de KPI.
     *
     * @param StoreKpiFieldRequest $request Datos de la petición
     * @return JsonResponse Campo de KPI creado
     */
    public function store(StoreKpiFieldRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $field = KpiField::create($validated);

        return (new KpiFieldResource($field->load('kpi')))->response()->setStatusCode(201);
    }

    /**
     * Obtiene un campo de KPI específico.
     *
     * @param int $id ID del campo
     * @return JsonResponse Campo de KPI encontrado
     */
    public function show(int $id): JsonResponse
    {
        $field = KpiField::with('kpi')->findOrFail($id);
        return (new KpiFieldResource($field))->response();
    }

    /**
     * Actualiza un campo de KPI existente.
     *
     * @param UpdateKpiFieldRequest $request Datos de la petición
     * @param int $id ID del campo
     * @return JsonResponse Campo de KPI actualizado
     */
    public function update(UpdateKpiFieldRequest $request, int $id): JsonResponse
    {
        $field = KpiField::findOrFail($id);
        $validated = $request->validated();
        $field->update($validated);

        return (new KpiFieldResource($field->load('kpi')))->response();
    }

    /**
     * Elimina un campo de KPI.
     *
     * @param int $id ID del campo
     * @return JsonResponse Respuesta de confirmación
     */
    public function destroy(int $id): JsonResponse
    {
        $field = KpiField::findOrFail($id);
        $field->delete();

        return response()->json(['message' => 'Campo de KPI eliminado exitosamente.']);
    }

    /**
     * Obtiene los campos de un KPI específico.
     *
     * @param int $kpiId ID del KPI
     * @return JsonResponse Lista de campos del KPI
     */
    public function getByKpi(int $kpiId): JsonResponse
    {
        $fields = KpiField::where('kpi_id', $kpiId)
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        return KpiFieldResource::collection($fields)->response();
    }
}
