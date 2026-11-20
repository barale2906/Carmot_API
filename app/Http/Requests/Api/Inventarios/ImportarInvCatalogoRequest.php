<?php

namespace App\Http\Requests\Api\Inventarios;

use App\Services\ArchivoTabularService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para la carga masiva del catálogo de productos.
 *
 * El archivo (Excel o CSV) debe tener encabezados: codigo, nombre, tipo, categoria, unidad_medida, descripcion, status
 */
class ImportarInvCatalogoRequest extends FormRequest
{
    /**
     * La autorización se resuelve con el middleware de permisos de la ruta.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', ArchivoTabularService::REGLA_MIMES, 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'El archivo es obligatorio.',
            'archivo.file' => 'El campo debe ser un archivo válido.',
            'archivo.mimes' => ArchivoTabularService::MENSAJE_MIMES,
            'archivo.max' => 'El archivo no puede superar 5 MB.',
        ];
    }
}
