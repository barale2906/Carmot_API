<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConfirmarAplazamientoRequest extends FormRequest
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
            'fecha_reinicio_real' => 'nullable|date',
            'observaciones'       => 'nullable|string|max:1000',
        ];
    }

    /**
     * Solo se puede confirmar un aplazamiento Pendiente. Si fue ampliado, indica cuál es la
     * ampliación que se debe confirmar (N25).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $aplazamiento = $this->route('aplazamiento');

            if ($aplazamiento && ($ampliacion = $aplazamiento->ampliacionVigente())) {
                $v->errors()->add('aplazamiento', "Este aplazamiento fue ampliado: confirme la ampliación #{$ampliacion->id}, que es la vigente.");
            } elseif (!$aplazamiento || !$aplazamiento->esPendiente()) {
                $v->errors()->add('aplazamiento', 'Solo se puede confirmar un aplazamiento en estado Pendiente.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fecha_reinicio_real.date' => 'La fecha de reinicio real debe ser una fecha válida.',
            'observaciones.max'        => 'Las observaciones no pueden superar los 1000 caracteres.',
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
