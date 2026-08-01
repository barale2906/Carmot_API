<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para anular un documento de movimiento de inventario.
 */
class AnularInvMovimientoRequest extends FormRequest
{
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
            'motivo_anulacion' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo_anulacion.required' => 'El motivo de anulación es obligatorio.',
            'motivo_anulacion.min'      => 'El motivo de anulación debe tener al menos 10 caracteres.',
            'motivo_anulacion.max'      => 'El motivo de anulación no puede superar 1000 caracteres.',
        ];
    }
}
