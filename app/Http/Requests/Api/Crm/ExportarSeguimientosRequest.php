<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\Crm\Referido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportarSeguimientosRequest extends FormRequest
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
     * Obtiene las reglas de validación: el informe siempre es de un lapso de fechas; los
     * filtros del lead son los mismos de la bitácora.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha_desde' => ['required', 'date'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'integer', Rule::in(array_keys(Referido::getStatusOptions()))],
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
            'fecha_desde.required' => 'Indique la fecha inicial del lapso a descargar.',
            'fecha_hasta.required' => 'Indique la fecha final del lapso a descargar.',
            'fecha_hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
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
            'fecha_desde' => 'fecha inicial',
            'fecha_hasta' => 'fecha final',
            'search' => 'búsqueda',
            'status' => 'estado del lead',
        ];
    }
}
