<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Dashboard\DashboardResource;
use App\Http\Requests\Api\Dashboard\StoreDashboardRequest;
use App\Http\Requests\Api\Dashboard\UpdateDashboardRequest;
use App\Models\Dashboard\Dashboard;
use App\Services\KpiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controlador DashboardController
 *
 * Maneja las operaciones CRUD para los dashboards del sistema.
 * Proporciona endpoints para gestionar dashboards y sus tarjetas.
 */
class DashboardController extends Controller
{
    protected $kpiService;

    public function __construct(KpiService $kpiService)
    {
        $this->kpiService = $kpiService;
    }

    /**
     * Obtiene una lista paginada de dashboards.
     *
     * @param Request $request Datos de la petición
     * @return JsonResponse Lista de dashboards
     */
    public function index(Request $request): JsonResponse
    {
        $query = Dashboard::with(['user', 'dashboardCards.kpi']);

        // Filtrar por usuario
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filtrar por tenant
        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        // Filtrar por búsqueda
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $dashboards = $query->paginate($request->get('per_page', 15));

        return DashboardResource::collection($dashboards)->response();
    }

    /**
     * Crea un nuevo dashboard.
     *
     * @param StoreDashboardRequest $request Datos de la petición
     * @return JsonResponse Dashboard creado
     */
    public function store(StoreDashboardRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $dashboard = Dashboard::create($validated);

        return (new DashboardResource($dashboard->load(['user', 'dashboardCards.kpi'])))->response()->setStatusCode(201);
    }

    /**
     * Obtiene un dashboard específico con valores de KPIs calculados.
     *
     * @param int $dashboardId ID del dashboard
     * @return JsonResponse Dashboard encontrado
     */
    public function show(int $dashboardId): JsonResponse
    {
        $dashboard = Dashboard::with(['dashboardCards.kpi.kpiFields'])
            ->findOrFail($dashboardId);

        $dashboardData = $dashboard->toArray();

        foreach ($dashboardData['dashboard_cards'] as &$card) {
            // Usar fechas de la tarjeta si están definidas, sino usar las fechas por defecto del KPI
            $startDate = $card['period_start_date'] ? Carbon::parse($card['period_start_date']) : null;
            $endDate = $card['period_end_date'] ? Carbon::parse($card['period_end_date']) : null;

            $card['kpi_value'] = $this->kpiService->getKpiValue(
                $card['kpi_id'],
                $dashboard->tenant_id,
                $startDate,
                $endDate
            );
        }

        return (new DashboardResource($dashboard))->response();
    }

    /**
     * Actualiza un dashboard existente.
     *
     * @param UpdateDashboardRequest $request Datos de la petición
     * @param int $dashboardId ID del dashboard
     * @return JsonResponse Dashboard actualizado
     */
    public function update(UpdateDashboardRequest $request, int $dashboardId): JsonResponse
    {
        $dashboard = Dashboard::findOrFail($dashboardId);
        $validated = $request->validated();

        $dashboard->update($validated);

        // Actualizar tarjetas si se proporcionan
        if ($request->has('dashboard_cards')) {
            foreach ($request->dashboard_cards as $cardData) {
                $dashboard->dashboardCards()->updateOrCreate(
                    ['id' => $cardData['id'] ?? null],
                    $cardData
                );
            }
        }

        return (new DashboardResource($dashboard->load('dashboardCards')))->response();
    }

    /**
     * Elimina un dashboard.
     *
     * @param int $dashboardId ID del dashboard
     * @return JsonResponse Respuesta de confirmación
     */
    public function destroy(int $dashboardId): JsonResponse
    {
        $dashboard = Dashboard::findOrFail($dashboardId);
        $dashboard->delete();

        return response()->json(['message' => 'Dashboard eliminado exitosamente.']);
    }

    /**
     * Exporta un dashboard a PDF.
     *
     * @param int $dashboardId ID del dashboard
     * @return \Illuminate\Http\Response Archivo PDF
     */
    public function exportPdf(int $dashboardId)
    {
        $dashboard = Dashboard::with(['dashboardCards.kpi.kpiFields'])
            ->findOrFail($dashboardId);

        // TODO: Implementar generación de PDF
        // Por ahora retornamos un JSON con los datos del dashboard
        return response()->json([
            'message' => 'Funcionalidad de exportación a PDF pendiente de implementar',
            'dashboard' => $dashboard
        ]);
    }
}
