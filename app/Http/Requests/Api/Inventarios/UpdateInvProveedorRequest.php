<?php

namespace App\Http\Requests\Api\Inventarios;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para actualizar un proveedor de inventario.
 */
class UpdateInvProveedorRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('proveedor')?->id;

        return [
            'razon_social' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inv_proveedores', 'razon_social')->ignore($id)->whereNull('deleted_at'),
            ],
            'nit'          => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('inv_proveedores', 'nit')->ignore($id)->whereNull('deleted_at'),
            ],
            'contacto'     => ['nullable', 'string', 'max:150'],
            'telefono'     => ['nullable', 'string', 'max:50'],
            'email'        => ['nullable', 'email', 'max:150'],
            'direccion'    => ['nullable', 'string', 'max:255'],
            'notas'        => ['nullable', 'string', 'max:2000'],
            'status'       => self::getStatusValidationRule(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'razon_social.required' => 'La razón social es obligatoria.',
            'razon_social.max'      => 'La razón social no puede superar 255 caracteres.',
            'razon_social.unique'   => 'Ya existe un proveedor con esa razón social.',
            'nit.unique'            => 'Ya existe un proveedor con ese NIT.',
            'email.email'           => 'El correo electrónico no tiene un formato válido.',
        ], self::getStatusValidationMessages());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'razon_social' => 'razón social',
            'nit'          => 'NIT',
            'contacto'     => 'contacto',
            'telefono'     => 'teléfono',
            'email'        => 'correo electrónico',
            'direccion'    => 'dirección',
            'notas'        => 'notas',
            'status'       => 'estado',
        ];
    }
}
