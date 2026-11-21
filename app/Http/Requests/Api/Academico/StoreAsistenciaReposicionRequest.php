<?php

namespace App\Http\Requests\Api\Academico;

use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\AsistenciaReposicion;
use Illuminate\Foundation\Http\FormRequest;

class StoreAsistenciaReposicionRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Un alumno solo puede reponer sus propias clases: se toma su id aunque envíe otro.
     */
    protected function prepareForValidation(): void
    {
        if ($this->user()?->esSoloEstudiante()) {
            $this->merge(['estudiante_id' => $this->user()->id]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'estudiante_id' => ['required', 'integer', 'exists:users,id'],
            'clase_origen_id' => ['required', 'integer', 'exists:asistencia_clases_programadas,id'],
            'clase_destino_id' => ['required', 'integer', 'exists:asistencia_clases_programadas,id', 'different:clase_origen_id'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estudiante_id.required' => 'El estudiante es obligatorio.',
            'estudiante_id.exists' => 'El estudiante seleccionado no existe.',
            'clase_origen_id.required' => 'La clase que no tomará es obligatoria.',
            'clase_origen_id.exists' => 'La clase de origen no existe.',
            'clase_destino_id.required' => 'La clase en la que la tomará es obligatoria.',
            'clase_destino_id.exists' => 'La clase de destino no existe.',
            'clase_destino_id.different' => 'La clase de destino debe ser distinta a la de origen.',
            'motivo.max' => 'El motivo no puede exceder los 500 caracteres.',
        ];
    }

    /**
     * La reposición solo cabe entre grupos distintos del mismo curso, en la misma semana y con el
     * mismo módulo (los mismos temas), con clases que no estén canceladas ni reprogramadas, y para
     * un estudiante con matrícula en la clase de origen sin otra reposición de esa clase.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $origen = AsistenciaClaseProgramada::with(['grupo', 'ciclo'])->findOrFail($this->clase_origen_id);
            $destino = AsistenciaClaseProgramada::with(['grupo', 'ciclo'])->findOrFail($this->clase_destino_id);

            foreach (['origen' => $origen, 'destino' => $destino] as $lado => $clase) {
                if (in_array($clase->estado, ['cancelada', 'reprogramada'], true)) {
                    $validator->errors()->add("clase_{$lado}_id", "La clase de {$lado} está {$clase->estado}.");
                }
            }

            if ($origen->grupo_id === $destino->grupo_id) {
                $validator->errors()->add('clase_destino_id', 'La clase de destino debe ser de otro grupo.');
            }

            if ($origen->ciclo->curso_id !== $destino->ciclo->curso_id) {
                $validator->errors()->add('clase_destino_id', 'La clase de destino debe ser del mismo curso.');
            }

            if ($origen->fecha_clase->format('o-W') !== $destino->fecha_clase->format('o-W')) {
                $validator->errors()->add('clase_destino_id', 'La clase de destino debe ser de la misma semana.');
            }

            if ($origen->grupo->modulo_id !== $destino->grupo->modulo_id) {
                $validator->errors()->add('clase_destino_id', 'La clase de destino debe ser del mismo módulo: no se ven los mismos temas.');
            }

            if (! $origen->grupo->idsEstudiantesVigentes(true)->contains((int) $this->estudiante_id)) {
                $validator->errors()->add('estudiante_id', "El estudiante no tiene matrícula vigente en el grupo {$origen->grupo->nombre}.");
            }

            if (AsistenciaReposicion::where('estudiante_id', $this->estudiante_id)->where('clase_origen_id', $origen->id)->exists()) {
                $validator->errors()->add('clase_origen_id', 'El estudiante ya tiene una reposición registrada para esa clase.');
            }
        });
    }
}
