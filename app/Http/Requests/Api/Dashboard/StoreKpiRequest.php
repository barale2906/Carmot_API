<?php

namespace App\Http\Requests\Api\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\KpiMetadataService;

/**
 * Request StoreKpiRequest
 *
 * Valida los datos para crear un nuevo KPI.
 * Incluye validación de modelo base y campos de configuración.
 */
class StoreKpiRequest extends FormRequest
{
    protected $kpiMetadataService;

    public function __construct(KpiMetadataService $kpiMetadataService)
    {
        parent::__construct();
        $this->kpiMetadataService = $kpiMetadataService;
    }

    /**
     * Determina si el usuario está autorizado para hacer esta petición.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en middleware
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:kpis,code',
            'description' => 'nullable|string|max:1000',
            'unit' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'calculation_type' => 'required|in:predefined,custom_fields,sql_query',
            'base_model' => 'nullable|integer',
            'default_period_type' => 'nullable|in:daily,weekly,monthly,yearly,custom',
            'default_period_start_date' => 'nullable|date',
            'default_period_end_date' => 'nullable|date|after_or_equal:default_period_start_date',
            'use_custom_time_range' => 'boolean',
            'kpi_fields' => 'nullable|array',
            'kpi_fields.*.field_name' => 'required_with:kpi_fields|string|max:255',
            'kpi_fields.*.display_name' => 'required_with:kpi_fields|string|max:255',
            'kpi_fields.*.field_type' => 'required_with:kpi_fields|in:numeric,string,date,boolean',
            'kpi_fields.*.operation' => 'nullable|in:sum,count,avg,min,max,where,group_by',
            'kpi_fields.*.operator' => 'nullable|string|max:10',
            'kpi_fields.*.value' => 'nullable|string|max:500',
            'kpi_fields.*.is_required' => 'boolean',
            'kpi_fields.*.order' => 'integer|min:0',
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del KPI es obligatorio.',
            'name.max' => 'El nombre del KPI no puede exceder 255 caracteres.',
            'code.required' => 'El código del KPI es obligatorio.',
            'code.unique' => 'El código del KPI ya existe.',
            'code.max' => 'El código del KPI no puede exceder 255 caracteres.',
            'description.max' => 'La descripción no puede exceder 1000 caracteres.',
            'unit.max' => 'La unidad no puede exceder 50 caracteres.',
            'calculation_type.required' => 'El tipo de cálculo es obligatorio.',
            'calculation_type.in' => 'El tipo de cálculo debe ser: predefined, custom_fields o sql_query.',
            'base_model.integer' => 'El modelo base debe ser un número entero.',
            'default_period_type.in' => 'El tipo de periodo debe ser: daily, weekly, monthly, yearly o custom.',
            'default_period_start_date.date' => 'La fecha de inicio debe ser una fecha válida.',
            'default_period_end_date.date' => 'La fecha de fin debe ser una fecha válida.',
            'default_period_end_date.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            'use_custom_time_range.boolean' => 'El uso de rango personalizado debe ser verdadero o falso.',
            'kpi_fields.array' => 'Los campos del KPI deben ser un array.',
            'kpi_fields.*.field_name.required_with' => 'El nombre del campo es obligatorio cuando se especifican campos.',
            'kpi_fields.*.display_name.required_with' => 'El nombre de visualización es obligatorio cuando se especifican campos.',
            'kpi_fields.*.field_type.required_with' => 'El tipo de campo es obligatorio cuando se especifican campos.',
            'kpi_fields.*.field_type.in' => 'El tipo de campo debe ser: numeric, string, date o boolean.',
            'kpi_fields.*.operation.in' => 'La operación debe ser: sum, count, avg, min, max, where o group_by.',
            'kpi_fields.*.operator.max' => 'El operador no puede exceder 10 caracteres.',
            'kpi_fields.*.value.max' => 'El valor no puede exceder 500 caracteres.',
            'kpi_fields.*.order.integer' => 'El orden debe ser un número entero.',
            'kpi_fields.*.order.min' => 'El orden debe ser mayor o igual a 0.',
        ];
    }

    /**
     * Configura el validador después de que las reglas se hayan aplicado.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que el modelo base existe en la configuración
            if ($this->base_model) {
                $availableModels = config('kpis.available_kpi_models', []);
                if (!array_key_exists($this->base_model, $availableModels)) {
                    $validator->errors()->add('base_model', 'El modelo especificado no está disponible en la configuración.');
                }
            }

            // Validar que al menos un campo tiene operación principal si calculation_type es custom_fields
            if ($this->calculation_type === 'custom_fields' && $this->kpi_fields) {
                $hasMainOperation = collect($this->kpi_fields)->contains(function ($field) {
                    return in_array($field['operation'] ?? '', ['sum', 'count', 'avg', 'min', 'max']);
                });

                if (!$hasMainOperation) {
                    $validator->errors()->add('kpi_fields', 'Debe especificar al menos un campo con operación principal (sum, count, avg, min, max).');
                }
            }

            // Validar configuración de rango de tiempo
            if ($this->use_custom_time_range) {
                if (!$this->default_period_start_date || !$this->default_period_end_date) {
                    $validator->errors()->add('use_custom_time_range', 'Si se usa rango personalizado, deben especificarse las fechas de inicio y fin.');
                }
            }

            if ($this->default_period_type === 'custom' && !$this->use_custom_time_range) {
                $validator->errors()->add('default_period_type', 'Si el tipo de periodo es custom, debe activarse el rango personalizado.');
            }
        });
    }
}
