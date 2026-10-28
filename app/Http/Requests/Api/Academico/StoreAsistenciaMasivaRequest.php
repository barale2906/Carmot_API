<?php

namespace App\Http\Requests\Api\Academico;

use App\Models\Academico\Asistencia;
use App\Models\Academico\AsistenciaClaseProgramada;
use App\Http\Requests\Api\Academico\Concerns\ValidaEstudiantesDelGrupo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAsistenciaMasivaRequest extends FormRequest
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
            'clase_programada_id' => ['required', 'integer', 'exists:asistencia_clases_programadas,id'],
            'asistencias' => ['required', 'array', 'min:1'],
            'asistencias.*.estudiante_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'asistencias.*.estado' => ['required', Rule::in(Asistencia::ESTADOS)],
            'asistencias.*.justificada' => ['sometimes', 'boolean'],
            'asistencias.*.justificacion' => ['nullable', 'required_if:asistencias.*.justificada,true', 'string', 'max:500'],
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
            'asistencias.*.estudiante_id.distinct' => 'Un estudiante aparece repetido en la lista.',
            'asistencias.*.estado.required' => 'El campo estado es obligatorio en cada asistencia.',
            'asistencias.*.estado.in' => 'El estado debe ser: presente, ausente o tardanza (la justificación se marca aparte).',
            'asistencias.*.justificada.boolean' => 'El campo justificada debe ser verdadero o falso.',
            'asistencias.*.justificacion.required_if' => 'Indique el motivo de cada justificación.',
            'asistencias.*.justificacion.max' => 'El motivo de la justificación no puede exceder los 500 caracteres.',
            'asistencias.*.observaciones.string' => 'Las observaciones deben ser texto.',
            'asistencias.*.observaciones.max' => 'Las observaciones no pueden exceder los 5000 caracteres.',
        ];
    }

    /**
     * No se toma asistencia en una clase cancelada ni en una que todavía no ocurre,
     * solo a estudiantes con matrícula vigente en el grupo de la clase, y solo se justifica
     * una ausencia o una tardanza (quien está presente no tiene nada que justificar).
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('asistencias', []) as $i => $registro) {
                if (($registro['estado'] ?? null) === 'presente' && filter_var($registro['justificada'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $validator->errors()->add("asistencias.{$i}.justificada", 'Solo se justifica una ausencia o una tardanza.');
                }
            }

            $clase = AsistenciaClaseProgramada::find($this->clase_programada_id);

            if (! $clase) {
                return;
            }

            if ($clase->estado === 'cancelada') {
                $validator->errors()->add('clase_programada_id', 'La clase está cancelada: no se puede tomar asistencia.');
            } elseif ($clase->fecha_clase->isAfter(today())) {
                $validator->errors()->add('clase_programada_id', 'La clase es del ' . $clase->fecha_clase->format('d/m/Y') . ': la asistencia se toma el día de la clase o después.');
            }

            $this->validarEstudiantesDelGrupo(
                $validator,
                $clase->grupo,
                array_column((array) $this->input('asistencias', []), 'estudiante_id'),
                'asistencias'
            );
        });
    }
}
