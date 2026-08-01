<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para actualizar una orden de compra en estado borrador.
 */
class UpdateInvOrdenCompraRequest extends FormRequest
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
            'proveedor_id'                       => ['sometimes', 'integer', 'exists:inv_proveedores,id'],
            'almacen_id'                         => ['sometimes', 'integer', 'exists:inv_almacenes,id'],
            'fecha_esperada'                     => ['nullable', 'date', 'after_or_equal:today'],
            'observaciones'                      => ['nullable', 'string', 'max:2000'],
            'items'                              => ['sometimes', 'array', 'min:1'],
            'items.*.producto_id'                => ['required_with:items', 'integer', 'exists:inv_productos,id'],
            'items.*.cantidad_solicitada'         => ['required_with:items', 'integer', 'min:1'],
            'items.*.precio_costo_unitario'       => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proveedor_id.exists'                  => 'El proveedor seleccionado no existe.',
            'almacen_id.exists'                    => 'El almacén seleccionado no existe.',
            'fecha_esperada.after_or_equal'        => 'La fecha esperada no puede ser anterior a hoy.',
            'items.min'                            => 'Debe incluir al menos un producto.',
            'items.*.producto_id.required_with'    => 'El producto es obligatorio en cada ítem.',
            'items.*.producto_id.exists'           => 'El producto seleccionado no existe.',
            'items.*.cantidad_solicitada.required_with' => 'La cantidad solicitada es obligatoria.',
            'items.*.cantidad_solicitada.min'      => 'La cantidad debe ser al menos 1.',
        ];
    }
}
