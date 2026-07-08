<?php

namespace App\Http\Requests\Api\Financiero\ReciboPago;

use App\Models\Financiero\ReciboPago\ReciboPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request StoreReciboPagoRequest
 *
 * Valida los datos para crear un recibo de pago en modo unificado.
 *
 * Flujo:
 *  1. Se selecciona un estudiante y una matrícula activa.
 *  2. El operador carga opcionalmente conceptos adicionales (certificados, copias…)
 *     cuyo valor se toma de conceptos_pago.valor × cantidad.
 *  3. El operador ingresa el monto total que paga el estudiante (monto_a_pagar).
 *  4. El servidor distribuye: primero los conceptos adicionales, luego el saldo
 *     restante entre las cuotas pendientes de más antigua a más reciente.
 *  5. Se registran los medios de pago.
 *
 * @package App\Http\Requests\Api\Financiero\ReciboPago
 */
class StoreReciboPagoRequest extends FormRequest
{
    /**
     * La autorización se gestiona mediante middleware de permisos en el controlador.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación del modo unificado.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Encabezado del recibo
            'sede_id'                                   => 'required|exists:sedes,id',
            'cajero_id'                                 => 'required|exists:users,id',
            'matricula_id'                              => 'required|exists:matriculas,id',
            'origen'                                    => ['required', 'integer', Rule::in([ReciboPago::ORIGEN_INVENTARIOS, ReciboPago::ORIGEN_ACADEMICO])],
            'fecha_recibo'                              => 'required|date',
            'fecha_transaccion'                         => 'required|date',
            'banco'                                     => 'nullable|string|max:100',

            // Monto total que el estudiante paga en este recibo
            'monto_a_pagar'                             => 'required|numeric|min:0.01',

            // Lista de precios vigente (referencia para auditoría y descuentos)
            'lista_precio_id'                           => 'nullable|exists:lp_listas_precios,id',

            // Si se aplica descuento por pronto pago sobre las cuotas de cartera
            'aplicar_descuento'                         => 'nullable|boolean',

            // Conceptos adicionales al cobro de cartera (certificados, copias, etc.)
            // Su precio unitario se toma directamente de conceptos_pago.valor.
            'conceptos_adicionales'                     => 'nullable|array',
            'conceptos_adicionales.*.concepto_pago_id' => 'required|integer|exists:conceptos_pago,id',
            'conceptos_adicionales.*.cantidad'          => 'required|integer|min:1',

            // Medios de pago — la suma debe igualar monto_a_pagar (bruto, incluye sobrecargos)
            'medios_pago'                               => 'required|array|min:1',
            'medios_pago.*.medio_pago'                  => ['required', 'string', Rule::in([
                'efectivo', 'transferencia', 'tarjeta_debito',
                'tarjeta_credito', 'cheque', 'consignacion',
            ])],
            // tipo_tarjeta: libre y configurable (visa, mastercard, amex, etc.)
            // Solo aplica cuando medio_pago es tarjeta_debito o tarjeta_credito.
            'medios_pago.*.tipo_tarjeta'                => 'nullable|string|max:60',
            'medios_pago.*.valor'                       => 'required|numeric|min:0',
            'medios_pago.*.referencia'                  => 'nullable|string|max:100',
            'medios_pago.*.banco'                       => 'nullable|string|max:100',

            // Sobrecargos seleccionados por el cajero (pre-calculados vía /precalcular-sobrecargos)
            // Cada ítem vincula un sobrecargo a un índice de medio de pago
            'sobrecargos'                               => 'nullable|array',
            'sobrecargos.*.descuento_id'                => 'required|exists:descuentos,id',
            'sobrecargos.*.medio_pago_index'            => 'required|integer|min:0', // índice en medios_pago[]
        ];
    }

    /**
     * Validaciones cruzadas: suma de medios de pago debe igualar monto_a_pagar.
     *
     * @param \Illuminate\Validation\Validator $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('medios_pago') && $this->has('monto_a_pagar')) {
                $suma = collect($this->medios_pago)->sum('valor');
                if (abs($suma - (float) $this->monto_a_pagar) > 0.01) {
                    $validator->errors()->add(
                        'medios_pago',
                        'La suma de los medios de pago debe ser igual al monto a pagar (incluyendo sobrecargos).'
                    );
                }
            }

            // Validar que los índices de sobrecargos referencien medios_pago existentes
            if ($this->has('sobrecargos') && $this->has('medios_pago')) {
                $totalMedios = count($this->medios_pago);
                foreach ($this->input('sobrecargos', []) as $i => $s) {
                    $idx = (int) ($s['medio_pago_index'] ?? -1);
                    if ($idx < 0 || $idx >= $totalMedios) {
                        $validator->errors()->add(
                            "sobrecargos.{$i}.medio_pago_index",
                            "El índice de medio de pago no es válido."
                        );
                    }
                }
            }
        });
    }

    /**
     * Mensajes de validación en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sede_id.required'                                   => 'La sede es obligatoria.',
            'sede_id.exists'                                     => 'La sede seleccionada no existe.',
            'cajero_id.required'                                 => 'El cajero es obligatorio.',
            'cajero_id.exists'                                   => 'El cajero seleccionado no existe.',
            'matricula_id.required'                              => 'La matrícula es obligatoria.',
            'matricula_id.exists'                                => 'La matrícula seleccionada no existe.',
            'origen.required'                                    => 'El origen es obligatorio.',
            'origen.in'                                          => 'El origen debe ser 0 (Inventarios) o 1 (Académico).',
            'fecha_recibo.required'                              => 'La fecha del recibo es obligatoria.',
            'fecha_recibo.date'                                  => 'La fecha del recibo debe ser una fecha válida.',
            'fecha_transaccion.required'                         => 'La fecha de transacción es obligatoria.',
            'fecha_transaccion.date'                             => 'La fecha de transacción debe ser una fecha válida.',
            'monto_a_pagar.required'                             => 'El monto a pagar es obligatorio.',
            'monto_a_pagar.numeric'                              => 'El monto a pagar debe ser un número.',
            'monto_a_pagar.min'                                  => 'El monto a pagar debe ser mayor a cero.',
            'medios_pago.required'                               => 'Debe incluir al menos un medio de pago.',
            'medios_pago.min'                                    => 'Debe incluir al menos un medio de pago.',
            'medios_pago.*.medio_pago.required'                  => 'El tipo de medio de pago es obligatorio.',
            'medios_pago.*.medio_pago.in'                        => 'El medio de pago no es válido.',
            'medios_pago.*.tipo_tarjeta.max'                     => 'La marca de tarjeta no puede exceder 60 caracteres.',
            'medios_pago.*.valor.required'                       => 'El valor del medio de pago es obligatorio.',
            'medios_pago.*.valor.min'                            => 'El valor del medio de pago no puede ser negativo.',
            'sobrecargos.*.descuento_id.required'                => 'El ID del sobrecargo es obligatorio.',
            'sobrecargos.*.descuento_id.exists'                  => 'El sobrecargo seleccionado no existe.',
            'sobrecargos.*.medio_pago_index.required'            => 'El índice de medio de pago del sobrecargo es obligatorio.',
            'conceptos_adicionales.*.concepto_pago_id.required'  => 'El concepto adicional es obligatorio.',
            'conceptos_adicionales.*.concepto_pago_id.exists'    => 'Uno de los conceptos adicionales no existe.',
            'conceptos_adicionales.*.cantidad.required'          => 'La cantidad es obligatoria para cada concepto adicional.',
            'conceptos_adicionales.*.cantidad.min'               => 'La cantidad de cada concepto debe ser al menos 1.',
        ];
    }

    /**
     * Nombres de atributos en español para los mensajes genéricos.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sede_id'       => 'sede',
            'cajero_id'     => 'cajero',
            'matricula_id'  => 'matrícula',
            'monto_a_pagar' => 'monto a pagar',
            'origen'        => 'origen',
        ];
    }
}
