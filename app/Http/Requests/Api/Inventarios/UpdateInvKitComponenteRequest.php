<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para actualizar la cantidad u orden de un componente de kit.
 */
class UpdateInvKitComponenteRequest extends FormRequest
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
            'cantidad' => ['required', 'integer', 'min:1', 'max:999'],
            'orden'    => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.min'      => 'La cantidad mínima es 1.',
            'cantidad.max'      => 'La cantidad máxima es 999.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cantidad' => 'cantidad',
            'orden'    => 'orden',
        ];
    }
}
