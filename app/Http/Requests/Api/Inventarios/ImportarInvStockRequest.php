<?php

namespace App\Http\Requests\Api\Inventarios;

use App\Services\ArchivoTabularService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para la importación masiva de stock inicial desde XLSX.
 */
class ImportarInvStockRequest extends FormRequest
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
            'almacen_id' => ['required', 'integer', 'exists:inv_almacenes,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'El archivo es obligatorio.',
            'archivo.file' => 'El campo debe ser un archivo.',
            'archivo.mimes' => ArchivoTabularService::MENSAJE_MIMES,
            'archivo.max' => 'El archivo no puede superar 5 MB.',
            'almacen_id.required' => 'El almacén es obligatorio para la importación.',
            'almacen_id.exists' => 'El almacén seleccionado no existe.',
        ];
    }
}
