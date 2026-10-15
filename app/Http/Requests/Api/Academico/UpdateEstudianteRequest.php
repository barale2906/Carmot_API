<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Actualización de los datos de identificación de un estudiante desde el asistente (B49).
 * No cambia contraseña, roles ni permisos.
 */
class UpdateEstudianteRequest extends FormRequest
{
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
        $id = $this->route('estudiante')?->id;

        return [
            'primer_nombre'    => ['sometimes', 'string', 'max:80'],
            'segundo_nombre'   => ['sometimes', 'nullable', 'string', 'max:80'],
            'primer_apellido'  => ['sometimes', 'string', 'max:80'],
            'segundo_apellido' => ['sometimes', 'nullable', 'string', 'max:80'],
            'email'            => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            'documento'        => ['sometimes', 'string', 'max:255', Rule::unique('users')->ignore($id)],
        ];
    }
}
