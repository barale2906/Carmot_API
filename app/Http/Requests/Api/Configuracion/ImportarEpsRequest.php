<?php

namespace App\Http\Requests\Api\Configuracion;

use App\Services\ArchivoTabularService;
use Illuminate\Foundation\Http\FormRequest;

class ImportarEpsRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', ArchivoTabularService::REGLA_MIMES, 'max:5120'],
        ];
    }

    /**
     * Obtiene los mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'El archivo es obligatorio.',
            'archivo.file' => 'El campo archivo debe ser un archivo válido.',
            'archivo.mimes' => ArchivoTabularService::MENSAJE_MIMES,
            'archivo.max' => 'El archivo no puede exceder 5 MB.',
        ];
    }

    /**
     * Obtiene los atributos personalizados para los mensajes de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'archivo' => 'archivo CSV',
        ];
    }
}
