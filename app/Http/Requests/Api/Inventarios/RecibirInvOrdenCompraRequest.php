<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para registrar la recepción (total o parcial) de una orden de compra.
 */
class RecibirInvOrdenCompraRequest extends FormRequest
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
            'items'                             => ['required', 'array', 'min:1'],
            'items.*.orden_item_id'             => ['required', 'integer', 'exists:inv_orden_compra_items,id'],
            'items.*.cantidad_recibida'         => ['required', 'integer', 'min:1'],
            'items.*.precio_costo_unitario'     => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required'                          => 'Debe indicar al menos un ítem a recibir.',
            'items.*.orden_item_id.required'          => 'El ID del ítem de la orden es obligatorio.',
            'items.*.orden_item_id.exists'            => 'El ítem seleccionado no existe en esta orden.',
            'items.*.cantidad_recibida.required'      => 'La cantidad recibida es obligatoria.',
            'items.*.cantidad_recibida.min'           => 'La cantidad recibida debe ser al menos 1.',
        ];
    }
}
