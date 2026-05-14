<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para la actualización de un documento de biblioteca.
 *
 * Todos los campos son opcionales (sometimes); si se adjunta un nuevo
 * archivo, el controlador eliminará el archivo anterior del disco.
 *
 * @package App\Http\Requests\Api\Academico
 */
class UpdateBibliotecaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre'              => ['sometimes', 'string', 'max:255'],
            'fecha_carga'         => ['sometimes', 'date'],
            'fecha_obsolescencia' => ['nullable', 'date', 'after_or_equal:fecha_carga'],
            'archivo'             => ['sometimes', 'nullable', 'file', 'max:51200'],
            'cursos'              => ['sometimes', 'array'],
            'cursos.*'            => ['integer', 'exists:cursos,id'],
            'status'              => ['sometimes', 'integer', 'in:0,1'],
        ];
    }

    /**
     * Mensajes de error personalizados para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.max'                         => 'El nombre no puede superar los 255 caracteres.',
            'fecha_carga.date'                   => 'La fecha de carga debe ser una fecha válida.',
            'fecha_obsolescencia.date'           => 'La fecha de obsolescencia debe ser una fecha válida.',
            'fecha_obsolescencia.after_or_equal' => 'La fecha de obsolescencia debe ser igual o posterior a la fecha de carga.',
            'archivo.file'                       => 'El campo archivo debe ser un fichero válido.',
            'archivo.max'                        => 'El archivo no puede superar los 50 MB.',
            'cursos.array'                       => 'Los cursos deben enviarse como un arreglo.',
            'cursos.*.integer'                   => 'Cada curso debe ser un identificador numérico.',
            'cursos.*.exists'                    => 'Uno o más cursos no existen en el sistema.',
            'status.in'                          => 'El status debe ser 0 (inactivo) o 1 (activo).',
        ];
    }
}
