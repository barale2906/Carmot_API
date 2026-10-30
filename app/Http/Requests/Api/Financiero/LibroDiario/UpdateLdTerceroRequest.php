<?php

namespace App\Http\Requests\Api\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdTercero;
use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la actualización de un tercero del libro diario. Tipo + número de
 * identificación no se pueden repetir; los movimientos ya registrados conservan
 * la copia del tercero que tenían.
 */
class UpdateLdTerceroRequest extends FormRequest
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
        $tercero = $this->route('ldTercero');

        return [
            'tipo_identificacion' => ['sometimes', 'required', 'string', Rule::in(array_keys(LdTercero::TIPOS_IDENTIFICACION))],
            'identificacion' => [
                'sometimes', 'required', 'string', 'max:30',
                Rule::unique('ld_terceros', 'identificacion')
                    ->where('tipo_identificacion', $this->input('tipo_identificacion', $tercero->tipo_identificacion))
                    ->ignore($tercero),
            ],
            'nombre' => ['sometimes', 'required', 'string', 'max:200'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
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
            'tipo_identificacion.required' => 'El tipo de identificación es obligatorio.',
            'tipo_identificacion.in' => 'El tipo de identificación no es válido.',
            'identificacion.required' => 'El número de identificación es obligatorio.',
            'identificacion.unique' => 'Ya existe un tercero con este tipo y número de identificación.',
            'nombre.required' => 'El nombre del tercero es obligatorio.',
            'email.email' => 'El correo electrónico no es válido.',
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
            'tipo_identificacion' => 'tipo de identificación',
            'identificacion' => 'número de identificación',
            'nombre' => 'nombre',
            'telefono' => 'teléfono',
            'email' => 'correo electrónico',
            'direccion' => 'dirección',
            'status' => 'estado',
        ];
    }
}
