<?php

namespace App\Http\Requests\Api\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request StoreDashboardCardRequest
 *
 * Valida los datos para crear una nueva tarjeta de dashboard.
 * Incluye validación específica para configuración de gráficos y filtros.
 */
class StoreDashboardCardRequest extends FormRequest
{
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
            // Campos básicos de la tarjeta
            'dashboard_id' => 'required|exists:dashboards,id',
            'kpi_id' => 'required|exists:kpis,id',
            'title' => 'required|string|max:255',
            'background_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'text_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'width' => 'integer|min:1|max:12',
            'height' => 'integer|min:1|max:12',
            'x_position' => 'integer|min:0',
            'y_position' => 'integer|min:0',
            'order' => 'integer|min:0',

            // Campos de periodo
            'period_type' => 'nullable|in:daily,weekly,monthly,yearly,custom',
            'period_start_date' => 'nullable|date|required_if:period_type,custom',
            'period_end_date' => 'nullable|date|required_if:period_type,custom|after_or_equal:period_start_date',

            // Campos de gráficos
            'chart_type' => 'required|in:bar,pie,line,area,scatter',
            'chart_parameters' => 'array',
            'chart_parameters.orientation' => 'required_if:chart_type,bar|in:vertical,horizontal',
            'chart_parameters.stacked' => 'boolean',
            'chart_parameters.show_values' => 'boolean',
            'chart_parameters.show_percentages' => 'boolean',
            'chart_parameters.show_legend' => 'boolean',
            'chart_parameters.legend_position' => 'in:top,bottom,left,right',
            'chart_parameters.donut' => 'boolean',
            'chart_parameters.donut_size' => 'numeric|min:0|max:1',
            'chart_parameters.smooth' => 'boolean',
            'chart_parameters.show_points' => 'boolean',
            'chart_parameters.fill_area' => 'boolean',
            'chart_parameters.show_grid' => 'boolean',
            'chart_parameters.y_axis_min' => 'numeric',
            'chart_parameters.y_axis_max' => 'numeric',
            'chart_parameters.opacity' => 'numeric|min:0|max:1',
            'chart_parameters.gradient' => 'boolean',
            'chart_parameters.point_size' => 'integer|min:1|max:20',
            'chart_parameters.show_trend_line' => 'boolean',

            // Campo de agrupación
            'group_by' => 'required|string|max:255',

            // Filtros dinámicos
            'filters' => 'array',
            'filters.*.field' => 'required|string|max:255',
            'filters.*.type' => 'required|in:exact,in,date_range,text,multiple,null,range,custom',
            'filters.*.value' => 'required',
            'filters.*.operator' => 'nullable|string|max:10',
            'filters.*.custom_type' => 'nullable|string|max:50',

            // Valores personalizados
            'custom_field_values' => 'array',
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
            // Mensajes básicos
            'dashboard_id.required' => 'El ID del dashboard es obligatorio.',
            'dashboard_id.exists' => 'El dashboard especificado no existe.',
            'kpi_id.required' => 'El ID del KPI es obligatorio.',
            'kpi_id.exists' => 'El KPI especificado no existe.',
            'title.required' => 'El título de la tarjeta es obligatorio.',
            'title.max' => 'El título no puede exceder 255 caracteres.',

            // Mensajes de colores
            'background_color.regex' => 'El color de fondo debe ser un código hexadecimal válido (ej. #FF0000).',
            'text_color.regex' => 'El color del texto debe ser un código hexadecimal válido (ej. #FFFFFF).',

            // Mensajes de dimensiones
            'width.min' => 'El ancho debe ser al menos 1.',
            'width.max' => 'El ancho no puede exceder 12.',
            'height.min' => 'La altura debe ser al menos 1.',
            'height.max' => 'La altura no puede exceder 12.',
            'x_position.min' => 'La posición X debe ser mayor o igual a 0.',
            'y_position.min' => 'La posición Y debe ser mayor o igual a 0.',

            // Mensajes de periodo
            'period_type.in' => 'El tipo de periodo debe ser: daily, weekly, monthly, yearly o custom.',
            'period_start_date.required_if' => 'La fecha de inicio es obligatoria cuando el tipo de periodo es custom.',
            'period_end_date.required_if' => 'La fecha de fin es obligatoria cuando el tipo de periodo es custom.',
            'period_end_date.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',

            // Mensajes de gráficos
            'chart_type.required' => 'El tipo de gráfico es obligatorio.',
            'chart_type.in' => 'El tipo de gráfico debe ser: bar, pie, line, area o scatter.',
            'chart_parameters.orientation.required_if' => 'La orientación es obligatoria para gráficos de barras.',
            'chart_parameters.orientation.in' => 'La orientación debe ser vertical u horizontal.',
            'chart_parameters.legend_position.in' => 'La posición de la leyenda debe ser: top, bottom, left o right.',
            'chart_parameters.donut_size.min' => 'El tamaño del donut debe ser mayor o igual a 0.',
            'chart_parameters.donut_size.max' => 'El tamaño del donut debe ser menor o igual a 1.',
            'chart_parameters.opacity.min' => 'La opacidad debe ser mayor o igual a 0.',
            'chart_parameters.opacity.max' => 'La opacidad debe ser menor o igual a 1.',
            'chart_parameters.point_size.min' => 'El tamaño del punto debe ser al menos 1.',
            'chart_parameters.point_size.max' => 'El tamaño del punto no puede exceder 20.',

            // Mensajes de agrupación
            'group_by.required' => 'El campo de agrupación es obligatorio.',
            'group_by.max' => 'El campo de agrupación no puede exceder 255 caracteres.',

            // Mensajes de filtros
            'filters.array' => 'Los filtros deben ser un array.',
            'filters.*.field.required' => 'El campo del filtro es obligatorio.',
            'filters.*.type.required' => 'El tipo de filtro es obligatorio.',
            'filters.*.type.in' => 'El tipo de filtro debe ser: exact, in, date_range, text, multiple, null, range o custom.',
            'filters.*.value.required' => 'El valor del filtro es obligatorio.',
            'filters.*.operator.max' => 'El operador no puede exceder 10 caracteres.',
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
            // Validar parámetros específicos por tipo de gráfico
            $this->validateChartParameters($validator);

            // Validar filtros dinámicos
            $this->validateFilters($validator);

            // Validar período personalizado
            $this->validateCustomPeriod($validator);
        });
    }

    /**
     * Valida parámetros específicos según el tipo de gráfico.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    private function validateChartParameters($validator): void
    {
        $chartType = $this->chart_type;
        $parameters = $this->chart_parameters ?? [];

        switch ($chartType) {
            case 'bar':
                if (!isset($parameters['orientation'])) {
                    $validator->errors()->add('chart_parameters.orientation', 'La orientación es obligatoria para gráficos de barras.');
                }
                break;

            case 'pie':
                if (isset($parameters['donut_size']) && ($parameters['donut_size'] < 0 || $parameters['donut_size'] > 1)) {
                    $validator->errors()->add('chart_parameters.donut_size', 'El tamaño del donut debe estar entre 0 y 1.');
                }
                break;

            case 'scatter':
                if (!isset($parameters['x_field']) || !isset($parameters['y_field'])) {
                    $validator->errors()->add('chart_parameters', 'Los gráficos de dispersión requieren campos X e Y.');
                }
                break;
        }
    }

    /**
     * Valida la estructura de los filtros dinámicos.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    private function validateFilters($validator): void
    {
        $filters = $this->filters ?? [];

        foreach ($filters as $index => $filter) {
            if (!isset($filter['field']) || !isset($filter['type']) || !isset($filter['value'])) {
                $validator->errors()->add("filters.{$index}", "El filtro debe contener 'field', 'type' y 'value'.");
                continue;
            }

            // Validar filtros de rango de fechas
            if ($filter['type'] === 'date_range' && (!isset($filter['value']['start']) || !isset($filter['value']['end']))) {
                $validator->errors()->add("filters.{$index}.value", "Los filtros de rango de fechas requieren 'start' y 'end'.");
            }

            // Validar filtros de rango numérico
            if ($filter['type'] === 'range' && (!isset($filter['value']['min']) || !isset($filter['value']['max']))) {
                $validator->errors()->add("filters.{$index}.value", "Los filtros de rango requieren 'min' y 'max'.");
            }

            // Validar filtros de lista
            if ($filter['type'] === 'in' && !is_array($filter['value'])) {
                $validator->errors()->add("filters.{$index}.value", "Los filtros de lista requieren un array de valores.");
            }
        }
    }

    /**
     * Valida el período personalizado.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    private function validateCustomPeriod($validator): void
    {
        if ($this->period_type === 'custom') {
            if (!$this->period_start_date || !$this->period_end_date) {
                $validator->errors()->add('period_type', 'El período personalizado requiere fechas de inicio y fin.');
            } elseif ($this->period_start_date >= $this->period_end_date) {
                $validator->errors()->add('period_end_date', 'La fecha de fin debe ser posterior a la fecha de inicio.');
            }
        }
    }
}
