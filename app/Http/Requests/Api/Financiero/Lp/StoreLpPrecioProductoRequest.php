<?php

namespace App\Http\Requests\Api\Financiero\Lp;

use App\Models\Financiero\Lp\LpProducto;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request StoreLpPrecioProductoRequest
 *
 * Valida los datos para crear un nuevo precio de producto en una lista de precios.
 * Incluye validación condicional de campos financiables según el tipo de producto.
 *
 * @package App\Http\Requests\Api\Financiero\Lp
 */
class StoreLpPrecioProductoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     * La autorización se maneja mediante middleware y permisos.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lista_precio_id' => 'required|integer|exists:lp_listas_precios,id',
            'producto_id' => 'required|integer|exists:lp_productos,id',
            'precio_contado' => 'required|numeric|min:0',
            'precio_total' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    if ($this->filled('producto_id')) {
                        $producto = LpProducto::find($this->producto_id);
                        if ($producto && $producto->esFinanciable() && !$value) {
                            $fail('El precio total es obligatorio para productos financiables.');
                        }
                    }
                },
            ],
            'matricula' => 'required|numeric|min:0',
            'numero_cuotas' => [
                'nullable',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    if ($this->filled('producto_id')) {
                        $producto = LpProducto::find($this->producto_id);
                        if ($producto && $producto->esFinanciable() && (!$value || $value <= 0)) {
                            $fail('El número de cuotas es obligatorio y debe ser mayor a 0 para productos financiables.');
                        }
                    }
                },
            ],
            'valor_cuota' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string',
        ];
    }

    /**
     * Configurar validaciones adicionales después de las reglas básicas.
     * Valida que los campos financiables estén presentes para productos financiables.
     *
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('producto_id')) {
                $producto = LpProducto::with('tipoProducto')->find($this->producto_id);

                if ($producto && $producto->esFinanciable()) {
                    // Para productos financiables, validar campos requeridos
                    if (!$this->filled('precio_total')) {
                        $validator->errors()->add('precio_total', 'El precio total es obligatorio para productos financiables.');
                    }

                    if (!$this->filled('numero_cuotas') || $this->numero_cuotas <= 0) {
                        $validator->errors()->add('numero_cuotas', 'El número de cuotas es obligatorio y debe ser mayor a 0 para productos financiables.');
                    }

                    // Validar que precio_total >= matricula
                    if ($this->filled('precio_total') && $this->filled('matricula')) {
                        if ($this->precio_total < $this->matricula) {
                            $validator->errors()->add('precio_total', 'El precio total debe ser mayor o igual a la matrícula.');
                        }
                    }
                } else {
                    // Para productos no financiables, limpiar campos de financiación
                    if ($this->filled('precio_total') || $this->filled('numero_cuotas')) {
                        $validator->errors()->add('precio_total', 'Los campos de financiación no aplican para productos no financiables.');
                    }
                }
            }
        });
    }

    /**
     * Obtiene los mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lista_precio_id.required' => 'La lista de precios es obligatoria.',
            'lista_precio_id.integer' => 'La lista de precios debe ser un número entero.',
            'lista_precio_id.exists' => 'La lista de precios seleccionada no existe.',
            'producto_id.required' => 'El producto es obligatorio.',
            'producto_id.integer' => 'El producto debe ser un número entero.',
            'producto_id.exists' => 'El producto seleccionado no existe.',
            'precio_contado.required' => 'El precio de contado es obligatorio.',
            'precio_contado.numeric' => 'El precio de contado debe ser un número.',
            'precio_contado.min' => 'El precio de contado debe ser mayor o igual a 0.',
            'precio_total.numeric' => 'El precio total debe ser un número.',
            'precio_total.min' => 'El precio total debe ser mayor o igual a 0.',
            'matricula.required' => 'La matrícula es obligatoria (puede ser 0).',
            'matricula.numeric' => 'La matrícula debe ser un número.',
            'matricula.min' => 'La matrícula debe ser mayor o igual a 0.',
            'numero_cuotas.integer' => 'El número de cuotas debe ser un número entero.',
            'numero_cuotas.min' => 'El número de cuotas debe ser mayor a 0.',
            'valor_cuota.numeric' => 'El valor de la cuota debe ser un número.',
            'valor_cuota.min' => 'El valor de la cuota debe ser mayor o igual a 0.',
            'observaciones.string' => 'Las observaciones deben ser una cadena de texto.',
        ];
    }
}
