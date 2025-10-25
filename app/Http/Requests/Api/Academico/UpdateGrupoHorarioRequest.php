<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGrupoHorarioRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
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
            'horarios' => 'sometimes|array|min:1',
            'horarios.*.id' => 'sometimes|integer|exists:horarios,id',
            'horarios.*.area_id' => 'sometimes|integer|exists:areas,id',
            'horarios.*.dia' => 'sometimes|string|in:lunes,martes,miércoles,jueves,viernes,sábado,domingo',
            'horarios.*.hora' => 'sometimes|date_format:H:i',
            'horarios.*.status' => self::getStatusValidationRule(),
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'horarios.array' => 'Los horarios deben ser un arreglo.',
            'horarios.min' => 'Debe especificar al menos un horario.',
            'horarios.*.id.integer' => 'El ID del horario debe ser un número entero.',
            'horarios.*.id.exists' => 'El horario seleccionado no existe.',
            'horarios.*.area_id.integer' => 'El área debe ser un número entero.',
            'horarios.*.area_id.exists' => 'El área seleccionada no existe.',
            'horarios.*.dia.string' => 'El día debe ser una cadena de texto.',
            'horarios.*.dia.in' => 'El día debe ser: lunes, martes, miércoles, jueves, viernes, sábado o domingo.',
            'horarios.*.hora.date_format' => 'La hora debe tener el formato HH:MM.',
        ], self::getStatusValidationMessages());
    }

    /**
     * Obtiene los atributos personalizados para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'horarios' => 'horarios',
            'horarios.*.id' => 'ID del horario',
            'horarios.*.area_id' => 'área',
            'horarios.*.dia' => 'día',
            'horarios.*.hora' => 'hora',
        ];
    }

    /**
     * Prepara los datos para la validación.
     */
    protected function prepareForValidation(): void
    {
        // Asegurar que el tipo sea false (horario de grupo) y el periodo sea true (inicio)
        if ($this->has('horarios')) {
            $horarios = $this->input('horarios', []);
            foreach ($horarios as $index => $horario) {
                $horarios[$index]['tipo'] = false; // Horario de grupo
                $horarios[$index]['periodo'] = true; // Hora de inicio
                $horarios[$index]['status'] = $horario['status'] ?? 1; // Activo por defecto
            }
            $this->merge(['horarios' => $horarios]);
        }
    }
}
