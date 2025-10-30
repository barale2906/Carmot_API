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
