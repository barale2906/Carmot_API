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
            'fecha_inicio' => 'required|date|after_or_equal:today',
            'fecha_fin' => 'nullable|date|after:fecha_inicio',
            'fecha_fin_automatica' => 'nullable|boolean',
            'inscritos' => 'nullable|integer|min:0',
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
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio debe ser igual o posterior a hoy.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'fecha_fin_automatica.boolean' => 'El cálculo automático de fecha de fin debe ser verdadero o falso.',
            'inscritos.integer' => 'El número de inscritos debe ser un número entero.',
            'inscritos.min' => 'El número de inscritos no puede ser negativo.',
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
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
            'fecha_fin_automatica' => 'cálculo automático de fecha de fin',
            'inscritos' => 'inscritos',
        ];
    }
}
