<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validación para crear un nuevo pedido de inventario (primer abono o pago total).
 */
class StoreInvVentaRequest extends FormRequest
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
            'estudiante_id'                   => ['required', 'integer', 'exists:users,id'],
            'sede_id'                         => ['required', 'integer', 'exists:sedes,id'],
            'almacen_id'                      => ['required', 'integer', 'exists:inv_almacenes,id'],
            'items'                           => ['required', 'array', 'min:1'],
            'items.*.producto_id'             => [
                'required', 'integer',
                'exists:inv_productos,id',
            ],
            'items.*.cantidad'                => ['required', 'integer', 'min:1'],
            'items.*.descuento_id'            => ['nullable', 'integer', 'exists:descuentos,id'],
            'items.*.entregar'                => ['nullable', 'boolean'],
            'items.*.entrega_completa'        => ['nullable', 'boolean'],
            'monto_abono'                     => ['required', 'numeric', 'min:0.01'],
            'medios_pago'                          => ['required', 'array', 'min:1'],
            'medios_pago.*.medio_pago'             => ['required', 'string', 'max:50'],
            'medios_pago.*.valor'                  => ['required', 'numeric', 'min:0.01'],
            'medios_pago.*.referencia'             => ['nullable', 'string', 'max:100'],
            'medios_pago.*.banco_id'               => ['nullable', 'integer', 'exists:bancos,id'],
            'medios_pago.*.tipo_tarjeta'           => ['nullable', 'string', 'max:60'],
            'medios_pago.*.numero_transaccion'     => ['nullable', 'string', 'max:100'],
            'comprobante'                          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
            'sobrecargos'                          => ['nullable', 'array'],
            'sobrecargos.*.descuento_id'           => ['required', 'integer', 'exists:descuentos,id'],
            'sobrecargos.*.medio_pago_index'       => ['required', 'integer', 'min:0'],
            'observaciones'                        => ['nullable', 'string', 'max:1000'],

            // Entrega inmediata del inventario en el mismo movimiento del recibo
            'entrega_inmediata'                      => ['nullable', 'boolean'],

            // Variantes de componentes de kit (opcional).
            // Al crear la venta los ítems aún no existen, por eso se referencian por
            // item_index (posición dentro de `items`). pedido_item_id queda disponible
            // para flujos donde el ítem ya fue creado.
            'variantes_kit'                          => ['nullable', 'array'],
            'variantes_kit.*.item_index'             => ['nullable', 'integer', 'min:0'],
            'variantes_kit.*.pedido_item_id'         => ['nullable', 'integer'],
            'variantes_kit.*.componentes'            => ['required', 'array'],
            'variantes_kit.*.componentes.*.kit_componente_id'      => ['required', 'integer', 'exists:inv_kit_componentes,id'],
            'variantes_kit.*.componentes.*.producto_entregado_id'  => ['nullable', 'integer', 'exists:inv_productos,id'],
        ];
    }

    /**
     * Validación cruzada: la suma de medios_pago debe igualar monto_abono y cada
     * bloque de variantes_kit debe apuntar a un ítem existente del request.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $sumaMP = collect($this->medios_pago ?? [])
                ->sum(fn ($mp) => (float) ($mp['valor'] ?? 0));

            if (abs($sumaMP - (float) $this->monto_abono) > 0.01) {
                $v->errors()->add('medios_pago', 'La suma de los medios de pago debe ser igual al monto a abonar.');
            }

            // Cada bloque de variantes debe poder asociarse a un ítem concreto del pedido.
            $totalItems = count($this->items ?? []);

            foreach ($this->variantes_kit ?? [] as $indice => $variante) {
                $itemIndex    = $variante['item_index'] ?? null;
                $pedidoItemId = $variante['pedido_item_id'] ?? null;

                if ($itemIndex === null && $pedidoItemId === null) {
                    $v->errors()->add(
                        "variantes_kit.{$indice}.item_index",
                        'Debe indicar item_index (posición del producto) o pedido_item_id.'
                    );

                    continue;
                }

                if ($itemIndex !== null && $itemIndex >= $totalItems) {
                    $v->errors()->add(
                        "variantes_kit.{$indice}.item_index",
                        'El item_index no corresponde a ningún producto enviado.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estudiante_id.required'           => 'El estudiante es obligatorio.',
            'estudiante_id.exists'             => 'El estudiante seleccionado no existe.',
            'almacen_id.required'              => 'El almacén de despacho es obligatorio.',
            'almacen_id.exists'                => 'El almacén seleccionado no existe.',
            'items.required'                   => 'Debe incluir al menos un producto.',
            'items.min'                        => 'Debe incluir al menos un producto.',
            'items.*.producto_id.required'     => 'El producto es obligatorio en cada ítem.',
            'items.*.producto_id.exists'       => 'El producto seleccionado no existe.',
            'items.*.cantidad.required'        => 'La cantidad es obligatoria en cada ítem.',
            'items.*.cantidad.min'             => 'La cantidad debe ser al menos 1.',
            'monto_abono.required'             => 'El monto del abono es obligatorio.',
            'monto_abono.min'                  => 'El monto del abono debe ser mayor a 0.',
            'medios_pago.required'             => 'Debe indicar al menos un medio de pago.',
            'medios_pago.*.medio_pago.required' => 'El tipo de medio de pago es obligatorio.',
            'medios_pago.*.valor.required'     => 'El valor del medio de pago es obligatorio.',
            'medios_pago.*.valor.min'          => 'El valor del medio de pago debe ser mayor a 0.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'estudiante_id' => 'estudiante',
            'sede_id'       => 'sede',
            'almacen_id'    => 'almacén',
            'items'         => 'productos',
            'monto_abono'   => 'monto del abono',
            'medios_pago'   => 'medios de pago',
        ];
    }
}
