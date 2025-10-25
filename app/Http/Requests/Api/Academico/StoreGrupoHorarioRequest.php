<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreGrupoHorarioRequest extends FormRequest
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
            'horarios' => 'required|array|min:1',
            'horarios.*.area_id' => 'required|integer|exists:areas,id',
            'horarios.*.dia' => 'required|string|in:lunes,martes,miércoles,jueves,viernes,sábado,domingo',
            'horarios.*.hora' => 'required|date_format:H:i',
            'horarios.*.duracion_horas' => 'sometimes|integer|min:1|max:8',
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
            'horarios.required' => 'Los horarios son obligatorios.',
            'horarios.array' => 'Los horarios deben ser un arreglo.',
            'horarios.min' => 'Debe especificar al menos un horario.',
            'horarios.*.area_id.required' => 'El área es obligatoria para cada horario.',
            'horarios.*.area_id.integer' => 'El área debe ser un número entero.',
            'horarios.*.area_id.exists' => 'El área seleccionada no existe.',
            'horarios.*.dia.required' => 'El día de la semana es obligatorio.',
            'horarios.*.dia.string' => 'El día debe ser una cadena de texto.',
            'horarios.*.dia.in' => 'El día debe ser: lunes, martes, miércoles, jueves, viernes, sábado o domingo.',
            'horarios.*.hora.required' => 'La hora es obligatoria.',
            'horarios.*.hora.date_format' => 'La hora debe tener el formato HH:MM.',
            'horarios.*.duracion_horas.integer' => 'La duración debe ser un número entero.',
            'horarios.*.duracion_horas.min' => 'La duración debe ser de al menos 1 hora.',
            'horarios.*.duracion_horas.max' => 'La duración no puede ser mayor a 8 horas.',
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
                $horarios[$index]['duracion_horas'] = $horario['duracion_horas'] ?? 1; // 1 hora por defecto
            }
            $this->merge(['horarios' => $horarios]);
        }
    }
}
