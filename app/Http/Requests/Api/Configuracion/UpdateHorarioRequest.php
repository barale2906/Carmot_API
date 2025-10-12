<?php

namespace App\Http\Requests\Api\Configuracion;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHorarioRequest extends FormRequest
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
            'sede_id' => ['sometimes', 'integer', 'exists:sedes,id'],
            'area_id' => ['sometimes', 'integer', 'exists:areas,id'],
            'grupo_id' => ['sometimes', 'integer', 'nullable'],
            'grupo_nombre' => ['sometimes', 'string', 'max:255', 'nullable'],
            'tipo' => ['sometimes', 'boolean'],
            'periodo' => ['sometimes', 'boolean'],
            'dia' => ['sometimes', 'string', 'in:lunes,martes,miércoles,jueves,viernes,sábado,domingo'],
            'hora' => ['sometimes', 'date_format:H:i:s'],
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
            'sede_id.integer' => 'La sede debe ser un número entero.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
            'area_id.integer' => 'El área debe ser un número entero.',
            'area_id.exists' => 'El área seleccionada no existe.',
            'grupo_id.integer' => 'El ID del grupo debe ser un número entero.',
            'grupo_nombre.string' => 'El nombre del grupo debe ser una cadena de texto.',
            'grupo_nombre.max' => 'El nombre del grupo no puede exceder los 255 caracteres.',
            'tipo.boolean' => 'El tipo debe ser verdadero o falso.',
            'periodo.boolean' => 'El período debe ser verdadero o falso.',
            'dia.string' => 'El día debe ser una cadena de texto.',
            'dia.in' => 'El día debe ser uno de: lunes, martes, miércoles, jueves, viernes, sábado, domingo.',
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
