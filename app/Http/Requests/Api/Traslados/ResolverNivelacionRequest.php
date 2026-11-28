<?php

namespace App\Http\Requests\Api\Traslados;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cierre de la nivelación pendiente de un traslado, con lo que se acordó hacer.
 */
class ResolverNivelacionRequest extends FormRequest
{
    /**
     * La autorización se hace con el middleware de permisos de la ruta.
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
            'observaciones' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * Mensajes de validación en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'observaciones.required' => 'Indique cómo se resolvió la nivelación.',
            'observaciones.min' => 'La observación debe tener al menos 5 caracteres.',
        ];
    }
}
