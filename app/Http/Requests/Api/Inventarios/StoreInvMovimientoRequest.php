<?php

namespace App\Http\Requests\Api\Inventarios;

use App\Models\Inventarios\InvDocumentoMovimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para registrar un nuevo documento de movimiento de inventario.
 *
 * Un documento puede contener múltiples líneas (lineas[]).
 * Para traslados, almacen_destino_id es obligatorio.
 * Para entradas, proveedor_id es opcional.
 */
class StoreInvMovimientoRequest extends FormRequest
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
            'tipo_documento'      => [
                'required',
                Rule::in([
                    InvDocumentoMovimiento::TIPO_ENTRADA,
                    InvDocumentoMovimiento::TIPO_SALIDA,
                    InvDocumentoMovimiento::TIPO_TRASLADO,
                    InvDocumentoMovimiento::TIPO_AJUSTE,
                    InvDocumentoMovimiento::TIPO_DEVOLUCION,
                ]),
            ],
            'almacen_id'          => ['required', 'integer', 'exists:inv_almacenes,id'],
            'almacen_destino_id'  => [
                'nullable',
                'integer',
                'exists:inv_almacenes,id',
                'different:almacen_id',
                Rule::requiredIf(fn () => $this->tipo_documento === InvDocumentoMovimiento::TIPO_TRASLADO),
            ],
            'proveedor_id'        => ['nullable', 'integer', 'exists:inv_proveedores,id'],
            'motivo'              => ['nullable', 'string', 'max:1000'],

            'lineas'              => ['required', 'array', 'min:1'],
            'lineas.*.producto_id' => ['required', 'integer', 'exists:inv_productos,id'],
            'lineas.*.cantidad'   => ['required', 'integer', 'min:1'],
            'lineas.*.precio_costo' => ['nullable', 'numeric', 'min:0'],
            'lineas.*.tipo_ajuste'  => [
                'nullable',
                Rule::in(['ajuste_positivo', 'ajuste_negativo']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo_documento.required'       => 'El tipo de documento es obligatorio.',
            'tipo_documento.in'             => 'El tipo de documento no es válido.',
            'almacen_id.required'           => 'El almacén es obligatorio.',
            'almacen_id.exists'             => 'El almacén seleccionado no existe.',
            'almacen_destino_id.required'   => 'El almacén destino es obligatorio para traslados.',
            'almacen_destino_id.exists'     => 'El almacén destino seleccionado no existe.',
            'almacen_destino_id.different'  => 'El almacén destino debe ser diferente al almacén origen.',
            'proveedor_id.exists'           => 'El proveedor seleccionado no existe.',
            'lineas.required'               => 'Debe incluir al menos una línea de movimiento.',
            'lineas.min'                    => 'Debe incluir al menos una línea de movimiento.',
            'lineas.*.producto_id.required' => 'El producto es obligatorio en cada línea.',
            'lineas.*.producto_id.exists'   => 'El producto seleccionado en la línea :input no existe.',
            'lineas.*.cantidad.required'    => 'La cantidad es obligatoria en cada línea.',
            'lineas.*.cantidad.min'         => 'La cantidad debe ser mayor a cero.',
            'lineas.*.precio_costo.numeric' => 'El precio de costo debe ser un valor numérico.',
            'lineas.*.precio_costo.min'     => 'El precio de costo no puede ser negativo.',
            'lineas.*.tipo_ajuste.in'       => 'El tipo de ajuste debe ser ajuste_positivo o ajuste_negativo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo_documento'      => 'tipo de documento',
            'almacen_id'          => 'almacén',
            'almacen_destino_id'  => 'almacén destino',
            'proveedor_id'        => 'proveedor',
            'motivo'              => 'motivo',
            'lineas'              => 'líneas',
            'lineas.*.producto_id' => 'producto',
            'lineas.*.cantidad'   => 'cantidad',
            'lineas.*.precio_costo' => 'precio de costo',
        ];
    }
}
