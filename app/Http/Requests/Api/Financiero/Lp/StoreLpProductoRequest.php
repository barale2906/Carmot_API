<?php

namespace App\Http\Requests\Api\Financiero\Lp;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request StoreLpProductoRequest
 *
 * Valida los datos para crear un nuevo producto en el catálogo LP.
 * La vinculación con cursos y módulos se gestiona de forma separada
 * mediante el endpoint de referencias (LpProductoReferenciaController).
 *
 * @package App\Http\Requests\Api\Financiero\Lp
 */
class StoreLpProductoRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo_producto_id' => 'required|integer|exists:lp_tipos_producto,id',
            'nombre'           => 'required|string|max:255',
            'codigo'           => 'nullable|string|max:100|unique:lp_productos,codigo',
            'descripcion'      => 'nullable|string',
            'status'           => self::getStatusValidationRule(),
        ];
    }

    /**
     * Obtiene los mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $statusMessages = self::getStatusValidationMessages();

        return array_merge([
            'tipo_producto_id.required' => 'El tipo de producto es obligatorio.',
            'tipo_producto_id.integer'  => 'El tipo de producto debe ser un número entero.',
            'tipo_producto_id.exists'   => 'El tipo de producto seleccionado no existe.',
            'nombre.required'           => 'El nombre del producto es obligatorio.',
            'nombre.string'             => 'El nombre debe ser una cadena de texto.',
            'nombre.max'                => 'El nombre no puede exceder 255 caracteres.',
            'codigo.string'             => 'El código debe ser una cadena de texto.',
            'codigo.max'                => 'El código no puede exceder 100 caracteres.',
            'codigo.unique'             => 'El código del producto ya existe.',
            'descripcion.string'        => 'La descripción debe ser una cadena de texto.',
        ], $statusMessages);
    }
}
