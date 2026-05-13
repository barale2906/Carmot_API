<?php

namespace App\Http\Requests\Api\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateRolRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Resuelve el ID del rol desde el parámetro de ruta,
     * ya sea el modelo bound o el valor raw (string/int).
     */
    private function rolId(): int
    {
        $rol = $this->route('rol');

        return $rol instanceof \Illuminate\Database\Eloquent\Model
            ? (int) $rol->getKey()
            : (int) $rol;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rolId = $this->rolId();

        return [
            'name'          => [
                'sometimes',
                'string',
                'max:125',
                function (string $attribute, mixed $value, \Closure $fail) use ($rolId): void {
                    $exists = DB::table('roles')
                        ->where('name', $value)
                        ->where('id', '<>', $rolId)
                        ->exists();

                    if ($exists) {
                        $fail('Ya existe un rol con ese nombre.');
                    }
                },
            ],
            'status'        => ['sometimes', 'boolean'],
            'permissions'   => ['sometimes', 'array'],
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
            'name'          => 'nombre del rol',
            'status'        => 'estado',
            'permissions'   => 'permisos',
            'permissions.*' => 'permiso',
        ];
    }
}
