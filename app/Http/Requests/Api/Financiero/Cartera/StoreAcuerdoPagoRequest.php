<?php

namespace App\Http\Requests\Api\Financiero\Cartera;

use App\Models\Academico\Matricula;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el payload para registrar un acuerdo de pago de cartera.
 */
class StoreAcuerdoPagoRequest extends FormRequest
{
    /**
     * La autorización se gestiona por middleware de permisos en el controlador.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación del acuerdo de pago.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'matricula_id'  => 'required|integer|exists:matriculas,id',
            'monto_inicial' => 'required|numeric|min:0',
            'numero_cuotas' => 'required|integer|min:1|max:36',
            'valor_cuota'   => 'required|numeric|min:1',
            'observaciones' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Mensajes de error en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'matricula_id.required'  => 'La matrícula es obligatoria.',
            'matricula_id.exists'    => 'La matrícula seleccionada no existe.',
            'monto_inicial.required' => 'El monto inicial es obligatorio.',
            'monto_inicial.min'      => 'El monto inicial no puede ser negativo.',
            'numero_cuotas.required' => 'El número de cuotas es obligatorio.',
            'numero_cuotas.min'      => 'El acuerdo debe tener al menos 1 cuota.',
            'numero_cuotas.max'      => 'El acuerdo no puede tener más de 36 cuotas.',
            'valor_cuota.required'   => 'El valor de la cuota es obligatorio.',
            'valor_cuota.min'        => 'El valor de la cuota debe ser mayor a cero.',
        ];
    }

    /**
     * Atributos en español para los mensajes de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'matricula_id'  => 'matrícula',
            'monto_inicial' => 'monto inicial',
            'numero_cuotas' => 'número de cuotas',
            'valor_cuota'   => 'valor de cuota',
            'observaciones' => 'observaciones',
        ];
    }

    /**
     * El total pactado (cuota inicial + cuotas × valor) debe cubrir el saldo pendiente de la
     * matrícula (B37): la cartera original se reemplaza y la diferencia se perdía sin rastro.
     * Se admite un total mayor (redondeo de cuotas o intereses de financiación).
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

            $saldo = (float) Matricula::find($this->matricula_id)->carteras()->pendientes()->sum('saldo');
            $total = (float) $this->monto_inicial + (int) $this->numero_cuotas * (float) $this->valor_cuota;

            if ($total < $saldo - 0.01) {
                $validator->errors()->add('valor_cuota', sprintf(
                    'El acuerdo suma $%s y no cubre el saldo pendiente de $%s (faltan $%s).',
                    number_format($total, 0, ',', '.'),
                    number_format($saldo, 0, ',', '.'),
                    number_format($saldo - $total, 0, ',', '.')
                ));
            }
        });
    }
}
