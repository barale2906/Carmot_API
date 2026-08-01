<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para la importación masiva de stock inicial desde CSV.
 */
class ImportarInvStockRequest extends FormRequest
{
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
            'archivo'    => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'almacen_id' => ['required', 'integer', 'exists:inv_almacenes,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required'    => 'El archivo CSV es obligatorio.',
            'archivo.file'        => 'El campo debe ser un archivo.',
            'archivo.mimes'       => 'El archivo debe ser de tipo CSV o TXT.',
            'archivo.max'         => 'El archivo no puede superar 5 MB.',
            'almacen_id.required' => 'El almacén es obligatorio para la importación.',
            'almacen_id.exists'   => 'El almacén seleccionado no existe.',
        ];
    }
}
