<?php

namespace App\Http\Requests\Api\Financiero\LibroDiario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la aprobación del cierre de caja: observaciones opcionales de quien aprueba.
 */
class AprobarCierreLdTurnoCajaRequest extends FormRequest
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
            'observaciones.max' => 'Las observaciones no pueden superar los 1000 caracteres.',
        ];
    }
}
