<?php

namespace App\Http\Requests\Api\Financiero\LibroDiario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el cierre del turno de caja: efectivo contado en el arqueo y observaciones.
 */
class CerrarLdTurnoCajaRequest extends FormRequest
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
            'efectivo_contado' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Mensajes de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'efectivo_contado.required' => 'El efectivo contado en el arqueo es obligatorio.',
            'efectivo_contado.min' => 'El efectivo contado no puede ser negativo.',
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
            'efectivo_contado' => 'efectivo contado',
            'observaciones' => 'observaciones',
        ];
    }
}
