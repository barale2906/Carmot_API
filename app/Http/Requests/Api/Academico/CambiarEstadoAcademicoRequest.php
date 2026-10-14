<?php

namespace App\Http\Requests\Api\Academico;

use App\Models\Academico\Matricula;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoAcademicoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado_academico' => ['required', 'integer', Rule::in(array_keys(Matricula::ESTADOS_ACADEMICOS))],
            'fecha'            => ['required', 'date', 'before_or_equal:today'],
            'motivo'           => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * Obtiene los mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado_academico.required' => 'El estado académico es obligatorio.',
            'estado_academico.in'       => 'El estado académico no es válido.',
            'fecha.required'            => 'La fecha del cambio es obligatoria.',
            'fecha.before_or_equal'     => 'La fecha del cambio no puede ser futura.',
            'motivo.required'           => 'Indica el motivo del cambio de estado.',
            'motivo.min'                => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }

    /**
     * Obtiene los atributos personalizados para los mensajes de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'estado_academico' => 'estado académico',
            'fecha'            => 'fecha',
            'motivo'           => 'motivo',
        ];
    }

    /**
     * Reglas que dependen de la matrícula: no se cambia una anulada, el estado debe ser
     * distinto del actual, la fecha no puede ser anterior a la matrícula y, al reactivar,
     * el estudiante no puede tener ya otra matrícula vigente en el mismo curso.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Matricula $matricula */
            $matricula = $this->route('matricula');
            $nuevo     = (int) $this->estado_academico;

            if ($matricula->status === 2) {
                $validator->errors()->add('estado_academico', 'La matrícula está anulada: no se puede cambiar su estado académico.');
                return;
            }

            if ($matricula->estado_academico === $nuevo) {
                $validator->errors()->add('estado_academico', 'La matrícula ya tiene ese estado académico.');
                return;
            }

            if ($matricula->fecha_matricula && $this->date('fecha')->lt($matricula->fecha_matricula)) {
                $validator->errors()->add('fecha', 'La fecha del cambio no puede ser anterior a la fecha de matrícula.');
            }

            $reactiva = $matricula->status === 0 && ! in_array($nuevo, Matricula::ESTADOS_ACADEMICOS_FINALES, true);
            $otraVigente = $reactiva && Matricula::where('estudiante_id', $matricula->estudiante_id)
                ->where('curso_id', $matricula->curso_id)
                ->where('id', '!=', $matricula->id)
                ->vigentes()
                ->exists();

            if ($otraVigente) {
                $validator->errors()->add('estado_academico', 'El estudiante ya tiene otra matrícula vigente en este curso: no se puede reactivar esta.');
            }
        });
    }
}
