<?php

namespace App\Http\Requests\Api\Activos;

use App\Models\Activos\ActActivo;
use App\Services\ArchivoService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Carga o reemplazo de la imagen del activo (multipart/form-data).
 */
class ImagenActActivoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta petición.
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'imagen' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . ArchivoService::MAX_KB],
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
            'imagen.required' => 'Selecciona la imagen del activo.',
            'imagen.image'    => 'El archivo debe ser una imagen.',
            'imagen.mimes'    => 'La imagen debe ser JPG, PNG o WEBP.',
            'imagen.max'      => 'La imagen no puede superar 10 MB.',
            'imagen.uploaded' => 'No se recibió el archivo: puede superar el tamaño que permite el servidor.',
        ];
    }

    /**
     * Un activo dado de baja ya no se modifica.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var ActActivo $activo */
            $activo = $this->route('activo');

            if ($activo->estaDeBaja()) {
                $validator->errors()->add('imagen', 'El activo está dado de baja: no se puede modificar.');
            }
        });
    }
}
