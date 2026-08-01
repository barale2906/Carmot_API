<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para completar la entrega de componentes de un kit desde la pantalla
 * de Entregas Pendientes (cajero elige variante por componente de tipo grupo).
 */
class CompletarInvEntregaKitRequest extends FormRequest
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
            'componentes'                            => ['required', 'array', 'min:1'],
            'componentes.*.kit_componente_id'        => ['required', 'integer', 'exists:inv_kit_componentes,id'],
            'componentes.*.producto_entregado_id'    => ['nullable', 'integer', 'exists:inv_productos,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'componentes.required'                         => 'Debe indicar los componentes a entregar.',
            'componentes.*.kit_componente_id.required'     => 'El ID del componente es obligatorio.',
            'componentes.*.kit_componente_id.exists'       => 'El componente seleccionado no existe.',
            'componentes.*.producto_entregado_id.exists'   => 'La variante seleccionada no existe.',
        ];
    }
}
