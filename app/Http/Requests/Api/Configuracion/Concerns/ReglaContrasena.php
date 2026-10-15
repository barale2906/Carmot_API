<?php

namespace App\Http\Requests\Api\Configuracion\Concerns;

use App\Models\User;
use Illuminate\Validation\Rules\Password;

/**
 * Regla de contraseña según el tipo de usuario (F19):
 *  - Personal: Password::defaults() (8 caracteres, mayúscula, número y símbolo; ver AppServiceProvider).
 *  - Solo alumno: la clave inicial es su número de documento (asistente de matrícula), así que basta
 *    con 6 caracteres sin espacios; antes se aceptaba un nombre con espacios como "Héctor Cuervo".
 */
trait ReglaContrasena
{
    /**
     * @param array<int, string> $roles Roles con los que quedará el usuario
     * @return array<int, mixed>
     */
    protected function reglaContrasena(array $roles): array
    {
        $soloAlumno = $roles !== [] && collect($roles)->every(fn ($rol) => $rol === User::ROL_ALUMNO);

        return $soloAlumno
            ? [Password::min(6), 'regex:/^\S+$/']
            : [Password::defaults()];
    }
}
