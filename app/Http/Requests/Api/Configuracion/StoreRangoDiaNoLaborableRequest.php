<?php

namespace App\Http\Requests\Api\Configuracion;

use App\Models\Configuracion\DiaNoLaborable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRangoDiaNoLaborableRequest extends FormRequest
{
    /** Máximo de días que se pueden registrar en una sola operación. */
    public const MAX_DIAS = 366;

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
     * Registra un día no laborable por cada fecha del rango (ej. vacaciones colectivas).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha_inicio'  => ['required', 'date_format:Y-m-d'],
            'fecha_fin'     => [
                'required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio',
                'before_or_equal:' . $this->fechaMaxima(),
            ],
            'nombre'        => ['required', 'string', 'max:255'],
            'tipo'          => ['required', Rule::in(array_keys(DiaNoLaborable::TIPOS))],
            'sede_id'       => ['nullable', 'integer', 'exists:sedes,id'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
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
            'fecha_inicio.required'     => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date_format'  => 'La fecha de inicio debe tener el formato AAAA-MM-DD.',
            'fecha_fin.required'        => 'La fecha de fin es obligatoria.',
            'fecha_fin.date_format'     => 'La fecha de fin debe tener el formato AAAA-MM-DD.',
            'fecha_fin.after_or_equal'  => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'fecha_fin.before_or_equal' => 'El rango no puede superar ' . self::MAX_DIAS . ' días.',
            'nombre.required'           => 'El nombre es obligatorio.',
            'tipo.required'             => 'El tipo es obligatorio.',
            'tipo.in'                   => 'El tipo debe ser: ' . implode(', ', array_keys(DiaNoLaborable::TIPOS)) . '.',
            'sede_id.exists'            => 'La sede seleccionada no existe.',
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
            'fecha_inicio'  => 'fecha de inicio',
            'fecha_fin'     => 'fecha de fin',
            'nombre'        => 'nombre',
            'tipo'          => 'tipo',
            'sede_id'       => 'sede',
            'observaciones' => 'observaciones',
        ];
    }

    /**
     * Última fecha admitida según fecha_inicio y MAX_DIAS.
     */
    private function fechaMaxima(): string
    {
        $inicio = \DateTime::createFromFormat('Y-m-d', (string) $this->input('fecha_inicio'));

        return $inicio
            ? $inicio->modify('+' . (self::MAX_DIAS - 1) . ' days')->format('Y-m-d')
            : '9999-12-31';
    }
}
