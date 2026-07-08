<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AmpliarAplazamientoRequest extends FormRequest
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
            'fecha_reinicio_probable' => 'required|date',
            'tipo_aplazamiento_id'   => 'nullable|integer|exists:tipo_aplazamientos,id',
            'mover_cartera'           => 'nullable|boolean',
            'observaciones'           => 'nullable|string|max:1000',
        ];
    }

    /**
     * La nueva fecha debe ser posterior a la fecha_reinicio_probable del aplazamiento actual.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $aplazamiento = $this->route('aplazamiento');

            if (!$aplazamiento || !$aplazamiento->esPendiente()) {
                $v->errors()->add('aplazamiento', 'Solo se puede ampliar un aplazamiento en estado Pendiente.');
                return;
            }

            $nuevaFecha = $this->date('fecha_reinicio_probable');
            if ($nuevaFecha && $nuevaFecha->lte($aplazamiento->fecha_reinicio_probable)) {
                $v->errors()->add(
                    'fecha_reinicio_probable',
                    'La nueva fecha de reinicio debe ser posterior a la fecha probable actual ('
                    . $aplazamiento->fecha_reinicio_probable->format('Y-m-d') . ').'
                );
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fecha_reinicio_probable.required' => 'La nueva fecha probable de reinicio es obligatoria.',
            'fecha_reinicio_probable.date'     => 'La nueva fecha probable de reinicio debe ser una fecha válida.',
            'tipo_aplazamiento_id.exists'      => 'El tipo de aplazamiento seleccionado no existe.',
            'observaciones.max'                => 'Las observaciones no pueden superar los 1000 caracteres.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'fecha_reinicio_probable' => 'nueva fecha probable de reinicio',
            'tipo_aplazamiento_id'   => 'tipo de aplazamiento',
            'mover_cartera'           => 'mover cartera',
            'observaciones'           => 'observaciones',
        ];
    }
}
