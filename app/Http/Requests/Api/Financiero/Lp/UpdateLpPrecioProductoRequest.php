<?php

namespace App\Http\Requests\Api\Financiero\Lp;

use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Models\Financiero\Lp\LpProducto;
use App\Models\Financiero\Lp\LpProductoReferencia;
use App\Services\Financiero\LpPrecioProductoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Request UpdateLpPrecioProductoRequest
 *
 * Valida los datos para actualizar un precio de producto existente en una lista de precios.
 * Todos los campos son opcionales (sometimes) para permitir actualizaciones parciales.
 * Incluye validación condicional de campos financiables según el tipo de producto.
 *
 * @package App\Http\Requests\Api\Financiero\Lp
 */
class UpdateLpPrecioProductoRequest extends FormRequest
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
     * Todas las reglas usan 'sometimes' para permitir actualizaciones parciales.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lista_precio_id' => 'sometimes|integer|exists:lp_listas_precios,id',
            'producto_id' => 'sometimes|integer|exists:lp_productos,id',
            'precio_contado' => 'sometimes|numeric|min:0',
            'precio_total' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    $precioProducto = $this->resolvedLpPrecioProducto();
                    $productoId = $this->input('producto_id') ?? ($precioProducto ? $precioProducto->producto_id : null);
                    if ($productoId) {
                        $producto = LpProducto::find($productoId);
                        if ($producto && $producto->esFinanciable() && !$value) {
                            $fail('El precio total es obligatorio para productos financiables.');
                        }
                    }
                },
            ],
            'matricula' => 'sometimes|numeric|min:0',
            'numero_cuotas' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $precioProducto = $this->resolvedLpPrecioProducto();
                    $productoId = $this->input('producto_id') ?? ($precioProducto ? $precioProducto->producto_id : null);
                    if ($productoId) {
                        $producto = LpProducto::find($productoId);
                        if ($producto && $producto->esFinanciable() && (!$value || $value <= 0)) {
                            $fail('El número de cuotas es obligatorio y debe ser mayor a 0 para productos financiables.');
                        }
                    }
                },
            ],
            'valor_cuota' => 'sometimes|nullable|numeric|min:0',
            'observaciones' => 'nullable|string',
            'referencia_tipo' => ['sometimes', 'nullable', 'string', Rule::in(LpProductoReferencia::tiposValidos())],
            'referencia_id'   => 'sometimes|nullable|required_with:referencia_tipo|integer',
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
            $precioProducto = $this->resolvedLpPrecioProducto();

            $productoId = $this->input('producto_id') ?? ($precioProducto ? $precioProducto->producto_id : null);

            if ($productoId) {
                $producto = LpProducto::with('tipoProducto')->find($productoId);

                if ($producto && $producto->esFinanciable()) {
                    // Para productos financiables, validar campos requeridos si se están actualizando
                    $precioTotal = $this->input('precio_total') ?? ($precioProducto ? $precioProducto->precio_total : null);
                    $matricula = $this->input('matricula') ?? ($precioProducto ? $precioProducto->matricula : null);
                    $precioContado = $this->input('precio_contado') ?? ($precioProducto ? $precioProducto->precio_contado : null);

                    if ($this->filled('precio_total') && !$precioTotal) {
                        $validator->errors()->add('precio_total', 'El precio total es obligatorio para productos financiables.');
                    }

                    // Validar que precio_total >= matricula si ambos están presentes
                    if ($precioTotal !== null && $matricula !== null && (float) $precioTotal < (float) $matricula) {
                        $validator->errors()->add('precio_total', 'El precio total debe ser mayor o igual a la matrícula.');
                    }

                    // Estado combinado (payload + registro) para productos financiables
                    if ($precioProducto && $precioContado !== null && $precioTotal !== null && $matricula !== null) {
                        $service = app(LpPrecioProductoService::class);
                        if (!$service->precioContadoCuadraConFinanciacion(
                            (float) $precioContado,
                            (float) $matricula,
                            (float) $precioTotal
                        )) {
                            $validator->errors()->add('precio_contado', $service->mensajePrecioContadoFinanciacion());
                        }
                    }

                    $numeroCuotasFinal = $this->has('numero_cuotas')
                        ? $this->input('numero_cuotas')
                        : ($precioProducto ? $precioProducto->numero_cuotas : null);
                    if ($numeroCuotasFinal === null || (int) $numeroCuotasFinal <= 0) {
                        $validator->errors()->add('numero_cuotas', 'El número de cuotas debe ser mayor a 0 para productos financiables.');
                    }
                }
            }

            // Validar que la entidad académica exista si se envía referencia
            if ($this->filled('referencia_id') && $this->filled('referencia_tipo')) {
                $tabla  = $this->input('referencia_tipo') === LpProductoReferencia::TIPO_CURSO ? 'cursos' : 'modulos';
                $existe = DB::table($tabla)
                    ->whereNull('deleted_at')
                    ->where('id', $this->integer('referencia_id'))
                    ->exists();

                if (! $existe) {
                    $tipo = $this->input('referencia_tipo');
                    $validator->errors()->add(
                        'referencia_id',
                        "El {$tipo} con ID {$this->input('referencia_id')} no existe o está eliminado."
                    );
                }
            }
        });
    }

    /**
     * Modelo de precio en ruta (apiResource puede usar distinto nombre de parámetro).
     */
    private function resolvedLpPrecioProducto(): ?LpPrecioProducto
    {
        foreach ($this->route()?->parameters() ?? [] as $value) {
            if ($value instanceof LpPrecioProducto) {
                return $value;
            }
        }

        $r = $this->route('lp_precio_producto');
        if ($r instanceof LpPrecioProducto) {
            return $r;
        }

        foreach ($this->route()?->parameters() ?? [] as $value) {
            if (is_numeric($value)) {
                $model = LpPrecioProducto::query()->find((int) $value);
                if ($model !== null) {
                    return $model;
                }
            }
        }

        return null;
    }

    /**
     * Obtiene los mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lista_precio_id.integer' => 'La lista de precios debe ser un número entero.',
            'lista_precio_id.exists' => 'La lista de precios seleccionada no existe.',
            'producto_id.integer' => 'El producto debe ser un número entero.',
            'producto_id.exists' => 'El producto seleccionado no existe.',
            'precio_contado.numeric' => 'El precio de contado debe ser un número.',
            'precio_contado.min' => 'El precio de contado debe ser mayor o igual a 0.',
            'precio_total.numeric' => 'El precio total debe ser un número.',
            'precio_total.min' => 'El precio total debe ser mayor o igual a 0.',
            'matricula.numeric' => 'La matrícula debe ser un número.',
            'matricula.min' => 'La matrícula debe ser mayor o igual a 0.',
            'numero_cuotas.integer' => 'El número de cuotas debe ser un número entero.',
            'numero_cuotas.min' => 'El número de cuotas debe ser mayor a 0.',
            'valor_cuota.numeric' => 'El valor de la cuota debe ser un número.',
            'valor_cuota.min' => 'El valor de la cuota debe ser mayor o igual a 0.',
            'observaciones.string'        => 'Las observaciones deben ser una cadena de texto.',
            'referencia_tipo.in'          => 'El tipo de referencia debe ser "curso" o "modulo".',
            'referencia_id.integer'       => 'El ID de la referencia debe ser un número entero.',
            'referencia_id.required_with' => 'El ID de referencia es obligatorio cuando se indica el tipo de referencia.',
        ];
    }
}
