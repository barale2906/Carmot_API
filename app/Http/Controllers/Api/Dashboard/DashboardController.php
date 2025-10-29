<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Dashboard\DashboardResource;
use App\Http\Requests\Api\Dashboard\StoreDashboardRequest;
use App\Http\Requests\Api\Dashboard\UpdateDashboardRequest;
use App\Models\Dashboard\Dashboard;
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

    /**
     * Obtiene una lista paginada de dashboards.
     *
     * @param Request $request Datos de la petición
     * @return JsonResponse Lista de dashboards
     */
    public function index(Request $request): JsonResponse
    {
        $query = Dashboard::with(['user', 'dashboardCards.kpi']);

        // Filtrar por usuario autenticado - mostrar dashboards default O del usuario autenticado
        $authenticatedUser = $request->user();
        $authenticatedUserId = $authenticatedUser?->id;

        if ($authenticatedUserId) {
            // Si es superusuario, puede ver todos los dashboards
            if ($authenticatedUser->hasRole('superusuario')) {
                // Los superusuarios ven todos los dashboards sin restricciones
            } else {
                // Usuarios normales ven dashboards default O sus propios dashboards
                $query->where(function ($q) use ($authenticatedUserId) {
                    $q->where('is_default', true)
                      ->orWhere('user_id', $authenticatedUserId);
                });
            }
        } else {
            // Si no hay usuario autenticado, mostrar solo los dashboards default
            $query->where('is_default', true);
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
     * Obtiene un dashboard específico.
     *
     * @param int $dashboardId ID del dashboard
     * @return JsonResponse Dashboard encontrado
     */
    public function show(int $dashboardId): JsonResponse
    {
        $dashboard = Dashboard::with(['dashboardCards.kpi'])
            ->findOrFail($dashboardId);

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
        $dashboard = Dashboard::with(['dashboardCards.kpi'])
            ->findOrFail($dashboardId);

        // TODO: Implementar generación de PDF
        // Por ahora retornamos un JSON con los datos del dashboard
        return response()->json([
            'message' => 'Funcionalidad de exportación a PDF pendiente de implementar',
            'dashboard' => $dashboard
        ]);
    }
}
