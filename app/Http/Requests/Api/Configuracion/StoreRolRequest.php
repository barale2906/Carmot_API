<?php

namespace App\Http\Requests\Api\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class StoreRolRequest extends FormRequest
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
            'name'           => ['required', 'string', 'max:125', 'unique:roles,name'],
            'guard_name'     => ['sometimes', 'string', 'max:125'],
            'status'         => ['sometimes', 'boolean'],
            'permissions'    => ['sometimes', 'array'],
            'permissions.*'  => ['string', 'exists:permissions,name'],
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
            'name.required'         => 'El nombre del rol es obligatorio.',
            'name.unique'           => 'Ya existe un rol con ese nombre.',
            'name.max'              => 'El nombre del rol no puede exceder los 125 caracteres.',
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
            'name'           => 'nombre del rol',
            'guard_name'     => 'guard',
            'status'         => 'estado',
            'permissions'    => 'permisos',
            'permissions.*'  => 'permiso',
        ];
    }
}
