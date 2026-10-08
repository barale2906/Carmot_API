<?php

namespace App\Http\Requests\Api\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class GenerarFestivosRequest extends FormRequest
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
     * sede_id NULL o ausente = los festivos aplican a todas las sedes.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'anio'    => ['required', 'integer', 'between:2000,2100'],
            'sede_id' => ['nullable', 'integer', 'exists:sedes,id'],
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
            'anio.required'  => 'El año es obligatorio.',
            'anio.integer'   => 'El año debe ser un número entero.',
            'anio.between'   => 'El año debe estar entre 2000 y 2100.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
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
            'anio'    => 'año',
            'sede_id' => 'sede',
        ];
    }
}
