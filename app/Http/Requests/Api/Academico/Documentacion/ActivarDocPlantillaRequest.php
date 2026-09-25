<?php

namespace App\Http\Requests\Api\Academico\Documentacion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la activación de una versión de plantilla.
 *
 * @package App\Http\Requests\Api\Academico\Documentacion
 */
class ActivarDocPlantillaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado (los permisos se validan por middleware).
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación de la petición.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha_inicio' => 'sometimes|date',
        ];
    }

    /**
     * Mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_inicio.date' => 'La fecha de inicio de vigencia debe ser una fecha válida.',
        ];
    }

    /**
     * Nombres legibles de los campos para los mensajes de error.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha_inicio' => 'fecha de inicio de vigencia',
        ];
    }
}
