<?php

namespace App\Http\Requests\Api\Inventarios;

use App\Models\Inventarios\InvOrdenCompraItem;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para registrar la recepción (total o parcial) de una orden de compra.
 */
class RecibirInvOrdenCompraRequest extends FormRequest
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

    /**
     * Cada ítem debe ser de esta orden y no se puede recibir más de lo pendiente: antes se
     * recortaba en silencio y la orden se cerraba sin avisar (B34).
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $orden = $this->route('ordenCompra');
            $ordenId = is_object($orden) ? $orden->getKey() : (int) $orden;
            $items = InvOrdenCompraItem::whereKey(collect($this->input('items'))->pluck('orden_item_id'))->get()->keyBy('id');

            foreach ($this->input('items') as $i => $fila) {
                $item = $items->get($fila['orden_item_id']);

                if (! $item || (int) $item->orden_id !== $ordenId) {
                    $validator->errors()->add("items.{$i}.orden_item_id", 'El ítem no pertenece a esta orden de compra.');
                } elseif ((int) $fila['cantidad_recibida'] > $item->cantidadPendiente()) {
                    $validator->errors()->add(
                        "items.{$i}.cantidad_recibida",
                        "La cantidad recibida ({$fila['cantidad_recibida']}) excede lo pendiente ({$item->cantidadPendiente()})."
                    );
                }
            }
        });
    }
}
