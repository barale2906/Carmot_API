<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para actualizar el precio de un producto de inventario.
 */
class UpdateInvPrecioProductoRequest extends FormRequest
{
    /**
     * La autorización se resuelve con el middleware de permisos de la ruta.
     *
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
            'precio'        => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'precio.required' => 'El precio es obligatorio.',
            'precio.min'      => 'El precio no puede ser negativo.',
        ];
    }
}
