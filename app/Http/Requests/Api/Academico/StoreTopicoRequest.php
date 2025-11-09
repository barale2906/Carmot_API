<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreTopicoRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
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
            'nombre' => 'required|string|max:255|unique:topicos,nombre',
            'descripcion' => 'required|string|max:1000',
            'duracion' => 'sometimes|numeric|min:0|max:999',
            'status' => self::getStatusValidationRule(),
            'modulo_ids' => 'sometimes|array',
            'modulo_ids.*' => 'integer|exists:modulos,id',
            'tema_ids' => 'sometimes|array',
            'tema_ids.*' => 'integer|exists:temas,id',
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'nombre.required' => 'El nombre del tópico es obligatorio.',
            'nombre.string' => 'El nombre del tópico debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del tópico no puede tener más de 255 caracteres.',
            'nombre.unique' => 'Ya existe un tópico con este nombre.',
            'descripcion.required' => 'La descripción del tópico es obligatoria.',
            'descripcion.string' => 'La descripción del tópico debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción del tópico no puede tener más de 1000 caracteres.',
            'duracion.numeric' => 'La duración del tópico debe ser un número.',
            'duracion.min' => 'La duración del tópico debe ser al menos 0 horas.',
            'duracion.max' => 'La duración del tópico no puede ser mayor a 999 horas.',
            'modulo_ids.array' => 'Los módulos deben ser un array.',
            'modulo_ids.*.integer' => 'Cada módulo debe ser un número entero.',
            'modulo_ids.*.exists' => 'Uno o más módulos seleccionados no existen.',
            'tema_ids.array' => 'Los temas deben ser un array.',
            'tema_ids.*.integer' => 'Cada tema debe ser un número entero.',
            'tema_ids.*.exists' => 'Uno o más temas seleccionados no existen.',
        ], self::getStatusValidationMessages());
    }
}
