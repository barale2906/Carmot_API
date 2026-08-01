<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para crear una orden de compra de inventario.
 */
class StoreInvOrdenCompraRequest extends FormRequest
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
            'proveedor_id'                          => ['required', 'integer', 'exists:inv_proveedores,id'],
            'almacen_id'                            => ['required', 'integer', 'exists:inv_almacenes,id'],
            'fecha_esperada'                        => ['nullable', 'date', 'after_or_equal:today'],
            'observaciones'                         => ['nullable', 'string', 'max:2000'],
            'items'                                 => ['required', 'array', 'min:1'],
            'items.*.producto_id'                   => ['required', 'integer', 'exists:inv_productos,id'],
            'items.*.cantidad_solicitada'            => ['required', 'integer', 'min:1'],
            'items.*.precio_costo_unitario'          => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proveedor_id.required'              => 'El proveedor es obligatorio.',
            'proveedor_id.exists'                => 'El proveedor seleccionado no existe.',
            'almacen_id.required'                => 'El almacén destino es obligatorio.',
            'almacen_id.exists'                  => 'El almacén seleccionado no existe.',
            'fecha_esperada.after_or_equal'      => 'La fecha esperada no puede ser anterior a hoy.',
            'items.required'                     => 'Debe incluir al menos un producto.',
            'items.min'                          => 'Debe incluir al menos un producto.',
            'items.*.producto_id.required'       => 'El producto es obligatorio en cada ítem.',
            'items.*.producto_id.exists'         => 'El producto seleccionado no existe.',
            'items.*.cantidad_solicitada.required' => 'La cantidad solicitada es obligatoria.',
            'items.*.cantidad_solicitada.min'    => 'La cantidad debe ser al menos 1.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'proveedor_id' => 'proveedor',
            'almacen_id'   => 'almacén',
            'items'        => 'ítems',
        ];
    }
}
