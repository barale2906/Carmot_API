<?php

namespace App\Http\Requests\Api\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdImpuesto;
use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la actualización de un impuesto o retención del libro diario.
 */
class UpdateLdImpuestoRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    /**
     * La autorización se resuelve con el middleware de permisos.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:120', Rule::unique('ld_impuestos', 'nombre')->ignore($this->route('ldImpuesto'))->whereNull('deleted_at')],
            'porcentaje' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'efecto' => ['sometimes', 'required', 'integer', Rule::in(array_keys(LdImpuesto::getEfectoOptions()))],
            'status' => self::getStatusValidationRule(),
        ];
    }

    /**
     * Mensajes de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'nombre.required' => 'El nombre del impuesto es obligatorio.',
            'nombre.unique' => 'Ya existe un impuesto con este nombre.',
            'porcentaje.required' => 'El porcentaje es obligatorio.',
            'porcentaje.max' => 'El porcentaje no puede superar 100.',
            'efecto.required' => 'Indique si el impuesto suma o resta del total.',
            'efecto.in' => 'El efecto seleccionado no es válido.',
        ], self::getStatusValidationMessages());
    }

    /**
     * Nombres de los atributos.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'porcentaje' => 'porcentaje',
            'efecto' => 'efecto',
            'status' => 'estado',
        ];
    }
}
