<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validación para registrar un abono a un pedido de inventario existente.
 */
class AbonarInvPedidoRequest extends FormRequest
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
            'monto_abono'                   => ['required', 'numeric', 'min:0.01'],
            'medios_pago'                   => ['required', 'array', 'min:1'],
            'medios_pago.*.medio_pago'      => ['required', 'string', 'max:50'],
            'medios_pago.*.valor'           => ['required', 'numeric', 'min:0.01'],
            'medios_pago.*.referencia'      => ['nullable', 'string', 'max:100'],
            'medios_pago.*.banco_id'        => ['nullable', 'integer', 'exists:bancos,id'],

            'variantes_kit'                                        => ['nullable', 'array'],
            'variantes_kit.*.pedido_item_id'                       => ['required', 'integer'],
            'variantes_kit.*.componentes'                          => ['required', 'array'],
            'variantes_kit.*.componentes.*.kit_componente_id'      => ['required', 'integer', 'exists:inv_kit_componentes,id'],
            'variantes_kit.*.componentes.*.producto_entregado_id'  => ['nullable', 'integer', 'exists:inv_productos,id'],
        ];
    }

    /**
     * Suma de medios de pago debe igualar el monto abonado.
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
            'monto_abono.required'             => 'El monto del abono es obligatorio.',
            'monto_abono.min'                  => 'El monto del abono debe ser mayor a 0.',
            'medios_pago.required'             => 'Debe indicar al menos un medio de pago.',
            'medios_pago.*.medio_pago.required' => 'El tipo de medio de pago es obligatorio.',
            'medios_pago.*.valor.required'     => 'El valor del medio de pago es obligatorio.',
        ];
    }
}
