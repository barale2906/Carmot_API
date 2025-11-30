<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAsistenciaRequest extends FormRequest
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
            'estudiante_id' => ['required', 'integer', 'exists:users,id'],
            'clase_programada_id' => ['required', 'integer', 'exists:asistencia_clases_programadas,id'],
            'estado' => ['required', Rule::in(['presente', 'ausente', 'justificado', 'tardanza'])],
            'hora_registro' => ['nullable', 'date_format:H:i:s'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
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
            'estudiante_id.required' => 'El campo estudiante es obligatorio.',
            'estudiante_id.integer' => 'El campo estudiante debe ser un número entero.',
            'estudiante_id.exists' => 'El estudiante seleccionado no existe.',
            'clase_programada_id.required' => 'El campo clase programada es obligatorio.',
            'clase_programada_id.integer' => 'El campo clase programada debe ser un número entero.',
            'clase_programada_id.exists' => 'La clase programada seleccionada no existe.',
            'estado.required' => 'El campo estado es obligatorio.',
            'estado.in' => 'El estado debe ser: presente, ausente, justificado o tardanza.',
            'hora_registro.date_format' => 'El formato de hora debe ser H:i:s (ejemplo: 14:30:00).',
            'observaciones.string' => 'Las observaciones deben ser texto.',
            'observaciones.max' => 'Las observaciones no pueden exceder los 5000 caracteres.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Si no se proporciona hora_registro, usar la hora actual
        if (!$this->has('hora_registro')) {
            $this->merge([
                'hora_registro' => now()->format('H:i:s'),
            ]);
        }
    }
}
