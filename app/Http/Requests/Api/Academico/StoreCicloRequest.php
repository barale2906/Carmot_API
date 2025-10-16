<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreCicloRequest extends FormRequest
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
            'sede_id' => 'required|integer|exists:sedes,id',
            'curso_id' => 'required|integer|exists:cursos,id',
            'nombre' => 'required|string|max:255|unique:ciclos,nombre',
            'descripcion' => 'nullable|string|max:1000',
            'grupos' => 'nullable|array',
            'grupos.*' => 'integer|exists:grupos,id',
            'status' => self::getStatusValidationRule(),
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
            'sede_id.required' => 'La sede es obligatoria.',
            'sede_id.integer' => 'La sede debe ser un número entero.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
            'curso_id.required' => 'El curso es obligatorio.',
            'curso_id.integer' => 'El curso debe ser un número entero.',
            'curso_id.exists' => 'El curso seleccionado no existe.',
            'nombre.required' => 'El nombre del ciclo es obligatorio.',
            'nombre.string' => 'El nombre del ciclo debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del ciclo no puede tener más de 255 caracteres.',
            'nombre.unique' => 'Ya existe un ciclo con este nombre.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción no puede tener más de 1000 caracteres.',
            'grupos.array' => 'Los grupos deben ser un array.',
            'grupos.*.integer' => 'Cada grupo debe ser un número entero.',
            'grupos.*.exists' => 'Uno o más grupos seleccionados no existen.',
        ], self::getStatusValidationMessages());
    }

    /**
     * Obtiene los atributos personalizados para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sede_id' => 'sede',
            'curso_id' => 'curso',
            'descripcion' => 'descripción',
        ];
    }
}
