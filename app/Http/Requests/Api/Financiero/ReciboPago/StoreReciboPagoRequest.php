<?php

namespace App\Http\Requests\Api\Financiero\ReciboPago;

use App\Models\Financiero\ReciboPago\ReciboPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request StoreReciboPagoRequest
 *
 * Valida los datos para crear un nuevo recibo de pago en el sistema financiero.
 * Incluye validación de campos requeridos, relaciones y cálculos.
 *
 * @package App\Http\Requests\Api\Financiero\ReciboPago
 */
class StoreReciboPagoRequest extends FormRequest
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
        return [
            'sede_id' => 'required|exists:sedes,id',
            'estudiante_id' => 'nullable|exists:users,id',
            'cajero_id' => 'required|exists:users,id',
            'matricula_id' => 'nullable|exists:matriculas,id',
            'origen' => ['required', 'integer', Rule::in([ReciboPago::ORIGEN_INVENTARIOS, ReciboPago::ORIGEN_ACADEMICO])],
            'fecha_recibo' => 'required|date',
            'fecha_transaccion' => 'required|date',
            'valor_total' => 'required|numeric|min:0',
            'descuento_total' => 'nullable|numeric|min:0',
            'banco' => 'nullable|string|max:100',
            'conceptos_pago' => 'required|array|min:1',
            'conceptos_pago.*.concepto_pago_id' => 'required|exists:conceptos_pago,id',
            'conceptos_pago.*.valor' => 'required|numeric|min:0',
            'conceptos_pago.*.tipo' => 'required|integer',
            'conceptos_pago.*.producto' => 'nullable|string|max:255',
            'conceptos_pago.*.cantidad' => 'required|integer|min:1',
            'conceptos_pago.*.unitario' => 'required|numeric|min:0',
            'conceptos_pago.*.subtotal' => 'required|numeric|min:0',
            'conceptos_pago.*.id_relacional' => 'nullable|integer',
            'conceptos_pago.*.observaciones' => 'nullable|string',
            'listas_precio' => 'nullable|array',
            'listas_precio.*' => 'exists:lp_listas_precios,id',
            'productos' => 'nullable|array',
            'productos.*.producto_id' => 'required|exists:lp_productos,id',
            'productos.*.cantidad' => 'required|integer|min:1',
            'productos.*.precio_unitario' => 'required|numeric|min:0',
            'productos.*.subtotal' => 'required|numeric|min:0',
            'descuentos' => 'nullable|array',
            'descuentos.*.descuento_id' => 'required|exists:descuentos,id',
            'descuentos.*.valor_descuento' => 'required|numeric|min:0',
            'descuentos.*.valor_original' => 'required|numeric|min:0',
            'descuentos.*.valor_final' => 'required|numeric|min:0',
            'medios_pago' => 'required|array|min:1',
            'medios_pago.*.medio_pago' => 'required|string|max:50',
            'medios_pago.*.valor' => 'required|numeric|min:0',
            'medios_pago.*.referencia' => 'nullable|string|max:100',
            'medios_pago.*.banco' => 'nullable|string|max:100',
        ];
    }

    /**
     * Configura el validador después de las reglas.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que descuento_total <= valor_total
            if ($this->has('descuento_total') && $this->has('valor_total')) {
                if ($this->descuento_total > $this->valor_total) {
                    $validator->errors()->add('descuento_total', 'El descuento total no puede ser mayor al valor total.');
                }
            }

            // Validar que la suma de medios de pago sea igual al valor_total
            if ($this->has('medios_pago') && $this->has('valor_total')) {
                $sumaMediosPago = collect($this->medios_pago)->sum('valor');
                $tolerancia = 0.01; // Tolerancia para decimales

                if (abs($sumaMediosPago - $this->valor_total) > $tolerancia) {
                    $validator->errors()->add('medios_pago', 'La suma de los medios de pago debe ser igual al valor total del recibo.');
                }
            }

            // Validar que subtotal = cantidad * unitario en conceptos_pago
            if ($this->has('conceptos_pago')) {
                foreach ($this->conceptos_pago as $index => $concepto) {
                    if (isset($concepto['cantidad']) && isset($concepto['unitario']) && isset($concepto['subtotal'])) {
                        $subtotalCalculado = $concepto['cantidad'] * $concepto['unitario'];
                        $tolerancia = 0.01;

                        if (abs($subtotalCalculado - $concepto['subtotal']) > $tolerancia) {
                            $validator->errors()->add("conceptos_pago.{$index}.subtotal", 'El subtotal debe ser igual a cantidad × unitario.');
                        }
                    }
                }
            }

            // Validar que subtotal = cantidad * precio_unitario en productos
            if ($this->has('productos')) {
                foreach ($this->productos as $index => $producto) {
                    if (isset($producto['cantidad']) && isset($producto['precio_unitario']) && isset($producto['subtotal'])) {
                        $subtotalCalculado = $producto['cantidad'] * $producto['precio_unitario'];
                        $tolerancia = 0.01;

                        if (abs($subtotalCalculado - $producto['subtotal']) > $tolerancia) {
                            $validator->errors()->add("productos.{$index}.subtotal", 'El subtotal debe ser igual a cantidad × precio unitario.');
                        }
                    }
                }
            }

            // Validar que valor_final = valor_original - valor_descuento en descuentos
            if ($this->has('descuentos')) {
                foreach ($this->descuentos as $index => $descuento) {
                    if (isset($descuento['valor_original']) && isset($descuento['valor_descuento']) && isset($descuento['valor_final'])) {
                        $valorFinalCalculado = $descuento['valor_original'] - $descuento['valor_descuento'];
                        $tolerancia = 0.01;

                        if (abs($valorFinalCalculado - $descuento['valor_final']) > $tolerancia) {
                            $validator->errors()->add("descuentos.{$index}.valor_final", 'El valor final debe ser igual a valor original - valor descuento.');
                        }
                    }
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
            'sede_id.required' => 'La sede es obligatoria.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
            'estudiante_id.exists' => 'El estudiante seleccionado no existe.',
            'cajero_id.required' => 'El cajero es obligatorio.',
            'cajero_id.exists' => 'El cajero seleccionado no existe.',
            'matricula_id.exists' => 'La matrícula seleccionada no existe.',
            'origen.required' => 'El origen es obligatorio.',
            'origen.integer' => 'El origen debe ser un número entero.',
            'origen.in' => 'El origen debe ser 0 (Inventarios) o 1 (Académico).',
            'fecha_recibo.required' => 'La fecha del recibo es obligatoria.',
            'fecha_recibo.date' => 'La fecha del recibo debe ser una fecha válida.',
            'fecha_transaccion.required' => 'La fecha de transacción es obligatoria.',
            'fecha_transaccion.date' => 'La fecha de transacción debe ser una fecha válida.',
            'valor_total.required' => 'El valor total es obligatorio.',
            'valor_total.numeric' => 'El valor total debe ser un número.',
            'valor_total.min' => 'El valor total no puede ser negativo.',
            'descuento_total.numeric' => 'El descuento total debe ser un número.',
            'descuento_total.min' => 'El descuento total no puede ser negativo.',
            'banco.max' => 'El nombre del banco no puede exceder 100 caracteres.',
            'conceptos_pago.required' => 'Debe incluir al menos un concepto de pago.',
            'conceptos_pago.array' => 'Los conceptos de pago deben ser un array.',
            'conceptos_pago.min' => 'Debe incluir al menos un concepto de pago.',
            'medios_pago.required' => 'Debe incluir al menos un medio de pago.',
            'medios_pago.array' => 'Los medios de pago deben ser un array.',
            'medios_pago.min' => 'Debe incluir al menos un medio de pago.',
        ];
    }
}

