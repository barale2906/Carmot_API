<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAsistenciaMasivaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'clase_programada_id' => ['required', 'integer', 'exists:asistencia_clases_programadas,id'],
            'asistencias' => ['required', 'array', 'min:1'],
            'asistencias.*.estudiante_id' => ['required', 'integer', 'exists:users,id'],
            'asistencias.*.estado' => ['required', Rule::in(['presente', 'ausente', 'justificado', 'tardanza'])],
            'asistencias.*.observaciones' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'clase_programada_id.required' => 'El campo clase programada es obligatorio.',
            'clase_programada_id.integer' => 'El campo clase programada debe ser un número entero.',
            'clase_programada_id.exists' => 'La clase programada seleccionada no existe.',
            'asistencias.required' => 'El campo asistencias es obligatorio.',
            'asistencias.array' => 'El campo asistencias debe ser un array.',
            'asistencias.min' => 'Debe haber al menos una asistencia.',
            'asistencias.*.estudiante_id.required' => 'El campo estudiante es obligatorio en cada asistencia.',
            'asistencias.*.estudiante_id.integer' => 'El campo estudiante debe ser un número entero.',
            'asistencias.*.estudiante_id.exists' => 'El estudiante seleccionado no existe.',
            'asistencias.*.estado.required' => 'El campo estado es obligatorio en cada asistencia.',
            'asistencias.*.estado.in' => 'El estado debe ser: presente, ausente, justificado o tardanza.',
            'asistencias.*.observaciones.string' => 'Las observaciones deben ser texto.',
            'asistencias.*.observaciones.max' => 'Las observaciones no pueden exceder los 5000 caracteres.',
        ];
    }
}
