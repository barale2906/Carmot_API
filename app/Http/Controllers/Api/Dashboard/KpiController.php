<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Dashboard\KpiResource;
use App\Http\Requests\Api\Dashboard\StoreKpiRequest;
use App\Http\Requests\Api\Dashboard\UpdateKpiRequest;
use App\Models\Dashboard\Kpi;
use App\Services\KpiMetadataService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controlador KpiController
 *
 * Maneja las operaciones CRUD para los KPIs del sistema.
 * Proporciona endpoints para crear, leer, actualizar y eliminar KPIs.
 */
class KpiController extends Controller
{
    protected $kpiMetadataService;

    public function __construct(KpiMetadataService $kpiMetadataService)
    {
        $this->kpiMetadataService = $kpiMetadataService;
    }

    /**
     * Obtiene una lista paginada de KPIs.
     *
     * @param Request $request Datos de la petición
     * @return JsonResponse Lista de KPIs
     */
    public function index(Request $request): JsonResponse
    {
        $query = Kpi::with('kpiFields');

        // Aplicar filtros
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('calculation_type')) {
            $query->where('calculation_type', $request->calculation_type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $kpis = $query->paginate($request->get('per_page', 15));

        return KpiResource::collection($kpis)->response();
    }

    /**
     * Crea un nuevo KPI.
     *
     * @param StoreKpiRequest $request Datos de la petición
     * @return JsonResponse KPI creado
     */
    public function store(StoreKpiRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $kpi = Kpi::create($validated);

        // Crear campos del KPI si se proporcionan
        if (isset($validated['kpi_fields'])) {
            foreach ($validated['kpi_fields'] as $fieldData) {
                $kpi->kpiFields()->create($fieldData);
            }
        }

        return (new KpiResource($kpi->load('kpiFields')))->response()->setStatusCode(201);
    }

    /**
     * Obtiene un KPI específico.
     *
     * @param int $id ID del KPI
     * @return JsonResponse KPI encontrado
     */
    public function show(int $id): JsonResponse
    {
        $kpi = Kpi::with('kpiFields', 'dashboardCards')->findOrFail($id);
        return (new KpiResource($kpi))->response();
    }

    /**
     * Actualiza un KPI existente.
     *
     * @param UpdateKpiRequest $request Datos de la petición
     * @param int $id ID del KPI
     * @return JsonResponse KPI actualizado
     */
    public function update(UpdateKpiRequest $request, int $id): JsonResponse
    {
        $kpi = Kpi::findOrFail($id);
        $validated = $request->validated();
        $kpi->update($validated);

        return (new KpiResource($kpi->load('kpiFields')))->response();
    }

    /**
     * Elimina un KPI.
     *
     * @param int $id ID del KPI
     * @return JsonResponse Respuesta de confirmación
     */
    public function destroy(int $id): JsonResponse
    {
        $kpi = Kpi::findOrFail($id);
        $kpi->delete();

        return response()->json(['message' => 'KPI eliminado exitosamente.']);
    }
}
