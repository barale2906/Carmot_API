<?php

namespace App\Http\Requests\Api\Inventarios;

use App\Traits\Financiero\HasListaPrecioStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para actualizar una lista de precios del módulo de inventarios.
 *
 * Solo permite modificar datos descriptivos y vigencia. El status se cambia
 * exclusivamente via los endpoints aprobar/activar/inactivar.
 */
class UpdateInvListaPrecioRequest extends FormRequest
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
            'nombre'        => ['sometimes', 'required', 'string', 'max:255'],
            'fecha_inicio'  => ['sometimes', 'required', 'date'],
            'fecha_fin'     => ['sometimes', 'required', 'date', 'after_or_equal:fecha_inicio'],
            'descripcion'   => ['nullable', 'string'],
            'poblaciones'   => ['sometimes', 'array', 'min:1'],
            'poblaciones.*' => ['integer', 'exists:poblacions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required'          => 'El nombre de la lista es obligatorio.',
            'nombre.max'               => 'El nombre no puede exceder 255 caracteres.',
            'fecha_inicio.date'        => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.date'           => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'poblaciones.min'          => 'Debe especificar al menos una población.',
            'poblaciones.*.exists'     => 'Una o más poblaciones no existen.',
        ];
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
