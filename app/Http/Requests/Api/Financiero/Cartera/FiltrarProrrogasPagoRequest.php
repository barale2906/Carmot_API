<?php

namespace App\Http\Requests\Api\Financiero\Cartera;

use App\Models\Financiero\Cartera\ProrrogaPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrarProrrogasPagoRequest extends FormRequest
{
    /**
     * La autorización se resuelve por middleware de permisos en el controlador.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'estudiante_id' => ['nullable', 'integer', 'exists:users,id'],
            'estado' => ['nullable', Rule::in([ProrrogaPago::VIGENTE, ProrrogaPago::CUMPLIDO, ProrrogaPago::INCUMPLIDO])],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estudiante_id.exists' => 'El estudiante seleccionado no existe.',
            'estado.in' => 'El estado debe ser vigente, cumplido o incumplido.',
            'fecha_desde.date' => 'La fecha inicial no es válida.',
            'fecha_hasta.date' => 'La fecha final no es válida.',
            'fecha_hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ];
    }
}
