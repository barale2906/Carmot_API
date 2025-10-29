<?php

namespace App\Http\Requests\Api\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request UpdateDashboardCardRequest
 *
 * Valida los datos para actualizar una tarjeta de dashboard existente.
 * Incluye validación de posicionamiento y configuración visual.
 */
class UpdateDashboardCardRequest extends FormRequest
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
            'title' => 'nullable|string|max:255',
            'background_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'text_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'width' => 'integer|min:1|max:12',
            'height' => 'integer|min:1|max:12',
            'x_position' => 'integer|min:0',
            'y_position' => 'integer|min:0',
            'period_type' => 'nullable|in:daily,weekly,monthly,yearly,custom',
            'period_start_date' => 'nullable|date',
            'period_end_date' => 'nullable|date|after_or_equal:period_start_date',
            'custom_field_values' => 'nullable|array',
            'order' => 'integer|min:0',

            // Campos de configuración de gráfico
            'chart_type' => 'nullable|in:bar,pie,line,area,scatter',
            'chart_parameters' => 'nullable|array',
            'group_by' => 'nullable|string|max:255',
            'filters' => 'nullable|array',
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
            'title.max' => 'El título de la tarjeta no puede exceder 255 caracteres.',
            'background_color.regex' => 'El color de fondo debe ser un código hexadecimal válido (ej: #FF0000).',
            'text_color.regex' => 'El color del texto debe ser un código hexadecimal válido (ej: #FFFFFF).',
            'width.min' => 'El ancho debe ser al menos 1.',
            'width.max' => 'El ancho no puede exceder 12.',
            'height.min' => 'La altura debe ser al menos 1.',
            'height.max' => 'La altura no puede exceder 12.',
            'x_position.min' => 'La posición X debe ser mayor o igual a 0.',
            'y_position.min' => 'La posición Y debe ser mayor o igual a 0.',
            'period_type.in' => 'El tipo de periodo debe ser: daily, weekly, monthly, yearly o custom.',
            'period_end_date.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            'order.integer' => 'El orden debe ser un número entero.',
            'order.min' => 'El orden debe ser mayor o igual a 0.',

            // Mensajes de configuración de gráfico
            'chart_type.in' => 'El tipo de gráfico debe ser: bar, pie, line, area o scatter.',
            'chart_parameters.array' => 'Los parámetros del gráfico deben ser un array.',
            'group_by.max' => 'El campo de agrupación no puede exceder 255 caracteres.',
            'filters.array' => 'Los filtros deben ser un array.',
        ];
    }
}
