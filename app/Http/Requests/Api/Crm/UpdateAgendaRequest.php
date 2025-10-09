<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgendaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'referido_id' => [
                'sometimes',
                'integer',
                'exists:referidos,id'
            ],
            'agendador_id' => [
                'sometimes',
                'integer',
                'exists:users,id'
            ],
            'fecha' => [
                'sometimes',
                'date',
                'after_or_equal:today'
            ],
            'hora' => [
                'sometimes',
                'date_format:H:i'
            ],
            'jornada' => [
                'sometimes',
                'string',
                Rule::in(['am', 'pm'])
            ],
            'status' => [
                'sometimes',
                'integer',
                Rule::in([0, 1, 2, 3, 4])
            ]
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
            'referido_id.exists' => 'El referido seleccionado no existe.',
            'agendador_id.exists' => 'El agendador seleccionado no existe.',
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'fecha.after_or_equal' => 'La fecha no puede ser anterior al día de hoy.',
            'hora.date_format' => 'La hora debe tener el formato HH:MM.',
            'jornada.in' => 'La jornada debe ser "am" o "pm".',
            'status.in' => 'El estado debe ser: 0 (Agendado), 1 (Asistió), 2 (No asistió), 3 (Reprogramó) o 4 (Canceló).'
        ];
    }

    /**
     * Prepara los datos para la validación.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Si se está actualizando el referido, también actualizar el agendador
        if ($this->has('referido_id')) {
            $this->merge([
                'agendador_id' => auth()->id()
            ]);
        }
    }
}
