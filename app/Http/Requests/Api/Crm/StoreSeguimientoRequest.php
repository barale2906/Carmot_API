<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeguimientoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('crm_seguimientoCrear');
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'referido_id' => 'required|integer|exists:referidos,id',
            'seguidor_id' => 'required|integer|exists:users,id',
            'fecha' => 'required|date',
            'seguimiento' => 'required|string|max:65535',
        ];
    }
}
