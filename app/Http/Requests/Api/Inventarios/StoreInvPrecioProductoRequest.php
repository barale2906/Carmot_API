<?php

namespace App\Http\Requests\Api\Inventarios;

use App\Models\Inventarios\InvProducto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validación para crear un precio de producto en una lista de precios de inventario.
 *
 * Acepta productos simple, kit y grupo. Un grupo se expande en el controlador a sus
 * variantes activas, por lo que debe tener al menos una para poder recibir precio.
 */
class StoreInvPrecioProductoRequest extends FormRequest
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
     * El endpoint store usa updateOrCreate, por lo que se permiten registros duplicados:
     * si ya existe el precio para ese producto+lista, se actualiza en vez de fallar.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lista_precio_id' => ['required', 'integer', 'exists:lp_listas_precios,id'],
            'producto_id'     => ['required', 'integer', 'exists:inv_productos,id'],
            'precio'          => ['required', 'numeric', 'min:0'],
            'observaciones'   => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Un grupo sin variantes activas no tiene a quién asignarle el precio.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $producto = InvProducto::find($this->producto_id);

            if ($producto?->tipo === 'grupo' && $producto->idsReceptoresDePrecio()->isEmpty()) {
                $v->errors()->add('producto_id', 'El grupo no tiene variantes activas. Agrega variantes antes de asignarle precio.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lista_precio_id.required' => 'La lista de precios es obligatoria.',
            'lista_precio_id.exists'   => 'La lista de precios seleccionada no existe.',
            'producto_id.required'     => 'El producto es obligatorio.',
            'producto_id.exists'       => 'El producto no existe.',
            'precio.required'          => 'El precio es obligatorio.',
            'precio.min'               => 'El precio no puede ser negativo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lista_precio_id' => 'lista de precios',
            'producto_id'     => 'producto',
            'precio'          => 'precio',
            'observaciones'   => 'observaciones',
        ];
    }
}
