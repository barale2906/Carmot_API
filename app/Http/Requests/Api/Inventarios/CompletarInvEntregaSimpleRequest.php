<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para completar (total o parcialmente) la entrega de un ítem simple.
 *
 * Sin `cantidad` se entrega todo lo que el stock permita; con `cantidad` se limita
 * la entrega a esa cifra, dejando el resto pendiente.
 */
class CompletarInvEntregaSimpleRequest extends FormRequest
{
    /**
     * La autorización se resuelve con el middleware de permisos de la ruta.
     *
     * @return bool
     */
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
            'cantidad'       => ['nullable', 'integer', 'min:1'],
            'forzar_parcial' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cantidad.integer' => 'La cantidad a entregar debe ser un número entero.',
            'cantidad.min'     => 'La cantidad a entregar debe ser al menos 1.',
            'forzar_parcial.boolean' => 'El indicador de entrega parcial forzada debe ser verdadero o falso.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cantidad'       => 'cantidad a entregar',
            'forzar_parcial' => 'entrega parcial forzada',
        ];
    }
}
