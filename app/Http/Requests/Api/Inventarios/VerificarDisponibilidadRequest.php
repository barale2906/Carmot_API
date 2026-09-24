<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para consultar la disponibilidad de stock antes de generar la venta.
 *
 * Es una consulta de solo lectura: nunca bloquea la venta, solo informa qué se
 * puede entregar en el acto y qué quedaría pendiente.
 */
class VerificarDisponibilidadRequest extends FormRequest
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
            'almacen_id'                                  => ['required', 'integer', 'exists:inv_almacenes,id'],
            'items'                                       => ['required', 'array', 'min:1'],
            'items.*.producto_id'                         => ['required', 'integer', 'exists:inv_productos,id'],
            'items.*.cantidad'                            => ['required', 'integer', 'min:1'],
            'items.*.entrega_completa'                    => ['nullable', 'boolean'],
            'items.*.variantes'                           => ['nullable', 'array'],
            'items.*.variantes.*.kit_componente_id'       => ['required', 'integer', 'exists:inv_kit_componentes,id'],
            'items.*.variantes.*.producto_entregado_id'   => ['nullable', 'integer', 'exists:inv_productos,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'almacen_id.required'                         => 'El almacén es obligatorio.',
            'almacen_id.exists'                           => 'El almacén seleccionado no existe.',
            'items.required'                              => 'Debe incluir al menos un producto.',
            'items.min'                                   => 'Debe incluir al menos un producto.',
            'items.*.producto_id.required'                => 'El producto es obligatorio en cada ítem.',
            'items.*.producto_id.exists'                  => 'El producto seleccionado no existe.',
            'items.*.cantidad.required'                   => 'La cantidad es obligatoria en cada ítem.',
            'items.*.cantidad.min'                        => 'La cantidad debe ser al menos 1.',
            'items.*.variantes.*.kit_componente_id.exists' => 'El componente seleccionado no existe.',
            'items.*.variantes.*.producto_entregado_id.exists' => 'La variante seleccionada no existe.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'almacen_id' => 'almacén',
            'items'      => 'productos',
        ];
    }
}
