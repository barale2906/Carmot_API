<?php

namespace App\Http\Requests\Api\Inventarios;

use App\Models\Configuracion\CuentaBancaria;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Services\ArchivoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validación para registrar un abono a un pedido de inventario existente.
 */
class AbonarInvPedidoRequest extends FormRequest
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
            // Caja desde la que se factura; obligatoria solo si el cajero tiene turnos abiertos en varias sedes
            'turno_caja_id'                 => ['nullable', 'integer'],
            'monto_abono'                   => ['required', 'numeric', 'min:0.01'],
            'medios_pago'                          => ['required', 'array', 'min:1'],
            'medios_pago.*.medio_pago'             => ['required', 'string', 'max:50'],
            'medios_pago.*.valor'                  => ['required', 'numeric', 'min:0.01'],
            'medios_pago.*.referencia'             => ['nullable', 'string', 'max:100'],
            'medios_pago.*.banco_id'               => ['nullable', 'integer', 'exists:bancos,id'],
            // Cuenta del instituto que recibió la transferencia: activa y aplicable a la sede del
            // turno de caja con que se factura (el recibo queda en esa sede)
            'medios_pago.*.cuenta_bancaria_id'     => ['nullable', 'required_if:medios_pago.*.medio_pago,transferencia', 'integer', CuentaBancaria::reglaActivaDeSede(
                LdTurnoCaja::sedeParaFacturar((int) $this->user()?->id, $this->integer('turno_caja_id') ?: null)
            )],
            'medios_pago.*.tipo_tarjeta'           => ['nullable', 'string', 'max:60'],
            'medios_pago.*.numero_transaccion'     => ['nullable', 'string', 'max:100'],
            'comprobante'                          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:' . ArchivoService::MAX_KB],
            'sobrecargos'                          => ['nullable', 'array'],
            'sobrecargos.*.descuento_id'           => ['required', 'integer', 'exists:descuentos,id'],
            'sobrecargos.*.medio_pago_index'       => ['required', 'integer', 'min:0'],

            // Entrega inmediata al saldar el pedido
            'entrega_inmediata'                                    => ['nullable', 'boolean'],
            'items_a_entregar'                                     => ['nullable', 'array'],
            'items_a_entregar.*'                                   => ['integer', 'exists:inv_pedido_items,id'],

            'variantes_kit'                                        => ['nullable', 'array'],
            'variantes_kit.*.pedido_item_id'                       => ['required', 'integer'],
            'variantes_kit.*.componentes'                          => ['required', 'array'],
            'variantes_kit.*.componentes.*.kit_componente_id'      => ['required', 'integer', 'exists:inv_kit_componentes,id'],
            'variantes_kit.*.componentes.*.producto_entregado_id'  => ['nullable', 'integer', 'exists:inv_productos,id'],
        ];
    }

    /**
     * El abono genera un recibo: exige el turno de caja abierto del cajero (en
     * cualquier sede; con cajas abiertas en varias sedes, el elegido en `turno_caja_id`). Además, la suma de medios de pago debe igualar el monto abonado.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $pedido = $this->route('pedido');

            if ($pedido && ($error = LdTurnoCaja::errorParaFacturar((int) $this->user()->id, $this->integer('turno_caja_id') ?: null))) {
                $v->errors()->add('turno_caja_id', $error);
            }

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
            'medios_pago.*.cuenta_bancaria_id.required_if' => 'La cuenta a la que se hizo el pago es obligatoria para pagos por transferencia.',
            'medios_pago.*.cuenta_bancaria_id.in'          => 'La cuenta seleccionada no está activa o no aplica a la sede desde la que se factura.',
            'items_a_entregar.*.exists'        => 'Uno de los ítems a entregar no existe.',
        ];
    }
}
