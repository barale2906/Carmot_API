<?php

namespace App\Http\Requests\Api\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrarLdTurnosCajaRequest extends FormRequest
{
    /**
     * Filtros del historial de turnos de caja (los mismos de LdTurnoCaja::scopeWithFilters).
     */
    private const FILTROS = ['sede_id', 'cajero_id', 'status', 'fecha_desde', 'fecha_hasta'];

    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación. Todos los filtros son opcionales; las fechas son
     * las del turno (día en que se abrió).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sede_id' => ['nullable', 'integer'],
            'cajero_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'integer', Rule::in(array_keys(LdTurnoCaja::getStatusOptions()))],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
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
            'fecha_hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'status.in' => 'El estado del turno no es válido.',
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
            'sede_id' => 'sede',
            'cajero_id' => 'cajero',
            'status' => 'estado',
            'fecha_desde' => 'fecha inicial',
            'fecha_hasta' => 'fecha final',
        ];
    }

    /**
     * Filtros validados listos para LdTurnoCaja::scopeWithFilters.
     *
     * @return array<string, mixed>
     */
    public function filtros(): array
    {
        return $this->only(self::FILTROS);
    }
}
