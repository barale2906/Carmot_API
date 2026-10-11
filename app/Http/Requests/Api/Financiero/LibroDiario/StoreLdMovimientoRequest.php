<?php

namespace App\Http\Requests\Api\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Models\Financiero\ReciboPago\ReciboPagoMedioPago;
use App\Services\Financiero\LibroDiario\LdMovimientoService;
use App\Services\Financiero\LibroDiario\LdTurnoCajaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida el registro de un movimiento manual del libro diario (egreso u otro ingreso).
 *
 * El cliente envía subtotal, impuestos aplicados (con su valor) y valor_total; el
 * total debe cuadrar con subtotal + impuestos - retenciones (tolerancia de $1).
 * Para registrar se exige un turno de caja abierto de quien registra.
 *
 * El medio de pago es efectivo por defecto; solo quien tiene `fin_ldMedioPagoOtros`
 * puede registrar otro medio (los encargados de caja operan únicamente en efectivo).
 */
class StoreLdMovimientoRequest extends FormRequest
{
    /**
     * La autorización se resuelve con el middleware de permisos.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sin medio de pago informado, el movimiento es en efectivo.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('medio_pago')) {
            $this->merge(['medio_pago' => 'efectivo']);
        }
    }

    /**
     * Reglas de validación.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $catalogoActivo = fn (string $tabla) => Rule::exists($tabla, 'id')->where('status', 1)->whereNull('deleted_at');

        return [
            'tipo_movimiento_id' => ['required', 'integer', $catalogoActivo('ld_tipos_movimiento')],
            'tercero_tipo_identificacion' => ['required', 'string', Rule::in(array_keys(LdMovimiento::TIPOS_IDENTIFICACION))],
            'tercero_identificacion' => ['required', 'string', 'max:30'],
            'tercero_nombre' => ['required', 'string', 'max:200'],
            'documento_tipo' => ['required', 'string', Rule::in(array_keys(LdMovimiento::TIPOS_DOCUMENTO))],
            'documento_numero' => ['nullable', 'string', 'max:60'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'impuestos' => ['nullable', 'array'],
            'impuestos.*.impuesto_id' => ['required', 'integer', 'distinct', $catalogoActivo('ld_impuestos')],
            'impuestos.*.porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
            'impuestos.*.valor' => ['required', 'numeric', 'min:0'],
            'valor_total' => ['required', 'numeric', 'min:0'],
            'medio_pago' => ['required', 'string', Rule::in(array_keys(ReciboPagoMedioPago::MEDIOS))],
            'banco_id' => ['nullable', 'required_if:medio_pago,transferencia,consignacion', 'integer', 'exists:bancos,id'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Valida el turno abierto, el medio de pago permitido y que el total cuadre con el cálculo.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->exigeTurnoAbierto() && ! app(LdTurnoCajaService::class)->turnoAbiertoDe($this->user()->id)) {
                $v->errors()->add('turno', 'Debe abrir un turno de caja antes de registrar movimientos.');
            }

            if ($this->medio_pago !== 'efectivo' && ! $this->user()->can('fin_ldMedioPagoOtros')) {
                $v->errors()->add('medio_pago', 'Solo puede registrar movimientos en efectivo.');
            }

            if ($v->errors()->hasAny(['subtotal', 'valor_total', 'impuestos', 'impuestos.*'])) {
                return;
            }

            $totales = app(LdMovimientoService::class)
                ->calcularTotales((float) $this->subtotal, $this->input('impuestos', []));

            if (abs($totales['valor_total'] - (float) $this->valor_total) > LdMovimientoService::TOLERANCIA) {
                $v->errors()->add(
                    'valor_total',
                    "El valor total no cuadra con el subtotal, impuestos y retenciones (calculado: {$totales['valor_total']})."
                );
            }
        });
    }

    /**
     * Solo la creación exige un turno abierto; la edición la controla el turno del registro.
     */
    protected function exigeTurnoAbierto(): bool
    {
        return true;
    }

    /**
     * Mensajes de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo_movimiento_id.required' => 'El tipo de movimiento es obligatorio.',
            'tipo_movimiento_id.exists' => 'El tipo de movimiento no existe o está inactivo.',
            'tercero_tipo_identificacion.required' => 'El tipo de identificación del tercero es obligatorio.',
            'tercero_tipo_identificacion.in' => 'El tipo de identificación no es válido.',
            'tercero_identificacion.required' => 'El número de identificación del tercero es obligatorio.',
            'tercero_nombre.required' => 'El nombre del tercero es obligatorio.',
            'documento_tipo.required' => 'El tipo de documento soporte es obligatorio.',
            'documento_tipo.in' => 'El tipo de documento no es válido.',
            'subtotal.required' => 'El subtotal es obligatorio.',
            'impuestos.*.impuesto_id.distinct' => 'Un impuesto no puede aplicarse dos veces.',
            'impuestos.*.impuesto_id.exists' => 'El impuesto no existe o está inactivo.',
            'valor_total.required' => 'El valor total es obligatorio.',
            'medio_pago.required' => 'El medio de pago es obligatorio.',
            'medio_pago.in' => 'El medio de pago no es válido.',
            'banco_id.required_if' => 'El banco es obligatorio para transferencias y consignaciones.',
        ];
    }

    /**
     * Nombres de los atributos.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo_movimiento_id' => 'tipo de movimiento',
            'tercero_tipo_identificacion' => 'tipo de identificación',
            'tercero_identificacion' => 'identificación del tercero',
            'tercero_nombre' => 'nombre del tercero',
            'documento_tipo' => 'tipo de documento',
            'documento_numero' => 'número de documento',
            'subtotal' => 'subtotal',
            'valor_total' => 'valor total',
            'medio_pago' => 'medio de pago',
            'banco_id' => 'banco',
            'observaciones' => 'observaciones',
        ];
    }
}
