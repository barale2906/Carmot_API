<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreGrupoRequest extends FormRequest
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
            'sede_id' => 'required|integer|exists:sedes,id',
            'modulo_id' => 'required|integer|exists:modulos,id',
            'profesor_id' => 'required|integer|exists:users,id',
            'nombre' => 'required|string|max:255|unique:grupos,nombre',
            'inscritos' => 'required|integer|min:0|max:50',
            'jornada' => 'required|integer|in:0,1,2,3',
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
            'jornada.required' => 'La jornada es obligatoria.',
            'jornada.integer' => 'La jornada debe ser un número entero.',
            'jornada.in' => 'La jornada debe ser: 0 (Mañana), 1 (Tarde), 2 (Noche) o 3 (Fin de semana).',
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
            'sede_id' => 'sede',
            'modulo_id' => 'módulo',
            'profesor_id' => 'profesor',
            'inscritos' => 'número de inscritos',
            'jornada' => 'jornada',
        ];
    }
}
