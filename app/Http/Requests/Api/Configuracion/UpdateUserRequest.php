<?php

namespace App\Http\Requests\Api\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ----------------------------------------------------------------
            // Nombre descompuesto
            // ----------------------------------------------------------------
            'primer_nombre'    => ['sometimes', 'string', 'max:80'],
            'segundo_nombre'   => ['sometimes', 'nullable', 'string', 'max:80'],
            'primer_apellido'  => ['sometimes', 'string', 'max:80'],
            'segundo_apellido' => ['sometimes', 'nullable', 'string', 'max:80'],

            // ----------------------------------------------------------------
            // Credenciales
            // ----------------------------------------------------------------
            'email'    => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->user)],
            'documento' => ['sometimes', 'string', 'max:255', Rule::unique('users')->ignore($this->user)],
            'password' => ['sometimes', 'confirmed', Password::defaults()],

            // ----------------------------------------------------------------
            // Roles, permisos y relaciones
            // ----------------------------------------------------------------
            'roles'         => ['sometimes', 'array'],
            'roles.*'       => ['exists:roles,name'],
            'permissions'   => ['sometimes', 'array'],
            'permissions.*' => ['exists:permissions,name'],
            'cursos'        => ['sometimes', 'array'],
            'cursos.*'      => ['exists:cursos,id'],
            'sedes'         => ['sometimes', 'array'],
            'sedes.*'       => ['integer', 'exists:sedes,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'primer_nombre.max'        => 'El primer nombre no puede superar 80 caracteres.',
            'segundo_nombre.max'       => 'El segundo nombre no puede superar 80 caracteres.',
            'primer_apellido.max'      => 'El primer apellido no puede superar 80 caracteres.',
            'segundo_apellido.max'     => 'El segundo apellido no puede superar 80 caracteres.',
            'email.email'              => 'El correo electrónico debe ser una dirección válida.',
            'email.unique'             => 'El correo electrónico ya está registrado por otro usuario.',
            'documento.unique'         => 'El número de documento ya está registrado por otro usuario.',
            'password.confirmed'       => 'La confirmación de contraseña no coincide.',
        ];
    }

    public function attributes(): array
    {
        return [
            'primer_nombre'    => 'primer nombre',
            'segundo_nombre'   => 'segundo nombre',
            'primer_apellido'  => 'primer apellido',
            'segundo_apellido' => 'segundo apellido',
        ];
    }
}
