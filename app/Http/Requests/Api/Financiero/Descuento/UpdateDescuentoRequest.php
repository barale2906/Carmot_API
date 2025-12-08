<?php

namespace App\Http\Requests\Api\Financiero\Descuento;

use App\Models\Financiero\Descuento\Descuento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request UpdateDescuentoRequest
 *
 * Valida los datos para actualizar un descuento existente en el sistema financiero.
 * Similar a StoreDescuentoRequest pero con reglas `sometimes` para permitir actualizaciones parciales.
 *
 * @package App\Http\Requests\Api\Financiero\Descuento
 */
class UpdateDescuentoRequest extends FormRequest
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
        $descuentoId = $this->route('descuento')?->id ?? $this->route('id');

        return [
            'nombre' => 'sometimes|string|max:255',
            'codigo_descuento' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('descuentos', 'codigo_descuento')->ignore($descuentoId),
                Rule::requiredIf(function () {
                    return $this->input('tipo_activacion') === Descuento::ACTIVACION_CODIGO_PROMOCIONAL;
                }),
            ],
            'descripcion' => 'nullable|string',
            'tipo' => ['sometimes', Rule::in([Descuento::TIPO_PORCENTUAL, Descuento::TIPO_VALOR_FIJO])],
            'valor' => 'sometimes|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
            'aplicacion' => [
                'sometimes',
                Rule::in([
                    Descuento::APLICACION_VALOR_TOTAL,
                    Descuento::APLICACION_MATRICULA,
                    Descuento::APLICACION_CUOTA
                ])
            ],
            'tipo_activacion' => [
                'sometimes',
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
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => [
                'sometimes',
                'date',
                Rule::requiredIf($this->has('fecha_inicio')),
                function ($attribute, $value, $fail) {
                    if ($this->has('fecha_inicio') && $value < $this->input('fecha_inicio')) {
                        $fail('La fecha de fin debe ser igual o posterior a la fecha de inicio.');
                    }
                },
            ],
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
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'codigo_descuento.string' => 'El código de descuento debe ser una cadena de texto.',
            'codigo_descuento.max' => 'El código de descuento no puede exceder 50 caracteres.',
            'codigo_descuento.unique' => 'El código de descuento ya está en uso.',
            'codigo_descuento.required' => 'El código de descuento es obligatorio cuando el tipo de activación es código promocional.',
            'tipo.in' => 'El tipo de descuento debe ser: porcentual o valor_fijo.',
            'valor.numeric' => 'El valor debe ser un número.',
            'valor.min' => 'El valor no puede ser negativo.',
            'valor.regex' => 'El valor debe tener máximo 2 decimales.',
            'aplicacion.in' => 'La aplicación debe ser: valor_total, matricula o cuota.',
            'tipo_activacion.in' => 'El tipo de activación debe ser: pago_anticipado, promocion_matricula o codigo_promocional.',
            'dias_anticipacion.required' => 'Los días de anticipación son obligatorios cuando el tipo de activación es pago anticipado.',
            'dias_anticipacion.integer' => 'Los días de anticipación deben ser un número entero.',
            'dias_anticipacion.min' => 'Los días de anticipación deben ser al menos 1.',
            'permite_acumulacion.boolean' => 'El campo permite acumulación debe ser verdadero o falso.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria cuando se proporciona fecha de inicio.',
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

