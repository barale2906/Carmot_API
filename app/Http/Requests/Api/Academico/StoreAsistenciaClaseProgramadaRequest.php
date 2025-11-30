<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAsistenciaClaseProgramadaRequest extends FormRequest
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
            'grupo_id' => ['required', 'integer', 'exists:grupos,id'],
            'ciclo_id' => ['required', 'integer', 'exists:ciclos,id'],
            'fecha_clase' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i:s'],
            'hora_fin' => ['required', 'date_format:H:i:s', 'after:hora_inicio'],
            'duracion_horas' => ['required', 'numeric', 'min:0'],
            'estado' => ['sometimes', Rule::in(['programada', 'dictada', 'cancelada', 'reprogramada'])],
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
            'grupo_id.required' => 'El campo grupo es obligatorio.',
            'grupo_id.integer' => 'El campo grupo debe ser un número entero.',
            'grupo_id.exists' => 'El grupo seleccionado no existe.',
            'ciclo_id.required' => 'El campo ciclo es obligatorio.',
            'ciclo_id.integer' => 'El campo ciclo debe ser un número entero.',
            'ciclo_id.exists' => 'El ciclo seleccionado no existe.',
            'fecha_clase.required' => 'El campo fecha de clase es obligatorio.',
            'fecha_clase.date' => 'El campo fecha de clase debe ser una fecha válida.',
            'hora_inicio.required' => 'El campo hora de inicio es obligatorio.',
            'hora_inicio.date_format' => 'El formato de hora de inicio debe ser H:i:s (ejemplo: 14:30:00).',
            'hora_fin.required' => 'El campo hora de fin es obligatorio.',
            'hora_fin.date_format' => 'El formato de hora de fin debe ser H:i:s (ejemplo: 16:30:00).',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
            'duracion_horas.required' => 'El campo duración en horas es obligatorio.',
            'duracion_horas.numeric' => 'El campo duración en horas debe ser un número.',
            'duracion_horas.min' => 'La duración en horas debe ser mayor o igual a 0.',
            'estado.in' => 'El estado debe ser: programada, dictada, cancelada o reprogramada.',
            'observaciones.string' => 'Las observaciones deben ser texto.',
            'observaciones.max' => 'Las observaciones no pueden exceder los 5000 caracteres.',
        ];
    }
}
