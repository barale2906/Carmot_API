<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;

class StoreCursoRequest extends FormRequest
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
            'nombre' => 'required|string|max:255|unique:cursos,nombre',
            'duracion' => 'required|numeric|min:0',
            'status' => 'sometimes|integer|in:0,1',
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
            'nombre.required' => 'El nombre del curso es obligatorio.',
            'nombre.string' => 'El nombre del curso debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del curso no puede tener más de 255 caracteres.',
            'nombre.unique' => 'Ya existe un curso con este nombre.',
            'duracion.required' => 'La duración del curso es obligatoria.',
            'duracion.numeric' => 'La duración del curso debe ser un número.',
            'duracion.min' => 'La duración del curso debe ser mayor o igual a 0.',
            'status.integer' => 'El estado debe ser un número entero.',
            'status.in' => 'El estado debe ser 0 (Inactivo) o 1 (Activo).',
        ];
    }
}
