<?php

namespace App\Http\Requests\Api\Inventarios;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para clonar una lista de precios de inventario.
 */
class CloneInvListaPrecioRequest extends FormRequest
{
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
            'poblaciones'   => ['sometimes', 'array', 'min:1'],
            'poblaciones.*' => ['integer', 'exists:poblacions,id'],
            'copiar_precios' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required'          => 'El nombre de la nueva lista es obligatorio.',
            'nombre.max'               => 'El nombre no puede exceder 255 caracteres.',
            'fecha_inicio.required'    => 'La fecha de inicio es obligatoria.',
            'fecha_fin.required'       => 'La fecha de fin es obligatoria.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'poblaciones.*.exists'     => 'Una o más poblaciones no existen.',
        ];
    }
}
