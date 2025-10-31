<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Dashboard\Kpi;
use App\Services\KpiCalculationService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Api\Dashboard\KpiComputeRequest;
use App\Http\Resources\Api\Dashboard\KpiComputeResource;

/**
 * Controlador de KPIs
 *
 * Provee endpoints para calcular indicadores con parámetros dinámicos
 * de periodo, filtros y agrupación.
 */
class KpiController extends Controller
{
    public function __construct(private readonly KpiCalculationService $service)
    {
    }

    /**
     * Calcula un KPI con parámetros opcionales de periodo, filtros y agrupación.
     *
     * Parámetros (query):
     * - period_type: daily|weekly|monthly|quarterly|yearly
     * - start_date, end_date: Rango de fechas (Y-m-d)
     * - date_field: Campo de fecha a usar para el filtrado
     * - filters[]: Mapa de filtros de igualdad
     * - group_by: Campo para agrupar resultados
     * - group_limit: Límite de grupos devueltos
     * - chart_schema: Esquema del gráfico para sobrescribir el del KPI
     *
     * @param KpiComputeRequest $request
     * @param Kpi $kpi
     * @return JsonResponse
     */
    public function compute(KpiComputeRequest $request, Kpi $kpi): JsonResponse
    {
        $opts = $request->validated();
        
        // El servicio ahora maneja el chart_schema de las opciones con prioridad sobre el del KPI
        $result = $this->service->compute($kpi, $opts);

        return (new KpiComputeResource($result))
            ->response()
            ->setStatusCode(200);
    }
}
