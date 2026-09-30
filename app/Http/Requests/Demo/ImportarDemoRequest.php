<?php

namespace App\Http\Requests\Demo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportarDemoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     * El acceso ya lo controla el middleware VerificarTokenDemo.
     *
     * @return bool
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
            'archivo' => ['required', 'file', 'max:102400'],
        ];
    }

    /**
     * Valida la extensión .sql por el nombre original: el tipo MIME de un .sql
     * varía según el navegador/cliente (text/plain, application/sql, ...).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $archivo = $this->file('archivo');

                if ($archivo && strtolower($archivo->getClientOriginalExtension()) !== 'sql') {
                    $validator->errors()->add('archivo', 'El archivo debe tener extensión .sql.');
                }
            },
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
            'archivo.required' => 'El archivo .sql es obligatorio.',
            'archivo.file'     => 'El campo archivo debe ser un archivo válido.',
            'archivo.max'      => 'El archivo no puede exceder 100 MB.',
        ];
    }
}
