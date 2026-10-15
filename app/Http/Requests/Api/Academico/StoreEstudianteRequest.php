<?php

namespace App\Http\Requests\Api\Academico;

use App\Http\Requests\Api\Configuracion\Concerns\ReglaContrasena;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Registro de un estudiante desde el asistente de matrícula o de venta (B49). Siempre
 * queda con el rol alumno; la contraseña inicial suele ser su documento.
 */
class StoreEstudianteRequest extends FormRequest
{
    use ReglaContrasena;

    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'primer_nombre'    => ['required', 'string', 'max:80'],
            'segundo_nombre'   => ['nullable', 'string', 'max:80'],
            'primer_apellido'  => ['required', 'string', 'max:80'],
            'segundo_apellido' => ['nullable', 'string', 'max:80'],
            'email'            => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'documento'        => ['required', 'string', 'max:255', 'unique:users'],
            'password'         => ['required', 'confirmed', ...$this->reglaContrasena([User::ROL_ALUMNO])],
            'sedes'            => ['sometimes', 'array'],
            'sedes.*'          => ['integer', 'exists:sedes,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique'       => 'Ya existe un usuario con este correo.',
            'documento.unique'   => 'Ya existe un usuario con este documento.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'password.regex'     => 'La contraseña no puede tener espacios.',
        ];
    }
}
