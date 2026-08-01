<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para la carga masiva del catálogo de productos.
 *
 * El archivo CSV debe tener encabezados: codigo, nombre, tipo, categoria, unidad_medida, descripcion, status
 */
class ImportarInvCatalogoRequest extends FormRequest
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
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'El archivo CSV es obligatorio.',
            'archivo.file'     => 'El campo debe ser un archivo válido.',
            'archivo.mimes'    => 'El archivo debe ser de tipo CSV.',
            'archivo.max'      => 'El archivo no puede superar 5 MB.',
        ];
    }
}
