<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use App\Traits\HasJornadaStatus;
use App\Traits\HasJornadaStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGrupoRequest extends FormRequest
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
        $grupoId = $this->route('grupo')->id;

        return [
            'sede_id' => 'sometimes|integer|exists:sedes,id',
            'modulo_id' => 'sometimes|integer|exists:modulos,id',
            'profesor_id' => 'sometimes|integer|exists:users,id',
            'nombre' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('grupos', 'nombre')->ignore($grupoId)
            ],
            'inscritos' => 'sometimes|integer|min:0|max:50',
            'jornada' => self::getJornadaValidationRuleOptional(),
            'status' => self::getStatusValidationRule(),
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
            'sede_id.integer' => 'La sede debe ser un número entero.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
            'modulo_id.integer' => 'El módulo debe ser un número entero.',
            'modulo_id.exists' => 'El módulo seleccionado no existe.',
            'profesor_id.integer' => 'El profesor debe ser un número entero.',
            'profesor_id.exists' => 'El profesor seleccionado no existe.',
            'nombre.string' => 'El nombre del grupo debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del grupo no puede tener más de 255 caracteres.',
            'nombre.unique' => 'Ya existe un grupo con este nombre.',
            'inscritos.integer' => 'El número de inscritos debe ser un número entero.',
            'inscritos.min' => 'El número de inscritos no puede ser menor a 0.',
            'inscritos.max' => 'El número de inscritos no puede ser mayor a 50.',
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
        ];
    }
}
