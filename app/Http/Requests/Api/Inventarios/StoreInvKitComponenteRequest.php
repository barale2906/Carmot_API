<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para agregar un componente a un kit.
 */
class StoreInvKitComponenteRequest extends FormRequest
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
        $kitId = $this->route('producto');

        return [
            'grupo_producto_id' => [
                'required',
                'integer',
                Rule::exists('inv_productos', 'id')->whereNull('deleted_at'),
                Rule::unique('inv_kit_componentes', 'grupo_producto_id')->where('kit_id', $kitId),
            ],
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
            'grupo_producto_id.required' => 'El producto componente es obligatorio.',
            'grupo_producto_id.exists'   => 'El producto componente seleccionado no existe.',
            'grupo_producto_id.unique'   => 'Este componente ya está incluido en el kit.',
            'cantidad.required'          => 'La cantidad es obligatoria.',
            'cantidad.min'               => 'La cantidad mínima es 1.',
            'cantidad.max'               => 'La cantidad máxima es 999.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'grupo_producto_id' => 'componente',
            'cantidad'          => 'cantidad',
            'orden'             => 'orden',
        ];
    }
}
