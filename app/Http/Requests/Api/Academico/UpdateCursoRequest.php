<?php

namespace App\Http\Requests\Api\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use App\Traits\HasTipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCursoRequest extends FormRequest
{
    use HasTipo, HasActiveStatus, HasActiveStatusValidation;
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
        $cursoId = $this->route('curso')->id;

        return [
            'nombre' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('cursos', 'nombre')->ignore($cursoId)
            ],
            'duracion' => 'sometimes|numeric|min:0',
            'tipo' => 'sometimes|integer|' . self::getTipoValidationRule(),
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
            'nombre.string' => 'El nombre del curso debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del curso no puede tener más de 255 caracteres.',
            'nombre.unique' => 'Ya existe un curso con este nombre.',
            'duracion.numeric' => 'La duración del curso debe ser un número.',
            'duracion.min' => 'La duración del curso debe ser mayor o igual a 0.',
            'modulo_ids.array' => 'Los módulos deben ser un array.',
            'modulo_ids.*.integer' => 'Cada módulo debe ser un número entero.',
            'modulo_ids.*.exists' => 'Uno o más módulos seleccionados no existen.',
            'tipo.integer' => 'El tipo debe ser un número entero.',
            'tipo.in' => 'El tipo debe ser uno de los valores válidos: ' . implode(', ', array_map(function($key, $value) { return "$key ($value)"; }, array_keys(self::getTipoOptions()), self::getTipoOptions())) . '.',
        ], self::getStatusValidationMessages());
    }
}
