<?php

namespace App\Http\Requests\Api\Academico\Documentacion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la anulación de un documento.
 *
 * @package App\Http\Requests\Api\Academico\Documentacion
 */
class AnularDocDocumentoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado (los permisos se validan por middleware).
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación de la petición.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => 'required|string|max:500',
        ];
    }

    /**
     * Mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo de la anulación es obligatorio.',
            'motivo.max'      => 'El motivo de la anulación no puede superar los 500 caracteres.',
        ];
    }

    /**
     * Nombres legibles de los campos para los mensajes de error.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'motivo' => 'motivo de la anulación',
        ];
    }
}
