<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgendaRequest extends FormRequest
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
                'required',
                'integer',
                'exists:referidos,id'
            ],
            'agendador_id' => [
                'sometimes',
                'integer',
                'exists:users,id'
            ],
            'fecha' => [
                'required',
                'date',
                'after_or_equal:today'
            ],
            'hora' => [
                'required',
                'date_format:H:i'
            ],
            'jornada' => [
                'required',
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
            'referido_id.required' => 'El referido es obligatorio.',
            'referido_id.exists' => 'El referido seleccionado no existe.',
            'agendador_id.exists' => 'El agendador seleccionado no existe.',
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'fecha.after_or_equal' => 'La fecha no puede ser anterior al día de hoy.',
            'hora.required' => 'La hora es obligatoria.',
            'hora.date_format' => 'La hora debe tener el formato HH:MM.',
            'jornada.required' => 'La jornada es obligatoria.',
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
        // Establecer el agendador_id automáticamente como el usuario autenticado
        $this->merge([
            'agendador_id' => auth()->id(),
            'status' => $this->status ?? 0 // Por defecto, status 0 (Agendado)
        ]);
    }
}
