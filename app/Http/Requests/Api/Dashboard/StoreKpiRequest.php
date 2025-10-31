<?php

namespace App\Http\Requests\Api\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para crear KPIs
 */
class StoreKpiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepara los datos para la validación.
     * Convierte chart_schema de string JSON a array si es necesario.
     *
     * Esto asegura que el JSON se guarde correctamente en la BD como JSON
     * (gracias al cast del modelo) y se recupere como array para generar
     * el gráfico en el servicio.
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
                // Usar json_decode/json_encode para convertir recursivamente objetos anidados a arrays
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
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:kpis,code',
            'description' => 'nullable|string',
            'unit' => 'nullable|string|max:50',
            'is_active' => 'boolean',

            'numerator_model' => 'required|integer',
            'numerator_field' => 'nullable|string',
            'numerator_operation' => 'required|in:count,sum,avg,max,min',

            // Denominador opcional
            'denominator_model' => 'nullable|integer',
            'denominator_field' => 'nullable|string',
            'denominator_operation' => 'nullable|in:count,sum,avg,max,min',

            'calculation_factor' => 'nullable|numeric',
            'target_value' => 'nullable|numeric',
            'date_field' => 'nullable|string',
            'period_type' => 'nullable|in:daily,weekly,monthly,quarterly,yearly',

            'chart_type' => 'nullable|in:bar,pie,line,area,scatter',
            'chart_schema' => 'nullable|array',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasDenModel = $this->filled('denominator_model');
            $hasDenOp = $this->filled('denominator_operation');

            // Si se envía denominator_model, exigir denominator_operation válida
            if ($hasDenModel && !$hasDenOp) {
                $validator->errors()->add('denominator_operation', 'La operación del denominador es obligatoria cuando se especifica un modelo.');
            }
        });
    }
}

