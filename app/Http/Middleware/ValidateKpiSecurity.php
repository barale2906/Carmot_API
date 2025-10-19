<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware ValidateKpiSecurity
 *
 * Valida la seguridad de las peticiones relacionadas con KPIs.
 * Asegura que solo se usen modelos y campos permitidos.
 */
class ValidateKpiSecurity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Validar que el modelo base esté permitido
        if ($request->has('base_model')) {
            $this->validateBaseModel($request->base_model);
        }

        // Validar campos en requests de KPI fields
        if ($request->has('field_name') && $request->has('kpi_id')) {
            $this->validateFieldForKpi($request->field_name, $request->kpi_id);
        }

        return $next($request);
    }

    /**
     * Valida que el modelo base esté permitido.
     *
     * @param string $baseModel
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateBaseModel(string $baseModel): void
    {
        $allowedModels = array_keys(config('kpis.available_kpi_models', []));

        if (!in_array($baseModel, $allowedModels)) {
            throw new \InvalidArgumentException("El modelo '{$baseModel}' no está permitido para KPIs.");
        }
    }

    /**
     * Valida que el campo esté permitido para el KPI.
     *
     * @param string $fieldName
     * @param int $kpiId
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateFieldForKpi(string $fieldName, int $kpiId): void
    {
        $kpi = \App\Models\Dashboard\Kpi::find($kpiId);

        if (!$kpi || !$kpi->base_model) {
            throw new \InvalidArgumentException("KPI no válido o sin modelo base.");
        }

        $allowedFields = config("kpis.available_kpi_models.{$kpi->base_model}.fields", []);

        if (!in_array($fieldName, $allowedFields)) {
            throw new \InvalidArgumentException("El campo '{$fieldName}' no está permitido para este modelo.");
        }
    }
}
