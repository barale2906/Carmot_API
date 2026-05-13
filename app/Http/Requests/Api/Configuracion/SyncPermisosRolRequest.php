<?php

namespace App\Http\Requests\Api\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class SyncPermisosRolRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta petición.
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
            'permissions'   => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
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
            'permissions.required'  => 'Debe enviar el array de permisos (puede ser vacío usando []).',
            'permissions.array'     => 'Los permisos deben ser un array.',
            'permissions.*.exists'  => 'Uno o más permisos indicados no existen.',
        ];
    }

    /**
     * Atributos personalizados para mensajes de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'permissions'   => 'permisos',
            'permissions.*' => 'permiso',
        ];
    }
}
