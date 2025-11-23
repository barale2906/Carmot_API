<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotaEstudianteRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nota' => 'sometimes|required|numeric|min:0',
            'fecha_registro' => 'sometimes|date',
            'observaciones' => 'nullable|string',
            'status' => 'sometimes|integer|in:0,1,2',
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nota.required' => 'La nota es obligatoria.',
            'nota.numeric' => 'La nota debe ser un número.',
            'nota.min' => 'La nota no puede ser menor a 0.',
            'fecha_registro.date' => 'La fecha de registro debe ser una fecha válida.',
            'status.in' => 'El estado debe ser 0 (pendiente), 1 (registrada) o 2 (cerrada).',
        ];
    }
}
