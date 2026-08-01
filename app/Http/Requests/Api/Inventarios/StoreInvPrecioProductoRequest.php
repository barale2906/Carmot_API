<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para crear un precio de producto en una lista de precios de inventario.
 */
class StoreInvPrecioProductoRequest extends FormRequest
{
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
            'lista_precio_id' => [
                'required',
                'integer',
                'exists:lp_listas_precios,id',
                Rule::unique('inv_precios_producto', 'lista_precio_id')
                    ->where('producto_id', $this->producto_id)
                    ->whereNull('deleted_at'),
            ],
            'producto_id'     => [
                'required',
                'integer',
                Rule::exists('inv_productos', 'id')->where('tipo', '!=', 'grupo'),
            ],
            'precio'          => ['required', 'numeric', 'min:0'],
            'observaciones'   => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lista_precio_id.required' => 'La lista de precios es obligatoria.',
            'lista_precio_id.exists'   => 'La lista de precios seleccionada no existe.',
            'lista_precio_id.unique'   => 'Ya existe un precio para ese producto en esa lista.',
            'producto_id.required'     => 'El producto es obligatorio.',
            'producto_id.exists'       => 'El producto no existe o es de tipo grupo (sin precio).',
            'precio.required'          => 'El precio es obligatorio.',
            'precio.min'               => 'El precio no puede ser negativo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lista_precio_id' => 'lista de precios',
            'producto_id'     => 'producto',
            'precio'          => 'precio',
            'observaciones'   => 'observaciones',
        ];
    }
}
