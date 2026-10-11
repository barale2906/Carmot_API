<?php

namespace App\Http\Requests\Api\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Services\Financiero\LibroDiario\LdTurnoCajaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida el registro de una consignación del efectivo de la caja.
 *
 * Se exige un turno abierto de quien registra y que el valor no supere el
 * efectivo esperado del turno.
 */
class StoreLdConsignacionRequest extends FormRequest
{
    /**
     * La autorización se resuelve con el middleware de permisos.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'banco_id' => ['required', 'integer', Rule::exists('bancos', 'id')->whereNull('deleted_at')],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'titular' => ['required', 'string', 'max:200'],
            'numero_cuenta' => ['nullable', 'string', 'max:40'],
            'numero_comprobante' => ['nullable', 'string', 'max:60'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Valida el turno y que haya efectivo suficiente para consignar.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $turno = $this->turnoDelRegistro();

            if (! $turno) {
                $v->errors()->add('turno', 'Debe abrir un turno de caja antes de registrar consignaciones.');

                return;
            }

            if ($v->errors()->has('valor')) {
                return;
            }

            $disponible = app(LdTurnoCajaService::class)->calcularResumen($turno)['efectivo_esperado']
                + $this->valorActual();

            if ((float) $this->valor > $disponible + 0.01) {
                $v->errors()->add('valor', 'El valor supera el efectivo disponible en caja.');
            }
        });
    }

    /**
     * Turno contra el que se valida el efectivo: el abierto de quien registra.
     */
    protected function turnoDelRegistro(): ?LdTurnoCaja
    {
        return app(LdTurnoCajaService::class)->turnoAbiertoDe($this->user()->id);
    }

    /**
     * Valor que la consignación ya descuenta del efectivo (0 al crear).
     */
    protected function valorActual(): float
    {
        return 0.0;
    }

    /**
     * Mensajes de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'banco_id.required' => 'El banco es obligatorio.',
            'banco_id.exists' => 'El banco seleccionado no existe.',
            'valor.required' => 'El valor consignado es obligatorio.',
            'valor.min' => 'El valor consignado debe ser mayor a 0.',
            'titular.required' => 'Indique a nombre de quién se hace la consignación.',
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
            'banco_id' => 'banco',
            'valor' => 'valor',
            'titular' => 'titular',
            'numero_cuenta' => 'número de cuenta',
            'numero_comprobante' => 'número de comprobante',
            'observaciones' => 'observaciones',
        ];
    }
}
