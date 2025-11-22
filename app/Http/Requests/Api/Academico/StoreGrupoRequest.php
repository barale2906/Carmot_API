<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use App\Traits\HasJornadaStatus;
use App\Traits\HasJornadaStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreGrupoRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation, HasJornadaStatus, HasJornadaStatusValidation;

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
            'sede_id' => 'required|integer|exists:sedes,id',
            'modulo_id' => 'required|integer|exists:modulos,id',
            'profesor_id' => 'required|integer|exists:users,id',
            'nombre' => 'required|string|max:255|unique:grupos,nombre',
            'inscritos' => 'required|integer|min:0|max:50',
            'jornada' => self::getJornadaValidationRule(),
            'status' => self::getStatusValidationRule(),

            // Horarios opcionales
            'horarios' => 'sometimes|array|min:1',
            'horarios.*.area_id' => 'required_with:horarios|integer|exists:areas,id',
            'horarios.*.dia' => 'required_with:horarios|string|in:lunes,martes,miércoles,jueves,viernes,sábado,domingo',
            'horarios.*.hora' => 'required_with:horarios|date_format:H:i',
            'horarios.*.duracion_horas' => 'sometimes|integer|min:1|max:8',
            'horarios.*.status' => 'sometimes|integer|in:0,1',
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
            'sede_id.required' => 'La sede es obligatoria.',
            'sede_id.integer' => 'La sede debe ser un número entero.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
            'modulo_id.required' => 'El módulo es obligatorio.',
            'modulo_id.integer' => 'El módulo debe ser un número entero.',
            'modulo_id.exists' => 'El módulo seleccionado no existe.',
            'profesor_id.required' => 'El profesor es obligatorio.',
            'profesor_id.integer' => 'El profesor debe ser un número entero.',
            'profesor_id.exists' => 'El profesor seleccionado no existe.',
            'nombre.required' => 'El nombre del grupo es obligatorio.',
            'nombre.string' => 'El nombre del grupo debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del grupo no puede tener más de 255 caracteres.',
            'nombre.unique' => 'Ya existe un grupo con este nombre.',
            'inscritos.required' => 'El número de inscritos es obligatorio.',
            'inscritos.integer' => 'El número de inscritos debe ser un número entero.',
            'inscritos.min' => 'El número de inscritos no puede ser menor a 0.',
            'inscritos.max' => 'El número de inscritos no puede ser mayor a 50.',

            // Mensajes para horarios
            'horarios.array' => 'Los horarios deben ser un arreglo.',
            'horarios.min' => 'Debe especificar al menos un horario.',
            'horarios.*.area_id.required_with' => 'El área es obligatoria para cada horario.',
            'horarios.*.area_id.integer' => 'El área debe ser un número entero.',
            'horarios.*.area_id.exists' => 'El área seleccionada no existe.',
            'horarios.*.dia.required_with' => 'El día de la semana es obligatorio.',
            'horarios.*.dia.string' => 'El día debe ser una cadena de texto.',
            'horarios.*.dia.in' => 'El día debe ser: lunes, martes, miércoles, jueves, viernes, sábado o domingo.',
            'horarios.*.hora.required_with' => 'La hora es obligatoria.',
            'horarios.*.hora.date_format' => 'La hora debe tener el formato HH:MM.',
            'horarios.*.duracion_horas.integer' => 'La duración debe ser un número entero.',
            'horarios.*.duracion_horas.min' => 'La duración debe ser de al menos 1 hora.',
            'horarios.*.duracion_horas.max' => 'La duración no puede ser mayor a 8 horas.',
            'horarios.*.status.integer' => 'El estado debe ser un número entero.',
            'horarios.*.status.in' => 'El estado debe ser 0 (Inactivo) o 1 (Activo).',
        ], array_merge(self::getStatusValidationMessages(), self::getJornadaValidationMessages()));
    }

    /**
     * Obtiene los atributos personalizados para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sede_id' => 'sede',
            'modulo_id' => 'módulo',
            'profesor_id' => 'profesor',
            'inscritos' => 'número de inscritos',
            'jornada' => 'jornada',
            'horarios' => 'horarios',
            'horarios.*.area_id' => 'área',
            'horarios.*.dia' => 'día',
            'horarios.*.hora' => 'hora',
            'horarios.*.duracion_horas' => 'duración en horas',
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
