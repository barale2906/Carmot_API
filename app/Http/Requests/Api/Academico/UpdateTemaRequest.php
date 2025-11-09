<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request para validar la actualización de un tema existente.
 *
 * Este request valida todos los campos necesarios para actualizar un tema,
 * incluyendo nombre, descripción, duración, estado y tópicos asociados.
 */
class UpdateTemaRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     *
     * @return bool
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
        $temaId = $this->route('tema')->id;

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('temas', 'nombre')->ignore($temaId)
            ],
            'descripcion' => 'required|string|max:1000',
            'duracion' => 'required|numeric|min:0.1|max:999',
            'status' => self::getStatusValidationRule(),
            'topico_ids' => 'sometimes|array',
            'topico_ids.*' => 'integer|exists:topicos,id',
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
            'nombre.required' => 'El nombre del tema es obligatorio.',
            'nombre.string' => 'El nombre del tema debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del tema no puede tener más de 255 caracteres.',
            'nombre.unique' => 'Ya existe un tema con este nombre.',
            'descripcion.required' => 'La descripción del tema es obligatoria.',
            'descripcion.string' => 'La descripción del tema debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción del tema no puede tener más de 1000 caracteres.',
            'duracion.required' => 'La duración del tema es obligatoria.',
            'duracion.numeric' => 'La duración del tema debe ser un número.',
            'duracion.min' => 'La duración del tema debe ser al menos 0.1 horas.',
            'duracion.max' => 'La duración del tema no puede ser mayor a 999 horas.',
            'topico_ids.array' => 'Los tópicos deben ser un array.',
            'topico_ids.*.integer' => 'Cada tópico debe ser un número entero.',
            'topico_ids.*.exists' => 'Uno o más tópicos seleccionados no existen.',
        ], self::getStatusValidationMessages());
    }
}
