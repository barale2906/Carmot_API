<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\Crm\Agenda;
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
            // Sin tipo, la agenda es una visita (cita presencial)
            'tipo' => [
                'sometimes',
                'string',
                Rule::in(array_keys(Agenda::TIPOS))
            ],
            'nota' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000'
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
            'tipo.in' => 'El tipo debe ser: ' . implode(', ', Agenda::TIPOS) . '.',
            'nota.max' => 'La nota no puede exceder los 1000 caracteres.',
            'status.in' => 'El estado debe ser: 0 (Agendado), 1 (Realizada: asistió, contestó o respondió), 2 (No realizada), 3 (Reprogramó) o 4 (Canceló).'
        ];
    }

    /**
     * Prepara los datos para la validación: si cambia el referido sin indicar agendador,
     * queda el usuario autenticado (B46); si cambia la hora, la jornada se deriva de ella (B12).
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $datos = [];

        if ($this->has('referido_id') && ! $this->has('agendador_id')) {
            $datos['agendador_id'] = auth()->id();
        }

        if (is_string($this->hora) && preg_match('/^\d{2}:\d{2}$/', $this->hora)) {
            $datos['jornada'] = Agenda::jornadaDeHora($this->hora);
        }

        $this->merge($datos);
    }
}
