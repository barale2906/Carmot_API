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

    /**
     * Prepara los datos para la validación.
     * Convierte chart_schema de string JSON a array si es necesario.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('chart_schema')) {
            $chartSchema = $this->input('chart_schema');

            // Si es null, no hacer nada
            if (is_null($chartSchema)) {
                return;
            }

            // Si ya es array, mantenerlo
            if (is_array($chartSchema)) {
                return;
            }

            $converted = null;

            // Si es string JSON, parsearlo a array
            if (is_string($chartSchema)) {
                $decoded = json_decode($chartSchema, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $converted = $decoded;
                }
            }
            // Si es un objeto (stdClass u otro), convertirlo a array recursivamente
            elseif (is_object($chartSchema)) {
                $array = json_decode(json_encode($chartSchema), true);
                if (is_array($array)) {
                    $converted = $array;
                }
            }

            // Si se convirtió exitosamente, actualizar el request
            if ($converted !== null) {
                $this->merge(['chart_schema' => $converted]);
            }
        }
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
            'chart_schema' => 'nullable|array',
            'ignore_stored_schema' => 'nullable|boolean', // Flag para ignorar el chart_schema guardado
        ];
    }
}
