<?php

namespace App\Http\Requests\Api\Financiero\Lp;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request UpdateLpTipoProductoRequest
 *
 * Valida los datos para actualizar un tipo de producto existente en el sistema de listas de precios.
 * Todos los campos son opcionales (sometimes) para permitir actualizaciones parciales.
 *
 * @package App\Http\Requests\Api\Financiero\Lp
 */
class UpdateLpTipoProductoRequest extends FormRequest
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
     * Todas las reglas usan 'sometimes' para permitir actualizaciones parciales.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tipoProductoId = $this->route('lp_tipo_producto') ?? $this->route('tipo_producto');

        return [
            'nombre' => 'sometimes|string|max:255',
            'codigo' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('lp_tipos_producto', 'codigo')->ignore($tipoProductoId)
            ],
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
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'codigo.string' => 'El código debe ser una cadena de texto.',
            'codigo.max' => 'El código no puede exceder 50 caracteres.',
            'codigo.unique' => 'El código del tipo de producto ya existe.',
            'es_financiable.boolean' => 'El campo es_financiable debe ser verdadero o falso.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
        ], $statusMessages);
    }
}
