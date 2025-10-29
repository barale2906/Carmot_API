<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Dashboard\StoreKpiRequest;
use App\Http\Requests\Api\Dashboard\UpdateKpiRequest;
use App\Http\Resources\Api\Dashboard\KpiResource;
use App\Models\Dashboard\Kpi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD de KPIs
 *
 * Gestiona creación, listado, detalle, actualización y eliminación de KPIs.
 */
class KpiCrudController extends Controller
{
    /**
     * Lista paginada/filtrada de KPIs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Kpi::query();

        if ($request->filled('search')) {
            $q = $request->string('search');
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool)$request->boolean('is_active'));
        }

        $kpis = $query->paginate($request->integer('per_page', 15));
        return KpiResource::collection($kpis)->response();
    }

    /**
     * Crea un KPI.
     */
    public function store(StoreKpiRequest $request): JsonResponse
    {
        $kpi = Kpi::create($request->validated());
        return (new KpiResource($kpi))->response()->setStatusCode(201);
    }

    /**
     * Muestra detalle de un KPI.
     */
    public function show(Kpi $kpi): JsonResponse
    {
        return (new KpiResource($kpi))->response();
    }

    /**
     * Actualiza un KPI.
     */
    public function update(UpdateKpiRequest $request, Kpi $kpi): JsonResponse
    {
        $kpi->update($request->validated());
        return (new KpiResource($kpi))->response();
    }

    /**
     * Elimina un KPI.
     */
    public function destroy(Kpi $kpi): JsonResponse
    {
        $kpi->delete();
        return response()->json(['message' => 'KPI eliminado exitosamente.']);
    }
}
