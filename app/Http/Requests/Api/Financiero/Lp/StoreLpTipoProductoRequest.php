<?php

namespace App\Http\Requests\Api\Financiero\Lp;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request StoreLpTipoProductoRequest
 *
 * Valida los datos para crear un nuevo tipo de producto en el sistema de listas de precios.
 * Incluye validación de campos requeridos, códigos únicos y estados.
 *
 * @package App\Http\Requests\Api\Financiero\Lp
 */
class StoreLpTipoProductoRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

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
            'nombre' => 'required|string|max:255',
            'codigo' => 'required|string|max:50|unique:lp_tipos_producto,codigo',
            'es_financiable' => 'sometimes|boolean',
            'descripcion' => 'nullable|string',
            'status' => self::getStatusValidationRule(),
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
            'nombre.required' => 'El nombre del tipo de producto es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'codigo.required' => 'El código del tipo de producto es obligatorio.',
            'codigo.string' => 'El código debe ser una cadena de texto.',
            'codigo.max' => 'El código no puede exceder 50 caracteres.',
            'codigo.unique' => 'El código del tipo de producto ya existe.',
            'es_financiable.boolean' => 'El campo es_financiable debe ser verdadero o falso.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
        ], $statusMessages);
    }
}
