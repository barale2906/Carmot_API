<?php

namespace App\Http\Requests\Api\Traslados;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta de los ciclos y precios disponibles en la sede y el curso de destino de un traslado.
 */
class OpcionesTrasladoRequest extends FormRequest
{
    /**
     * La autorización se hace con el middleware de permisos de la ruta.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación de la petición.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'matricula_id' => ['required', 'integer', 'exists:matriculas,id'],
            'sede_id' => ['required', 'integer', 'exists:sedes,id'],
            'curso_id' => ['required', 'integer', 'exists:cursos,id'],
        ];
    }

    /**
     * Nombres de los campos para los mensajes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'matricula_id' => 'matrícula',
            'sede_id' => 'sede',
            'curso_id' => 'curso',
        ];
    }
}
