<?php

namespace App\Http\Requests\Api\Inventarios;

use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Inventarios\InvPrecioProducto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validación para actualizar el precio de un producto de inventario.
 */
class UpdateInvPrecioProductoRequest extends FormRequest
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
            'precio'        => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Rechaza el cambio si la lista del precio ya está aprobada o activa (N14).
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $precio = $this->route('precio');

            if ($precio instanceof InvPrecioProducto && $precio->listaPrecio?->preciosBloqueados()) {
                $v->errors()->add('precio', LpListaPrecio::MENSAJE_PRECIOS_BLOQUEADOS);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'precio.required' => 'El precio es obligatorio.',
            'precio.min'      => 'El precio no puede ser negativo.',
        ];
    }
}
