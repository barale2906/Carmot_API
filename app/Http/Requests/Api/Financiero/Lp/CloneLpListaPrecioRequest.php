<?php

namespace App\Http\Requests\Api\Financiero\Lp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

/**
 * Request CloneLpListaPrecioRequest
 *
 * Valida los datos para clonar una lista de precios existente.
 * La clonación crea una nueva lista con las mismas poblaciones y precios de
 * la lista origen, lista para editar y aprobar en el nuevo período.
 *
 * Campos requeridos: nombre, fecha_inicio, fecha_fin.
 * Campos opcionales:
 *   - descripcion  → si se omite, se hereda de la lista origen.
 *   - poblaciones  → array de IDs de poblacion; si se omite, se heredan de la lista origen.
 *   - copiar_precios → bool (default true); si false, crea la lista vacía sin precios.
 *
 * @package App\Http\Requests\Api\Financiero\Lp
 */
class CloneLpListaPrecioRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     * La autorización se maneja mediante middleware y permisos.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre'        => 'required|string|max:255',
            'fecha_inicio'  => 'required|date|after_or_equal:today',
            'fecha_fin'     => 'required|date|after:fecha_inicio',
            'descripcion'   => 'sometimes|nullable|string',
            'poblaciones'   => 'sometimes|array',
            'poblaciones.*' => 'integer|exists:poblacions,id',
            'copiar_precios' => 'sometimes|boolean',
        ];
    }

    /**
     * Mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required'           => 'El nombre de la nueva lista es obligatorio.',
            'nombre.max'                => 'El nombre no puede exceder 255 caracteres.',
            'fecha_inicio.required'     => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date'         => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio debe ser hoy o una fecha futura.',
            'fecha_fin.required'        => 'La fecha de fin es obligatoria.',
            'fecha_fin.date'            => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after'           => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'poblaciones.array'         => 'Las poblaciones deben ser un array de IDs.',
            'poblaciones.*.integer'     => 'Cada población debe ser un número entero.',
            'poblaciones.*.exists'      => 'Una de las poblaciones indicadas no existe.',
        ];
    }
}
