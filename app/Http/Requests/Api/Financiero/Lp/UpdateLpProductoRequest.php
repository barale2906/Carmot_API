<?php

namespace App\Http\Requests\Api\Financiero\Lp;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request UpdateLpProductoRequest
 *
 * Valida los datos para actualizar un producto existente en el catálogo LP.
 * Todos los campos son opcionales (sometimes) para permitir actualizaciones parciales.
 * La gestión de referencias académicas se realiza mediante LpProductoReferenciaController.
 *
 * @package App\Http\Requests\Api\Financiero\Lp
 */
class UpdateLpProductoRequest extends FormRequest
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
     * Todas las reglas usan 'sometimes' para permitir actualizaciones parciales.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productoId = $this->route('lpProducto') ?? $this->route('lp_producto');

        return [
            'tipo_producto_id' => 'sometimes|integer|exists:lp_tipos_producto,id',
            'nombre'           => 'sometimes|string|max:255',
            'codigo'           => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('lp_productos', 'codigo')->ignore($productoId),
            ],
            'descripcion' => 'nullable|string',
            'status'      => self::getStatusValidationRule(),
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
            'tipo_producto_id.integer'  => 'El tipo de producto debe ser un número entero.',
            'tipo_producto_id.exists'   => 'El tipo de producto seleccionado no existe.',
            'nombre.string'             => 'El nombre debe ser una cadena de texto.',
            'nombre.max'                => 'El nombre no puede exceder 255 caracteres.',
            'codigo.string'             => 'El código debe ser una cadena de texto.',
            'codigo.max'                => 'El código no puede exceder 100 caracteres.',
            'codigo.unique'             => 'El código del producto ya existe.',
            'descripcion.string'        => 'La descripción debe ser una cadena de texto.',
        ], $statusMessages);
    }
}
