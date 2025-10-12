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
            'duracion' => 'required|integer|min:1|max:999',
            'status' => self::getStatusValidationRule(),
            'modulo_ids' => 'sometimes|array',
            'modulo_ids.*' => 'integer|exists:modulos,id',
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
            'duracion.required' => 'La duración del tópico es obligatoria.',
            'duracion.integer' => 'La duración del tópico debe ser un número entero.',
            'duracion.min' => 'La duración del tópico debe ser al menos 1 minuto.',
            'duracion.max' => 'La duración del tópico no puede ser mayor a 999 minutos.',
            'modulo_ids.array' => 'Los módulos deben ser un array.',
            'modulo_ids.*.integer' => 'Cada módulo debe ser un número entero.',
            'modulo_ids.*.exists' => 'Uno o más módulos seleccionados no existen.',
        ], self::getStatusValidationMessages());
    }
}
