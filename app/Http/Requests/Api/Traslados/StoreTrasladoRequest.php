<?php

namespace App\Http\Requests\Api\Traslados;

/**
 * Registro del traslado: los mismos datos de la simulación más el motivo.
 */
class StoreTrasladoRequest extends SimularTrasladoRequest
{
    /**
     * Reglas de validación de la petición.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
    }

    /**
     * Mensajes de validación en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'motivo.required' => 'Indique el motivo del traslado.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max' => 'El motivo no puede superar los 1000 caracteres.',
        ]);
    }
}
