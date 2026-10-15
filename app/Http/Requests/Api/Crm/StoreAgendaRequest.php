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
     * Prepara los datos para la validación: agendador por defecto, estado inicial y
     * jornada derivada de la hora.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $datos = [
            // Se respeta el agendador asignado; si no viene, es el usuario autenticado (B46)
            'agendador_id' => $this->agendador_id ?? auth()->id(),
            'status' => $this->status ?? 0, // Por defecto, status 0 (Agendado)
        ];

        // La jornada se deriva de la hora para que no se contradigan (15:00 nunca es "am", B12)
        if (is_string($this->hora) && preg_match('/^(\d{2}):\d{2}$/', $this->hora, $m)) {
            $datos['jornada'] = (int) $m[1] < 12 ? 'am' : 'pm';
        }

        $this->merge($datos);
    }
}
