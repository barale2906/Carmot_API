<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InterrumpirAplazamientoRequest extends FormRequest
{
    /** @return bool */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fecha_reinicio_real' => 'required|date',
            'observaciones'       => 'nullable|string|max:1000',
        ];
    }

    /**
     * La fecha de reinicio real debe estar entre la fecha_inicio_original y la fecha_reinicio_probable (exclusivos).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $aplazamiento = $this->route('aplazamiento');

            if (!$aplazamiento || !$aplazamiento->esPendiente()) {
                $v->errors()->add('aplazamiento', 'Solo se puede interrumpir un aplazamiento en estado Pendiente.');
                return;
            }

            $fechaReal = $this->date('fecha_reinicio_real');
            if (!$fechaReal) {
                return;
            }

            if ($fechaReal->lte($aplazamiento->fecha_inicio_original)) {
                $v->errors()->add(
                    'fecha_reinicio_real',
                    'La fecha de reinicio real debe ser posterior a la fecha de inicio original ('
                    . $aplazamiento->fecha_inicio_original->format('Y-m-d') . ').'
                );
            }

            if ($fechaReal->gte($aplazamiento->fecha_reinicio_probable)) {
                $v->errors()->add(
                    'fecha_reinicio_real',
                    'La fecha de reinicio real debe ser anterior a la fecha probable ('
                    . $aplazamiento->fecha_reinicio_probable->format('Y-m-d')
                    . '). Si el ciclo reinicia en la fecha probable o después, use Confirmar o Ampliar.'
                );
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fecha_reinicio_real.required' => 'La fecha de reinicio real es obligatoria para interrumpir el aplazamiento.',
            'fecha_reinicio_real.date'     => 'La fecha de reinicio real debe ser una fecha válida.',
            'observaciones.max'            => 'Las observaciones no pueden superar los 1000 caracteres.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'fecha_reinicio_real' => 'fecha de reinicio real',
            'observaciones'       => 'observaciones',
        ];
    }
}
