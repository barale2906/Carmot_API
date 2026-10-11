<?php

namespace App\Http\Requests\Api\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la creación de un tipo de movimiento del libro diario.
 */
class StoreLdTipoMovimientoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:120', Rule::unique('ld_tipos_movimiento', 'nombre')->whereNull('deleted_at')],
            'clase' => ['required', 'integer', Rule::in(array_keys(LdTipoMovimiento::getClaseOptions()))],
            'descripcion' => ['nullable', 'string', 'max:255'],
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
            'nombre.required' => 'El nombre del tipo de movimiento es obligatorio.',
            'nombre.unique' => 'Ya existe un tipo de movimiento con este nombre.',
            'clase.required' => 'La clase (egreso u otro ingreso) es obligatoria.',
            'clase.in' => 'La clase seleccionada no es válida.',
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
            'clase' => 'clase',
            'descripcion' => 'descripción',
            'status' => 'estado',
        ];
    }
}
