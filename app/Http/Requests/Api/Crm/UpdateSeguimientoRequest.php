<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSeguimientoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     */
    public function authorize(): bool
    {
        return $this->user()->can('crm_seguimientoEditar');
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'referido_id' => 'sometimes|integer|exists:referidos,id',
            'seguidor_id' => 'sometimes|integer|exists:users,id',
            'fecha' => 'sometimes|date',
            'seguimiento' => 'sometimes|string|max:65535',
        ];
    }
}
