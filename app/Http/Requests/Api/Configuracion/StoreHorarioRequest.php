<?php

namespace App\Http\Requests\Api\Configuracion;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreHorarioRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;
    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sede_id' => ['required', 'integer', 'exists:sedes,id'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'grupo_id' => ['sometimes', 'integer', 'nullable'],
            'grupo_nombre' => ['sometimes', 'string', 'max:255', 'nullable'],
            'tipo' => ['required', 'boolean'],
            'periodo' => ['required', 'boolean'],
            'dia' => ['required', 'string', 'in:lunes,martes,miércoles,jueves,viernes,sábado,domingo'],
            'hora' => ['required', 'date_format:H:i:s'],
            'status' => [self::getStatusValidationRule()],
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
            'sede_id.required' => 'La sede es obligatoria.',
            'sede_id.integer' => 'La sede debe ser un número entero.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
            'area_id.required' => 'El área es obligatoria.',
            'area_id.integer' => 'El área debe ser un número entero.',
            'area_id.exists' => 'El área seleccionada no existe.',
            'grupo_id.integer' => 'El ID del grupo debe ser un número entero.',
            'grupo_nombre.string' => 'El nombre del grupo debe ser una cadena de texto.',
            'grupo_nombre.max' => 'El nombre del grupo no puede exceder los 255 caracteres.',
            'tipo.required' => 'El tipo de horario es obligatorio.',
            'tipo.boolean' => 'El tipo debe ser verdadero o falso.',
            'periodo.required' => 'El período es obligatorio.',
            'periodo.boolean' => 'El período debe ser verdadero o falso.',
            'dia.required' => 'El día es obligatorio.',
            'dia.string' => 'El día debe ser una cadena de texto.',
            'dia.in' => 'El día debe ser uno de: lunes, martes, miércoles, jueves, viernes, sábado, domingo.',
            'hora.required' => 'La hora es obligatoria.',
            'hora.date_format' => 'La hora debe tener el formato HH:MM:SS.',
        ] + self::getStatusValidationMessages();
    }

    /**
     * Obtiene los atributos personalizados para los mensajes de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sede_id' => 'sede',
            'area_id' => 'área',
            'grupo_id' => 'ID del grupo',
            'grupo_nombre' => 'nombre del grupo',
            'tipo' => 'tipo de horario',
            'periodo' => 'período',
            'dia' => 'día',
            'hora' => 'hora',
        ];
    }
}
