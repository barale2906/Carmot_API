<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;

class StoreBibliotecaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'              => ['required', 'string', 'max:255'],
            'fecha_carga'         => ['required', 'date'],
            'fecha_obsolescencia' => ['nullable', 'date', 'after_or_equal:fecha_carga'],
            'archivo'             => ['required', 'file', 'max:51200'], // máx. 50 MB
            'cursos'              => ['sometimes', 'array'],
            'cursos.*'            => ['integer', 'exists:cursos,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'              => 'El nombre del documento es obligatorio.',
            'nombre.max'                   => 'El nombre no puede superar los 255 caracteres.',
            'fecha_carga.required'         => 'La fecha de carga es obligatoria.',
            'fecha_carga.date'             => 'La fecha de carga debe ser una fecha válida.',
            'fecha_obsolescencia.date'     => 'La fecha de obsolescencia debe ser una fecha válida.',
            'fecha_obsolescencia.after_or_equal' => 'La fecha de obsolescencia debe ser igual o posterior a la fecha de carga.',
            'archivo.required'             => 'El archivo es obligatorio.',
            'archivo.file'                 => 'El campo archivo debe ser un fichero válido.',
            'archivo.max'                  => 'El archivo no puede superar los 50 MB.',
            'cursos.array'                 => 'Los cursos deben enviarse como un arreglo.',
            'cursos.*.integer'             => 'Cada curso debe ser un identificador numérico.',
            'cursos.*.exists'              => 'Uno o más cursos no existen en el sistema.',
        ];
    }
}
