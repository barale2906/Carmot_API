<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;

class FiltrarReposicionesRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Filtros del listado de reposiciones. Las fechas son las de la clase de destino.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'estudiante_id' => ['nullable', 'integer'],
            'grupo_id' => ['nullable', 'integer'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Mensajes de error en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_desde.date' => 'La fecha desde no es válida.',
            'fecha_hasta.date' => 'La fecha hasta no es válida.',
            'fecha_hasta.after_or_equal' => 'La fecha hasta no puede ser anterior a la fecha desde.',
            'per_page.max' => 'No se pueden pedir más de 100 registros por página.',
        ];
    }
}
