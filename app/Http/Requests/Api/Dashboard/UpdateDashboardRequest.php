<?php

namespace App\Http\Requests\Api\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request UpdateDashboardRequest
 *
 * Valida los datos para actualizar un dashboard existente.
 * Incluye validación de configuración del dashboard.
 */
class UpdateDashboardRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'is_default' => 'boolean',
            'dashboard_cards' => 'nullable|array',
            'dashboard_cards.*.id' => 'nullable|integer|exists:dashboard_cards,id',
            'dashboard_cards.*.kpi_id' => 'required_with:dashboard_cards|exists:kpis,id',
            'dashboard_cards.*.title' => 'nullable|string|max:255',
            'dashboard_cards.*.background_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'dashboard_cards.*.text_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'dashboard_cards.*.width' => 'integer|min:1|max:12',
            'dashboard_cards.*.height' => 'integer|min:1|max:12',
            'dashboard_cards.*.x_position' => 'integer|min:0',
            'dashboard_cards.*.y_position' => 'integer|min:0',
            'dashboard_cards.*.period_type' => 'nullable|in:daily,weekly,monthly,yearly,custom',
            'dashboard_cards.*.period_start_date' => 'nullable|date',
            'dashboard_cards.*.period_end_date' => 'nullable|date|after_or_equal:dashboard_cards.*.period_start_date',
            'dashboard_cards.*.custom_field_values' => 'nullable|array',
            'dashboard_cards.*.order' => 'integer|min:0',
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
            'name.string' => 'El nombre del dashboard debe ser una cadena válida.',
            'name.max' => 'El nombre del dashboard no puede exceder 255 caracteres.',
            'dashboard_cards.array' => 'Las tarjetas del dashboard deben ser un array.',
            'dashboard_cards.*.kpi_id.required_with' => 'El ID del KPI es obligatorio para cada tarjeta.',
            'dashboard_cards.*.kpi_id.exists' => 'El KPI especificado no existe.',
            'dashboard_cards.*.title.max' => 'El título de la tarjeta no puede exceder 255 caracteres.',
            'dashboard_cards.*.background_color.regex' => 'El color de fondo debe ser un código hexadecimal válido (ej: #FF0000).',
            'dashboard_cards.*.text_color.regex' => 'El color del texto debe ser un código hexadecimal válido (ej: #FFFFFF).',
            'dashboard_cards.*.width.min' => 'El ancho debe ser al menos 1.',
            'dashboard_cards.*.width.max' => 'El ancho no puede exceder 12.',
            'dashboard_cards.*.height.min' => 'La altura debe ser al menos 1.',
            'dashboard_cards.*.height.max' => 'La altura no puede exceder 12.',
            'dashboard_cards.*.x_position.min' => 'La posición X debe ser mayor o igual a 0.',
            'dashboard_cards.*.y_position.min' => 'La posición Y debe ser mayor o igual a 0.',
            'dashboard_cards.*.period_type.in' => 'El tipo de periodo debe ser: daily, weekly, monthly, yearly o custom.',
            'dashboard_cards.*.period_end_date.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            'dashboard_cards.*.order.integer' => 'El orden debe ser un número entero.',
            'dashboard_cards.*.order.min' => 'El orden debe ser mayor o igual a 0.',
        ];
    }
}
