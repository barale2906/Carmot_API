<?php

namespace App\Http\Requests\Api\Academico;

use App\Http\Requests\Api\Academico\Concerns\ValidaEstudiantesDelGrupo;
use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Asistencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAsistenciaRequest extends FormRequest
{
    use ValidaEstudiantesDelGrupo;

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
            'estado' => ['required', Rule::in(Asistencia::ESTADOS)],
            'justificada' => ['sometimes', 'boolean'],
            'justificacion' => ['nullable', 'required_if:justificada,true', 'string', 'max:500'],
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
            'estado.in' => 'El estado debe ser: presente, ausente o tardanza (la justificación se marca aparte).',
            'hora_registro.date_format' => 'El formato de hora debe ser H:i:s (ejemplo: 14:30:00).',
            'justificada.boolean' => 'El campo justificada debe ser verdadero o falso.',
            'justificacion.required_if' => 'Indique el motivo de la justificación.',
            'justificacion.max' => 'El motivo de la justificación no puede exceder los 500 caracteres.',
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

    /**
     * Solo se registra asistencia a estudiantes con matrícula vigente en el grupo de la clase,
     * y solo se justifica una ausencia o una tardanza.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('estado') === 'presente' && $this->boolean('justificada')) {
                $validator->errors()->add('justificada', 'Solo se justifica una ausencia o una tardanza.');
            }

            $clase = AsistenciaClaseProgramada::with('grupo')->find($this->input('clase_programada_id'));
            $this->validarEstudiantesDelGrupo($validator, $clase?->grupo, [$this->input('estudiante_id')]);
        });
    }
}
