<?php

namespace App\Http\Requests\Api\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSedeRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $sedeId = $this->route('sede')->id;

        return [
            'nombre' => ['sometimes', 'string', 'max:255'],
            'direccion' => ['sometimes', 'string', 'max:500'],
            'telefono' => ['sometimes', 'string', 'max:20'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('sedes', 'email')->ignore($sedeId)
            ],
            'hora_inicio' => ['sometimes', 'date_format:H:i:s'],
            'hora_fin' => ['sometimes', 'date_format:H:i:s', 'after:hora_inicio'],
            'poblacion_id' => ['sometimes', 'integer', 'exists:poblacions,id'],
            'areas' => ['sometimes', 'array'],
            'areas.*' => ['integer', 'exists:areas,id'],
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
            'nombre.max' => 'El nombre de la sede no puede exceder los 255 caracteres.',
            'direccion.max' => 'La dirección no puede exceder los 500 caracteres.',
            'telefono.max' => 'El teléfono no puede exceder los 20 caracteres.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Este email ya está registrado.',
            'email.max' => 'El email no puede exceder los 255 caracteres.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM:SS.',
            'hora_fin.date_format' => 'La hora de fin debe tener el formato HH:MM:SS.',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
            'poblacion_id.exists' => 'La población seleccionada no existe.',
            'areas.array' => 'Las áreas deben ser un array.',
            'areas.*.integer' => 'Cada área debe ser un número entero.',
            'areas.*.exists' => 'Una o más áreas seleccionadas no existen.',
        ];
    }

    /**
     * Obtiene los atributos personalizados para los mensajes de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de la sede',
            'direccion' => 'dirección',
            'telefono' => 'teléfono',
            'email' => 'email',
            'hora_inicio' => 'hora de inicio',
            'hora_fin' => 'hora de fin',
            'poblacion_id' => 'población',
            'areas' => 'áreas',
        ];
    }
}
