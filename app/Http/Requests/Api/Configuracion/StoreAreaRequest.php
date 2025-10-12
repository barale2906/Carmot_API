<?php

namespace App\Http\Requests\Api\Configuracion;

use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreAreaRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'status' => self::getStatusValidationRule(),
            'sedes' => ['sometimes', 'array'],
            'sedes.*' => ['integer', 'exists:sedes,id'],
        ];
    }

    /**
     * Obtiene los mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'nombre.required' => 'El nombre del área es obligatorio.',
            'nombre.max' => 'El nombre del área no puede exceder los 255 caracteres.',
            'sedes.array' => 'Las sedes deben ser un arreglo.',
            'sedes.*.integer' => 'Cada sede debe ser un número entero.',
            'sedes.*.exists' => 'Una o más sedes seleccionadas no existen.',
        ], self::getStatusValidationMessages());
    }

    /**
     * Obtiene los atributos personalizados para los mensajes de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre del área',
            'status' => 'estado',
            'sedes' => 'sedes',
        ];
    }
}
