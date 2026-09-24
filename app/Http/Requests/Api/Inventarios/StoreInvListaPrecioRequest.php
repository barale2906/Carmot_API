<?php

namespace App\Http\Requests\Api\Inventarios;

use App\Traits\Financiero\HasListaPrecioStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para crear una lista de precios del módulo de inventarios.
 *
 * El campo origen=0 (inventarios) se fuerza en el controlador; no es parte del request.
 */
class StoreInvListaPrecioRequest extends FormRequest
{
    use HasListaPrecioStatus;

    /**
     * La autorización se maneja via middleware de permisos en el controlador.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre'        => ['required', 'string', 'max:255'],
            'fecha_inicio'  => ['required', 'date'],
            'fecha_fin'     => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'descripcion'   => ['nullable', 'string'],
            'status'        => [self::getStatusValidationRule()],
            'poblaciones'   => ['required', 'array', 'min:1'],
            'poblaciones.*' => ['required', 'integer', 'exists:poblacions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'nombre.required'       => 'El nombre de la lista es obligatorio.',
            'nombre.max'            => 'El nombre no puede exceder 255 caracteres.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date'     => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.required'    => 'La fecha de fin es obligatoria.',
            'fecha_fin.date'        => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'poblaciones.required'  => 'Debe especificar al menos una población.',
            'poblaciones.array'     => 'Las poblaciones deben ser un arreglo.',
            'poblaciones.min'       => 'Debe especificar al menos una población.',
            'poblaciones.*.exists'  => 'Una o más poblaciones no existen.',
        ], self::getStatusValidationMessages());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre'       => 'nombre',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin'    => 'fecha de fin',
            'descripcion'  => 'descripción',
            'poblaciones'  => 'poblaciones',
        ];
    }
}
