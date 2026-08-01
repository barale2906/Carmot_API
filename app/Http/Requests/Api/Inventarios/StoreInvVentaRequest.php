<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validación para crear un nuevo pedido de inventario (primer abono o pago total).
 */
class StoreInvVentaRequest extends FormRequest
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
            'estudiante_id'                   => ['required', 'integer', 'exists:users,id'],
            'sede_id'                         => ['required', 'integer', 'exists:sedes,id'],
            'almacen_id'                      => ['required', 'integer', 'exists:inv_almacenes,id'],
            'items'                           => ['required', 'array', 'min:1'],
            'items.*.producto_id'             => [
                'required', 'integer',
                'exists:inv_productos,id',
            ],
            'items.*.cantidad'                => ['required', 'integer', 'min:1'],
            'monto_abono'                     => ['required', 'numeric', 'min:0.01'],
            'medios_pago'                     => ['required', 'array', 'min:1'],
            'medios_pago.*.medio_pago'        => ['required', 'string', 'max:50'],
            'medios_pago.*.valor'             => ['required', 'numeric', 'min:0.01'],
            'medios_pago.*.referencia'        => ['nullable', 'string', 'max:100'],
            'medios_pago.*.banco_id'          => ['nullable', 'integer', 'exists:bancos,id'],
            'observaciones'                   => ['nullable', 'string', 'max:1000'],

            // Variantes de componentes de kit (opcional)
            'variantes_kit'                          => ['nullable', 'array'],
            'variantes_kit.*.pedido_item_id'         => ['required', 'integer'],
            'variantes_kit.*.componentes'            => ['required', 'array'],
            'variantes_kit.*.componentes.*.kit_componente_id'      => ['required', 'integer', 'exists:inv_kit_componentes,id'],
            'variantes_kit.*.componentes.*.producto_entregado_id'  => ['nullable', 'integer', 'exists:inv_productos,id'],
        ];
    }

    /**
     * Validación cruzada: suma de medios_pago debe igualar monto_abono.
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
