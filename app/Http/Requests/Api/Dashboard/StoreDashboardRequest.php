<?php

namespace App\Http\Requests\Api\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request StoreDashboardRequest
 *
 * Valida los datos para crear un nuevo dashboard.
 * Incluye validación de usuario y configuración del dashboard.
 */
class StoreDashboardRequest extends FormRequest
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
            'user_id' => 'required|exists:users,id',
            'tenant_id' => 'nullable|integer|min:1',
            'name' => 'required|string|max:255',
            'is_default' => 'boolean',
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
            'user_id.required' => 'El ID del usuario es obligatorio.',
            'user_id.exists' => 'El usuario especificado no existe.',
            'tenant_id.integer' => 'El ID del tenant debe ser un número entero.',
            'tenant_id.min' => 'El ID del tenant debe ser mayor a 0.',
            'name.required' => 'El nombre del dashboard es obligatorio.',
            'name.max' => 'El nombre del dashboard no puede exceder 255 caracteres.',
        ];
    }
}
