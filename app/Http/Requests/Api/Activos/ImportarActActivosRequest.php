<?php

namespace App\Http\Requests\Api\Activos;

use App\Services\ArchivoTabularService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el archivo de carga masiva de activos fijos.
 *
 * El contenido de cada fila lo valida ActActivoImportacionService con las reglas del
 * registro individual; aquí solo el archivo.
 */
class ImportarActActivosRequest extends FormRequest
{
    /**
     * La autorización se resuelve con el middleware de permisos.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', ArchivoTabularService::REGLA_MIMES, 'max:5120'],
        ];
    }

    /**
     * Mensajes de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'El archivo es obligatorio.',
            'archivo.file' => 'El archivo no es válido.',
            'archivo.mimes' => ArchivoTabularService::MENSAJE_MIMES,
            'archivo.max' => 'El archivo no puede exceder 5 MB.',
        ];
    }
}
