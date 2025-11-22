<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreMatriculaRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    /**
     * Obtiene las opciones de estado para matrículas.
     * Sobrescribe el método del trait para incluir el estado "Anulado".
     *
     * @return array<string, string> Array con los estados disponibles
     */
    public static function getActiveStatusOptions(): array
    {
        return [
            0 => 'Inactivo',
            1 => 'Activo',
            2 => 'Anulado',
        ];
    }

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
            'curso_id' => 'required|integer|exists:cursos,id',
            'ciclo_id' => 'required|integer|exists:ciclos,id',
            'estudiante_id' => 'required|integer|exists:users,id',
            'matriculado_por_id' => 'required|integer|exists:users,id',
            'comercial_id' => 'required|integer|exists:users,id',
            'fecha_matricula' => 'required|date',
            'fecha_inicio' => 'required|date|after_or_equal:fecha_matricula',
            'monto' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string|max:5000',
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
            'curso_id.required' => 'El curso es obligatorio.',
            'curso_id.integer' => 'El curso debe ser un número entero.',
            'curso_id.exists' => 'El curso seleccionado no existe.',
            'ciclo_id.required' => 'El ciclo es obligatorio.',
            'ciclo_id.integer' => 'El ciclo debe ser un número entero.',
            'ciclo_id.exists' => 'El ciclo seleccionado no existe.',
            'estudiante_id.required' => 'El estudiante es obligatorio.',
            'estudiante_id.integer' => 'El estudiante debe ser un número entero.',
            'estudiante_id.exists' => 'El estudiante seleccionado no existe.',
            'matriculado_por_id.required' => 'El usuario que realiza la matrícula es obligatorio.',
            'matriculado_por_id.integer' => 'El usuario que realiza la matrícula debe ser un número entero.',
            'matriculado_por_id.exists' => 'El usuario que realiza la matrícula no existe.',
            'comercial_id.required' => 'El usuario comercial es obligatorio.',
            'comercial_id.integer' => 'El usuario comercial debe ser un número entero.',
            'comercial_id.exists' => 'El usuario comercial seleccionado no existe.',
            'fecha_matricula.required' => 'La fecha de matrícula es obligatoria.',
            'fecha_matricula.date' => 'La fecha de matrícula debe ser una fecha válida.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio debe ser igual o posterior a la fecha de matrícula.',
            'monto.required' => 'El monto es obligatorio.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.min' => 'El monto debe ser mayor o igual a 0.',
            'observaciones.string' => 'Las observaciones deben ser una cadena de texto.',
            'observaciones.max' => 'Las observaciones no pueden tener más de 5000 caracteres.',
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
            'curso_id' => 'curso',
            'ciclo_id' => 'ciclo',
            'estudiante_id' => 'estudiante',
            'matriculado_por_id' => 'usuario que realiza la matrícula',
            'comercial_id' => 'usuario comercial',
            'fecha_matricula' => 'fecha de matrícula',
            'fecha_inicio' => 'fecha de inicio',
        ];
    }
}
