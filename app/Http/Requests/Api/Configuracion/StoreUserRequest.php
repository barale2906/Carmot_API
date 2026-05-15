<?php

namespace App\Http\Requests\Api\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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
            'primer_nombre'    => ['required', 'string', 'max:80'],
            'segundo_nombre'   => ['nullable', 'string', 'max:80'],
            'primer_apellido'  => ['required', 'string', 'max:80'],
            'segundo_apellido' => ['nullable', 'string', 'max:80'],

            // ----------------------------------------------------------------
            // Credenciales
            // ----------------------------------------------------------------
            'email'     => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'documento' => ['required', 'string', 'max:255', 'unique:users'],
            'password'  => ['required', 'confirmed', Password::defaults()],

            // ----------------------------------------------------------------
            // Roles, permisos y relaciones
            // ----------------------------------------------------------------
            'roles'        => ['sometimes', 'array'],
            'roles.*'      => ['exists:roles,name'],
            'permissions'  => ['sometimes', 'array'],
            'permissions.*' => ['exists:permissions,name'],
            'cursos'       => ['sometimes', 'array'],
            'cursos.*'     => ['exists:cursos,id'],
            'sedes'        => ['sometimes', 'array'],
            'sedes.*'      => ['integer', 'exists:sedes,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'primer_nombre.required'   => 'El primer nombre es obligatorio.',
            'primer_nombre.max'        => 'El primer nombre no puede superar 80 caracteres.',
            'segundo_nombre.max'       => 'El segundo nombre no puede superar 80 caracteres.',
            'primer_apellido.required' => 'El primer apellido es obligatorio.',
            'primer_apellido.max'      => 'El primer apellido no puede superar 80 caracteres.',
            'segundo_apellido.max'     => 'El segundo apellido no puede superar 80 caracteres.',
            'email.required'           => 'El correo electrónico es obligatorio.',
            'email.email'              => 'El correo electrónico debe ser una dirección válida.',
            'email.unique'             => 'El correo electrónico ya está registrado.',
            'documento.required'       => 'El número de documento es obligatorio.',
            'documento.unique'         => 'El número de documento ya está registrado.',
            'password.required'        => 'La contraseña es obligatoria.',
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
