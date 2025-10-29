<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Dashboard\DashboardCardResource;
use App\Http\Requests\Api\Dashboard\StoreDashboardCardRequest;
use App\Http\Requests\Api\Dashboard\UpdateDashboardCardRequest;
use App\Models\Dashboard\DashboardCard;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controlador DashboardCardController
 *
 * Maneja las operaciones CRUD para las tarjetas de dashboard.
 * Proporciona endpoints para gestionar la configuración visual y temporal de las tarjetas.
 */
class DashboardCardController extends Controller
{
    /**
     * Obtiene una lista paginada de tarjetas de dashboard.
     *
     * @param Request $request Datos de la petición
     * @return JsonResponse Lista de tarjetas
     */
    public function index(Request $request): JsonResponse
    {
        $query = DashboardCard::with(['dashboard', 'kpi']);

        // Filtrar por dashboard específico
        if ($request->has('dashboard_id')) {
            $query->where('dashboard_id', $request->dashboard_id);
        }

        // Filtrar por KPI específico
        if ($request->has('kpi_id')) {
            $query->where('kpi_id', $request->kpi_id);
        }

        // Filtrar por tipo de periodo
        if ($request->has('period_type')) {
            $query->where('period_type', $request->period_type);
        }

        // Ordenar por orden y luego por ID
        $query->orderBy('order')->orderBy('id');

        $cards = $query->paginate($request->get('per_page', 15));

        return DashboardCardResource::collection($cards)->response();
    }

    /**
     * Crea una nueva tarjeta de dashboard.
     *
     * @param StoreDashboardCardRequest $request Datos de la petición
     * @return JsonResponse Tarjeta creada
     */
    public function store(StoreDashboardCardRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $card = DashboardCard::create($validated);

        return (new DashboardCardResource($card->load(['dashboard', 'kpi'])))->response()->setStatusCode(201);
    }

    /**
     * Obtiene una tarjeta de dashboard específica.
     *
     * @param int $id ID de la tarjeta
     * @return JsonResponse Tarjeta encontrada
     */
    public function show(int $id): JsonResponse
    {
        $card = DashboardCard::with(['dashboard', 'kpi'])->findOrFail($id);

        return (new DashboardCardResource($card))->response();
    }

    /**
     * Actualiza una tarjeta de dashboard existente.
     *
     * @param UpdateDashboardCardRequest $request Datos de la petición
     * @param int $id ID de la tarjeta
     * @return JsonResponse Tarjeta actualizada
     */
    public function update(UpdateDashboardCardRequest $request, int $id): JsonResponse
    {
        $card = DashboardCard::findOrFail($id);
        $validated = $request->validated();
        $card->update($validated);

        return (new DashboardCardResource($card->load(['dashboard', 'kpi'])))->response();
    }

    /**
     * Elimina una tarjeta de dashboard.
     *
     * @param int $id ID de la tarjeta
     * @return JsonResponse Respuesta de confirmación
     */
    public function destroy(int $id): JsonResponse
    {
        $card = DashboardCard::findOrFail($id);
        $card->delete();

        return response()->json(['message' => 'Tarjeta de dashboard eliminada exitosamente.']);
    }

    /**
     * Obtiene las tarjetas de un dashboard específico.
     *
     * @param int $dashboardId ID del dashboard
     * @return JsonResponse Lista de tarjetas del dashboard
     */
    public function getByDashboard(int $dashboardId): JsonResponse
    {
        $cards = DashboardCard::where('dashboard_id', $dashboardId)
            ->with(['kpi'])
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        return DashboardCardResource::collection($cards)->response();
    }

    /**
     * Actualiza la posición de una tarjeta en el dashboard.
     *
     * @param Request $request Datos de la petición
     * @param int $id ID de la tarjeta
     * @return JsonResponse Tarjeta actualizada
     * @throws ValidationException Si los datos no son válidos
     */
    public function updatePosition(Request $request, int $id): JsonResponse
    {
        $card = DashboardCard::findOrFail($id);

        $validated = $request->validate([
            'x_position' => 'required|integer|min:0',
            'y_position' => 'required|integer|min:0',
            'width' => 'integer|min:1|max:12',
            'height' => 'integer|min:1|max:12',
        ]);

        $card->update($validated);

        return (new DashboardCardResource($card))->response();
    }
}
