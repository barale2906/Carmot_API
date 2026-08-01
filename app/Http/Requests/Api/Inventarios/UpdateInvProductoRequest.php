<?php

namespace App\Http\Requests\Api\Inventarios;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para actualizar un producto del inventario.
 */
class UpdateInvProductoRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productoId = $this->route('producto');

        return [
            'codigo'            => [
                'required',
                'string',
                'max:100',
                Rule::unique('inv_productos', 'codigo')
                    ->ignore($productoId)
                    ->whereNull('deleted_at'),
            ],
            'nombre'            => ['required', 'string', 'max:255'],
            'descripcion'       => ['nullable', 'string', 'max:2000'],
            'tipo'              => ['required', Rule::in(['simple', 'kit', 'grupo'])],
            'imagen'            => ['nullable', 'image', 'max:2048'],
            'categoria_id'      => ['nullable', 'integer', 'exists:inv_categorias,id'],
            'unidad_medida_id'  => ['nullable', 'integer', 'exists:inv_unidades_medida,id'],
            'producto_padre_id' => [
                'nullable',
                'integer',
                Rule::exists('inv_productos', 'id')->where('tipo', 'grupo')->whereNull('deleted_at'),
            ],
            'status'            => self::getStatusValidationRule(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'codigo.required'            => 'El código del producto es obligatorio.',
            'codigo.unique'              => 'Ya existe otro producto con ese código.',
            'nombre.required'            => 'El nombre del producto es obligatorio.',
            'tipo.required'              => 'El tipo de producto es obligatorio.',
            'tipo.in'                    => 'El tipo debe ser: simple, kit o grupo.',
            'categoria_id.exists'        => 'La categoría seleccionada no existe.',
            'unidad_medida_id.exists'    => 'La unidad de medida seleccionada no existe.',
            'producto_padre_id.exists'   => 'El grupo padre seleccionado no existe o no es de tipo grupo.',
            'imagen.image'               => 'El archivo debe ser una imagen.',
            'imagen.max'                 => 'La imagen no puede superar 2 MB.',
        ], self::getStatusValidationMessages());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'codigo'            => 'código',
            'nombre'            => 'nombre',
            'descripcion'       => 'descripción',
            'tipo'              => 'tipo',
            'categoria_id'      => 'categoría',
            'unidad_medida_id'  => 'unidad de medida',
            'producto_padre_id' => 'grupo padre',
            'status'            => 'estado',
        ];
    }
}
