<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;

class StoreAsistenciaConfiguracionRequest extends FormRequest
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
            'curso_id' => ['nullable', 'integer', 'exists:cursos,id'],
            'modulo_id' => ['nullable', 'integer', 'exists:modulos,id'],
            'porcentaje_minimo' => ['required', 'numeric', 'min:0', 'max:100'],
            'horas_minimas' => ['nullable', 'integer', 'min:0'],
            'aplicar_justificaciones' => ['sometimes', 'boolean'],
            'perder_por_fallas' => ['sometimes', 'boolean'],
            'fecha_inicio_vigencia' => ['nullable', 'date'],
            'fecha_fin_vigencia' => ['nullable', 'date', 'after_or_equal:fecha_inicio_vigencia'],
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
            'curso_id.integer' => 'El campo curso debe ser un número entero.',
            'curso_id.exists' => 'El curso seleccionado no existe.',
            'modulo_id.integer' => 'El campo módulo debe ser un número entero.',
            'modulo_id.exists' => 'El módulo seleccionado no existe.',
            'porcentaje_minimo.required' => 'El campo porcentaje mínimo es obligatorio.',
            'porcentaje_minimo.numeric' => 'El campo porcentaje mínimo debe ser un número.',
            'porcentaje_minimo.min' => 'El porcentaje mínimo debe ser mayor o igual a 0.',
            'porcentaje_minimo.max' => 'El porcentaje mínimo no puede ser mayor a 100.',
            'horas_minimas.integer' => 'El campo horas mínimas debe ser un número entero.',
            'horas_minimas.min' => 'Las horas mínimas deben ser mayor o igual a 0.',
            'aplicar_justificaciones.boolean' => 'El campo aplicar justificaciones debe ser verdadero o falso.',
            'perder_por_fallas.boolean' => 'El campo perder por fallas debe ser verdadero o falso.',
            'fecha_inicio_vigencia.date' => 'El campo fecha de inicio de vigencia debe ser una fecha válida.',
            'fecha_fin_vigencia.date' => 'El campo fecha de fin de vigencia debe ser una fecha válida.',
            'fecha_fin_vigencia.after_or_equal' => 'La fecha de fin de vigencia debe ser posterior o igual a la fecha de inicio.',
            'observaciones.string' => 'Las observaciones deben ser texto.',
            'observaciones.max' => 'Las observaciones no pueden exceder los 5000 caracteres.',
        ];
    }
}
