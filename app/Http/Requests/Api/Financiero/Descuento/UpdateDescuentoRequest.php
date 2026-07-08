<?php

namespace App\Http\Requests\Api\Financiero\Descuento;

use App\Models\Financiero\Descuento\Descuento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request UpdateDescuentoRequest
 *
 * Valida los datos para actualizar un descuento existente en el sistema financiero.
 * Similar a StoreDescuentoRequest pero con reglas `sometimes` para permitir actualizaciones parciales.
 *
 * @package App\Http\Requests\Api\Financiero\Descuento
 */
class UpdateDescuentoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     * La autorización se maneja mediante middleware y permisos.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $descuentoId  = $this->route('descuento')?->id ?? $this->route('id');
        // tipo_movimiento puede venir en el request o se toma del modelo existente
        $movimiento   = $this->input('tipo_movimiento', $this->route('descuento')?->tipo_movimiento);
        $esSobrecargo = $movimiento === Descuento::MOVIMIENTO_SOBRECARGO;

        return [
            'tipo_movimiento' => ['sometimes', Rule::in([Descuento::MOVIMIENTO_DESCUENTO, Descuento::MOVIMIENTO_SOBRECARGO])],
            'nombre'          => 'sometimes|string|max:255',
            'codigo_descuento' => [
                'nullable', 'string', 'max:50',
                Rule::unique('descuentos', 'codigo_descuento')->ignore($descuentoId),
                Rule::requiredIf(fn () => $this->input('tipo_activacion') === Descuento::ACTIVACION_CODIGO_PROMOCIONAL),
            ],
            'descripcion' => 'nullable|string',
            'tipo' => [
                'sometimes',
                Rule::in($esSobrecargo ? [Descuento::TIPO_PORCENTUAL] : [Descuento::TIPO_PORCENTUAL, Descuento::TIPO_VALOR_FIJO]),
            ],
            'valor' => [
                'sometimes', 'numeric', 'min:0',
                ...($esSobrecargo || $this->input('tipo') === Descuento::TIPO_PORCENTUAL
                    ? ['max:100']
                    : []),
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'aplicacion' => [
                'sometimes',
                Rule::in($esSobrecargo
                    ? [Descuento::APLICACION_VALOR_RECIBO, Descuento::APLICACION_SALDO_CARTERA]
                    : [Descuento::APLICACION_VALOR_TOTAL, Descuento::APLICACION_MATRICULA, Descuento::APLICACION_CUOTA]
                ),
            ],
            'tipo_activacion' => [
                'sometimes',
                Rule::in($esSobrecargo
                    ? [Descuento::ACTIVACION_MEDIO_PAGO, Descuento::ACTIVACION_MORA_AUTOMATICA]
                    : [Descuento::ACTIVACION_PAGO_ANTICIPADO, Descuento::ACTIVACION_PROMOCION_MATRICULA, Descuento::ACTIVACION_CODIGO_PROMOCIONAL]
                ),
            ],
            'dias_anticipacion' => [
                Rule::requiredIf($this->input('tipo_activacion') === Descuento::ACTIVACION_PAGO_ANTICIPADO),
                'nullable', 'integer', 'min:1',
            ],
            'permite_acumulacion' => 'sometimes|boolean',
            'medios_pago'   => [
                Rule::requiredIf(fn () => $this->input('tipo_activacion') === Descuento::ACTIVACION_MEDIO_PAGO),
                'nullable', 'array', 'min:1',
            ],
            'medios_pago.*' => [
                'string',
                Rule::in(['efectivo', 'transferencia', 'tarjeta_debito', 'tarjeta_credito', 'cheque', 'consignacion']),
            ],
            'marca_tarjeta'   => 'nullable|array',
            'marca_tarjeta.*' => 'string|max:60',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => [
                'sometimes', 'date',
                Rule::requiredIf($this->has('fecha_inicio')),
                function ($attribute, $value, $fail) {
                    if ($this->has('fecha_inicio') && $value < $this->input('fecha_inicio')) {
                        $fail('La fecha de fin debe ser igual o posterior a la fecha de inicio.');
                    }
                },
            ],
            'status'           => Descuento::getStatusValidationRule(),
            'listas_precios'   => 'nullable|array',
            'listas_precios.*' => 'exists:lp_listas_precios,id',
            'productos'        => 'nullable|array',
            'productos.*'      => 'exists:lp_productos,id',
            'sedes'            => 'nullable|array',
            'sedes.*'          => 'exists:sedes,id',
            'poblaciones'      => 'nullable|array',
            'poblaciones.*'    => 'exists:poblacions,id',
        ];
    }

    /**
     * Validaciones cruzadas para sobrecargos.
     *
     * @param \Illuminate\Validation\Validator $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $movimiento = $this->input('tipo_movimiento', $this->route('descuento')?->tipo_movimiento);
            $activacion = $this->input('tipo_activacion');

            if ($movimiento === Descuento::MOVIMIENTO_SOBRECARGO) {
                if ($this->has('permite_acumulacion') && $this->boolean('permite_acumulacion')) {
                    $v->errors()->add('permite_acumulacion', 'Los sobrecargos no pueden acumularse.');
                }
                if ($activacion === Descuento::ACTIVACION_MORA_AUTOMATICA
                    && $this->has('aplicacion')
                    && $this->input('aplicacion') !== Descuento::APLICACION_SALDO_CARTERA) {
                    $v->errors()->add('aplicacion', 'La mora automática debe aplicarse sobre saldo_cartera.');
                }
            }
        });
    }

    /**
     * Obtiene los mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo_movimiento.in'       => 'El tipo de movimiento debe ser: descuento o sobrecargo.',
            'codigo_descuento.unique'  => 'El código de descuento ya está en uso.',
            'tipo.in'                  => 'Los sobrecargos solo admiten tipo porcentual.',
            'valor.max'                => 'El valor no puede superar 100.',
            'valor.regex'              => 'El valor admite máximo 2 decimales.',
            'aplicacion.in'            => 'Valor de aplicación no válido para este tipo de movimiento.',
            'tipo_activacion.in'       => 'Tipo de activación no válido para este tipo de movimiento.',
            'dias_anticipacion.required' => 'Los días de anticipación son obligatorios para pago anticipado.',
            'medios_pago.required'     => 'Los medios de pago son obligatorios para sobrecargos por medio de pago.',
            'medios_pago.*.in'         => 'Medio de pago no válido.',
            'fecha_fin.required'       => 'La fecha de fin es obligatoria cuando se proporciona fecha de inicio.',
            'status.in'                => Descuento::getStatusValidationMessages()['status.in'] ?? 'Estado no válido.',
        ];
    }
}

