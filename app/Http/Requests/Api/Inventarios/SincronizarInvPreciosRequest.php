<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para sincronización masiva de precios en una lista de inventario.
 *
 * Recibe un arreglo de ítems con producto_id y precio. El endpoint reemplaza
 * todos los precios vigentes de la lista con los enviados (soft-delete de los que
 * ya no están, upsert de los nuevos/actualizados).
 */
class SincronizarInvPreciosRequest extends FormRequest
{
    /**
     * La autorización se maneja via middleware de permisos en el controlador.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Se aceptan productos de tipo simple, kit y grupo.
     * Los grupos se expanden automáticamente a sus variantes en el controlador.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.producto_id'    => ['required', 'integer', Rule::exists('inv_productos', 'id')],
            'items.*.precio'         => ['required', 'numeric', 'min:0'],
            'items.*.observaciones'  => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required'               => 'Debe enviar al menos un ítem de precio.',
            'items.array'                  => 'Los ítems deben ser un arreglo.',
            'items.min'                    => 'Debe enviar al menos un ítem de precio.',
            'items.*.producto_id.required' => 'El producto es obligatorio en cada ítem.',
            'items.*.producto_id.exists'   => 'El producto no existe.',
            'items.*.precio.required'      => 'El precio es obligatorio en cada ítem.',
            'items.*.precio.min'           => 'El precio no puede ser negativo.',
        ];
    }
}
