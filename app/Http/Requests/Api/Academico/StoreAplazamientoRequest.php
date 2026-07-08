<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAplazamientoRequest extends FormRequest
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
            'tipo_aplazamiento_id'   => 'required|integer|exists:tipo_aplazamientos,id',
            'fecha_reinicio_probable' => 'required|date',
            'fecha_aplazamiento'      => 'nullable|date|before_or_equal:today',
            'mover_cartera'           => 'nullable|boolean',
            'observaciones'           => 'nullable|string|max:1000',
        ];
    }

    /**
     * Validaciones cruzadas: fecha_reinicio_probable debe ser posterior al fecha_inicio del ciclo.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ciclo = $this->route('ciclo');

            if (!$ciclo || !$ciclo->fecha_inicio) {
                $v->errors()->add('ciclo', 'El ciclo no tiene fecha de inicio definida y no puede aplazarse.');
                return;
            }

            if ($ciclo->finalizado) {
                $v->errors()->add('ciclo', 'Un ciclo finalizado no puede aplazarse.');
                return;
            }

            $fechaReinicio = $this->date('fecha_reinicio_probable');
            if ($fechaReinicio && $fechaReinicio->lte($ciclo->fecha_inicio)) {
                $v->errors()->add(
                    'fecha_reinicio_probable',
                    'La fecha de reinicio debe ser posterior a la fecha de inicio actual del ciclo ('
                    . $ciclo->fecha_inicio->format('Y-m-d') . ').'
                );
            }

            // Solo puede haber un aplazamiento Pendiente por ciclo a la vez
            if ($ciclo->aplazamientoActivo()->exists()) {
                $v->errors()->add('ciclo', 'El ciclo ya tiene un aplazamiento pendiente. Confírmelo, amplíelo o reviértalo antes de crear uno nuevo.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tipo_aplazamiento_id.required'   => 'El tipo de aplazamiento es obligatorio.',
            'tipo_aplazamiento_id.exists'      => 'El tipo de aplazamiento seleccionado no existe.',
            'fecha_reinicio_probable.required' => 'La fecha probable de reinicio es obligatoria.',
            'fecha_reinicio_probable.date'     => 'La fecha probable de reinicio debe ser una fecha válida.',
            'fecha_aplazamiento.date'          => 'La fecha de aplazamiento debe ser una fecha válida.',
            'fecha_aplazamiento.before_or_equal' => 'La fecha de aplazamiento no puede ser futura.',
            'observaciones.max'                => 'Las observaciones no pueden superar los 1000 caracteres.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'tipo_aplazamiento_id'   => 'tipo de aplazamiento',
            'fecha_reinicio_probable' => 'fecha probable de reinicio',
            'fecha_aplazamiento'      => 'fecha de aplazamiento',
            'mover_cartera'           => 'mover cartera',
            'observaciones'           => 'observaciones',
        ];
    }
}
