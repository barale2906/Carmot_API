<?php

namespace App\Http\Requests\Api\Configuracion;

use App\Http\Requests\Api\Configuracion\Concerns\ReglaContrasena;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cambio de la contraseña propia desde el perfil (F03). Exige la contraseña actual y
 * aplica la misma política que la gestión de usuarios según los roles del usuario.
 */
class CambiarContrasenaRequest extends FormRequest
{
    use ReglaContrasena;

    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contrasena_actual' => ['required', 'string', 'current_password:sanctum'],
            'password'          => [
                'required', 'confirmed', 'different:contrasena_actual',
                ...$this->reglaContrasena($this->user()->getRoleNames()->all()),
            ],
        ];
    }

    /**
     * Mensajes de validación en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contrasena_actual.required'         => 'Ingrese su contraseña actual.',
            'contrasena_actual.current_password' => 'La contraseña actual no es correcta.',
            'password.required'                  => 'Ingrese la nueva contraseña.',
            'password.confirmed'                 => 'La confirmación de la contraseña no coincide.',
            'password.different'                 => 'La nueva contraseña debe ser distinta de la actual.',
            'password.regex'                     => 'La contraseña no puede tener espacios.',
        ];
    }
}
