<?php

namespace App\Http\Requests\Api\Financiero\Descuento;

use App\Models\Financiero\Descuento\Descuento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request StoreDescuentoRequest
 *
 * Valida los datos para crear un nuevo descuento en el sistema financiero.
 * Incluye validación de campos requeridos, tipos de descuento, aplicación,
 * condiciones de activación y relaciones many-to-many.
 *
 * @package App\Http\Requests\Api\Financiero\Descuento
 */
class StoreDescuentoRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'codigo_descuento' => [
                'nullable',
                'string',
                'max:50',
                'unique:descuentos,codigo_descuento',
                Rule::requiredIf(function () {
                    return $this->input('tipo_activacion') === Descuento::ACTIVACION_CODIGO_PROMOCIONAL;
                }),
            ],
            'descripcion' => 'nullable|string',
            'tipo' => ['required', Rule::in([Descuento::TIPO_PORCENTUAL, Descuento::TIPO_VALOR_FIJO])],
            'valor' => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
            'aplicacion' => [
                'required',
                Rule::in([
                    Descuento::APLICACION_VALOR_TOTAL,
                    Descuento::APLICACION_MATRICULA,
                    Descuento::APLICACION_CUOTA
                ])
            ],
            'tipo_activacion' => [
                'required',
                Rule::in([
                    Descuento::ACTIVACION_PAGO_ANTICIPADO,
                    Descuento::ACTIVACION_PROMOCION_MATRICULA,
                    Descuento::ACTIVACION_CODIGO_PROMOCIONAL
                ])
            ],
            'dias_anticipacion' => [
                Rule::requiredIf($this->input('tipo_activacion') === Descuento::ACTIVACION_PAGO_ANTICIPADO),
                'nullable',
                'integer',
                'min:1'
            ],
            'permite_acumulacion' => 'sometimes|boolean',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'status' => Descuento::getStatusValidationRule(),
            'listas_precios' => 'nullable|array',
            'listas_precios.*' => 'exists:lp_listas_precios,id',
            'productos' => 'nullable|array',
            'productos.*' => 'exists:lp_productos,id',
            'sedes' => 'nullable|array',
            'sedes.*' => 'exists:sedes,id',
            'poblaciones' => 'nullable|array',
            'poblaciones.*' => 'exists:poblacions,id',
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
            'nombre.required' => 'El nombre del descuento es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'codigo_descuento.string' => 'El código de descuento debe ser una cadena de texto.',
            'codigo_descuento.max' => 'El código de descuento no puede exceder 50 caracteres.',
            'codigo_descuento.unique' => 'El código de descuento ya está en uso.',
            'codigo_descuento.required' => 'El código de descuento es obligatorio cuando el tipo de activación es código promocional.',
            'tipo.required' => 'El tipo de descuento es obligatorio.',
            'tipo.in' => 'El tipo de descuento debe ser: porcentual o valor_fijo.',
            'valor.required' => 'El valor del descuento es obligatorio.',
            'valor.numeric' => 'El valor debe ser un número.',
            'valor.min' => 'El valor no puede ser negativo.',
            'valor.regex' => 'El valor debe tener máximo 2 decimales.',
            'aplicacion.required' => 'La aplicación del descuento es obligatoria.',
            'aplicacion.in' => 'La aplicación debe ser: valor_total, matricula o cuota.',
            'tipo_activacion.required' => 'El tipo de activación es obligatorio.',
            'tipo_activacion.in' => 'El tipo de activación debe ser: pago_anticipado, promocion_matricula o codigo_promocional.',
            'dias_anticipacion.required' => 'Los días de anticipación son obligatorios cuando el tipo de activación es pago anticipado.',
            'dias_anticipacion.integer' => 'Los días de anticipación deben ser un número entero.',
            'dias_anticipacion.min' => 'Los días de anticipación deben ser al menos 1.',
            'permite_acumulacion.boolean' => 'El campo permite acumulación debe ser verdadero o falso.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'status.integer' => 'El estado debe ser un número entero.',
            'status.in' => Descuento::getStatusValidationMessages()['status.in'] ?? 'El estado no es válido.',
            'listas_precios.array' => 'Las listas de precios deben ser un array.',
            'listas_precios.*.exists' => 'Una o más listas de precios seleccionadas no existen.',
            'productos.array' => 'Los productos deben ser un array.',
            'productos.*.exists' => 'Uno o más productos seleccionados no existen.',
            'sedes.array' => 'Las sedes deben ser un array.',
            'sedes.*.exists' => 'Una o más sedes seleccionadas no existen.',
            'poblaciones.array' => 'Las poblaciones deben ser un array.',
            'poblaciones.*.exists' => 'Una o más poblaciones seleccionadas no existen.',
        ];
    }
}

