<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreModuloRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;
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
            'nombre' => 'required|string|max:255|unique:modulos,nombre',
            'status' => self::getStatusValidationRule(),
            'curso_ids' => 'sometimes|array',
            'curso_ids.*' => 'integer|exists:cursos,id',
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'nombre.required' => 'El nombre del módulo es obligatorio.',
            'nombre.string' => 'El nombre del módulo debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del módulo no puede tener más de 255 caracteres.',
            'nombre.unique' => 'Ya existe un módulo con este nombre.',
            'curso_ids.array' => 'Los cursos deben ser un array.',
            'curso_ids.*.integer' => 'Cada curso debe ser un número entero.',
            'curso_ids.*.exists' => 'Uno o más cursos seleccionados no existen.',
        ], self::getStatusValidationMessages());
    }
}
