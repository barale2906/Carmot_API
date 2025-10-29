<?php

namespace App\Http\Requests\Api\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para cálculo de KPIs
 *
 * Valida parámetros de entrada para el endpoint de cálculo de KPIs,
 * incluyendo periodo, rango de fechas, campo de fecha, filtros y agrupación.
 */
class KpiComputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /**
         * Reglas de validación:
         * - period_type: preset de periodo soportado
         * - start_date/end_date: rango de fechas válido
         * - date_field: nombre de campo de fecha
         * - filters: mapa de filtros de igualdad
         * - group_by: campo por el cual agrupar
         * - group_limit: límite máximo de grupos
         */
        return [
            'period_type' => 'nullable|in:daily,weekly,monthly,quarterly,yearly',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'date_field' => 'nullable|string',
            'filters' => 'nullable|array',
            'group_by' => 'nullable|string',
            'group_limit' => 'nullable|integer|min:1|max:1000',
        ];
    }
}
