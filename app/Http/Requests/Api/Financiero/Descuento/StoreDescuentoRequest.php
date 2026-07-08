<?php

namespace App\Http\Requests\Api\Financiero\Descuento;

use App\Models\Financiero\Descuento\Descuento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request StoreDescuentoRequest
 *
 * Valida los datos para crear un nuevo descuento en el sistema financiero.
 * Incluye validación de campos requeridos, tipos de descuento, aplicación,
 * condiciones de activación y relaciones many-to-many.
 *
 * @package App\Http\Requests\Api\Financiero\Descuento
 */
class StoreDescuentoRequest extends FormRequest
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
        $esSobrecargo = $this->input('tipo_movimiento') === Descuento::MOVIMIENTO_SOBRECARGO;

        return [
            'tipo_movimiento' => ['required', Rule::in([Descuento::MOVIMIENTO_DESCUENTO, Descuento::MOVIMIENTO_SOBRECARGO])],
            'nombre' => 'required|string|max:255',
            'codigo_descuento' => [
                'nullable',
                'string',
                'max:50',
                'unique:descuentos,codigo_descuento',
                Rule::requiredIf(fn () => $this->input('tipo_activacion') === Descuento::ACTIVACION_CODIGO_PROMOCIONAL),
            ],
            'descripcion' => 'nullable|string',
            'tipo' => [
                'required',
                Rule::in($esSobrecargo ? [Descuento::TIPO_PORCENTUAL] : [Descuento::TIPO_PORCENTUAL, Descuento::TIPO_VALOR_FIJO]),
            ],
            'valor' => [
                'required', 'numeric', 'min:0',
                // Los porcentuales (y todos los sobrecargos) no pueden superar 100
                ...($esSobrecargo || $this->input('tipo') === Descuento::TIPO_PORCENTUAL
                    ? ['max:100']
                    : []),
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'aplicacion' => [
                'required',
                Rule::in($esSobrecargo
                    ? [Descuento::APLICACION_VALOR_RECIBO, Descuento::APLICACION_SALDO_CARTERA]
                    : [Descuento::APLICACION_VALOR_TOTAL, Descuento::APLICACION_MATRICULA, Descuento::APLICACION_CUOTA]
                ),
            ],
            'tipo_activacion' => [
                'required',
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
            // medios_pago: requerido para sobrecargos con tipo_activacion=medio_pago
            'medios_pago' => [
                Rule::requiredIf(fn () => $this->input('tipo_activacion') === Descuento::ACTIVACION_MEDIO_PAGO),
                'nullable', 'array', 'min:1',
            ],
            'medios_pago.*' => [
                'string',
                Rule::in(['efectivo', 'transferencia', 'tarjeta_debito', 'tarjeta_credito', 'cheque', 'consignacion']),
            ],
            // marca_tarjeta: opcional, libre (valores configurados por el admin)
            'marca_tarjeta' => 'nullable|array',
            'marca_tarjeta.*' => 'string|max:60',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'status' => Descuento::getStatusValidationRule(),
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
     * Validaciones cruzadas que la DB ya garantiza como CHECKs, pero se exponen
     * aquí para devolver mensajes claros al cliente.
     *
     * @param \Illuminate\Validation\Validator $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $movimiento = $this->input('tipo_movimiento');
            $activacion = $this->input('tipo_activacion');

            if ($movimiento === Descuento::MOVIMIENTO_SOBRECARGO) {
                if ($this->boolean('permite_acumulacion')) {
                    $v->errors()->add('permite_acumulacion', 'Los sobrecargos no pueden acumularse.');
                }
                if ($activacion === Descuento::ACTIVACION_MORA_AUTOMATICA
                    && $this->input('aplicacion') !== Descuento::APLICACION_SALDO_CARTERA) {
                    $v->errors()->add('aplicacion', 'La mora automática debe aplicarse sobre saldo_cartera.');
                }
                if ($activacion === Descuento::ACTIVACION_MEDIO_PAGO
                    && $this->input('aplicacion') !== Descuento::APLICACION_VALOR_RECIBO) {
                    $v->errors()->add('aplicacion', 'El sobrecargo por medio de pago debe aplicarse sobre valor_recibo.');
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
            'tipo_movimiento.required' => 'El tipo de movimiento (descuento o sobrecargo) es obligatorio.',
            'tipo_movimiento.in'       => 'El tipo de movimiento debe ser: descuento o sobrecargo.',
            'nombre.required'          => 'El nombre es obligatorio.',
            'nombre.max'               => 'El nombre no puede exceder 255 caracteres.',
            'codigo_descuento.unique'  => 'El código de descuento ya está en uso.',
            'codigo_descuento.required' => 'El código es obligatorio cuando el tipo de activación es código promocional.',
            'tipo.required'            => 'El tipo de cálculo es obligatorio.',
            'tipo.in'                  => 'Los sobrecargos solo admiten tipo porcentual.',
            'valor.required'           => 'El valor es obligatorio.',
            'valor.numeric'            => 'El valor debe ser numérico.',
            'valor.min'                => 'El valor no puede ser negativo.',
            'valor.max'                => 'El valor no puede superar 100.',
            'valor.regex'              => 'El valor admite máximo 2 decimales.',
            'aplicacion.required'      => 'La aplicación es obligatoria.',
            'aplicacion.in'            => 'Valor de aplicación no válido para este tipo de movimiento.',
            'tipo_activacion.required' => 'El tipo de activación es obligatorio.',
            'tipo_activacion.in'       => 'Tipo de activación no válido para este tipo de movimiento.',
            'dias_anticipacion.required' => 'Los días de anticipación son obligatorios para pago anticipado.',
            'dias_anticipacion.min'    => 'Los días de anticipación deben ser al menos 1.',
            'medios_pago.required'     => 'Los medios de pago son obligatorios para sobrecargos por medio de pago.',
            'medios_pago.*.in'         => 'Medio de pago no válido.',
            'marca_tarjeta.*.max'      => 'El nombre de la marca no puede exceder 60 caracteres.',
            'fecha_inicio.required'    => 'La fecha de inicio es obligatoria.',
            'fecha_fin.required'       => 'La fecha de fin es obligatoria.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'status.in'                => Descuento::getStatusValidationMessages()['status.in'] ?? 'Estado no válido.',
        ];
    }
}

