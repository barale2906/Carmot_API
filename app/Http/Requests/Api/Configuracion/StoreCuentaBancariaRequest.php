<?php

namespace App\Http\Requests\Api\Configuracion;

use App\Models\Configuracion\CuentaBancaria;
use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validación para crear una cuenta bancaria del instituto.
 */
class StoreCuentaBancariaRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    /**
     * La autorización se resuelve con el middleware de permisos de la ruta.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para crear una cuenta bancaria. La cuenta aplica a las sedes
     * indicadas y/o a todas las sedes de una ciudad. El número de cuenta no se repite
     * dentro del mismo banco (entre cuentas no eliminadas).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sedes' => ['nullable', 'array'],
            'sedes.*' => ['integer', 'distinct', Rule::exists('sedes', 'id')->whereNull('deleted_at')],
            'poblacion_id' => ['nullable', 'integer', Rule::exists('poblacions', 'id')],
            'banco_id' => ['required', 'integer', Rule::exists('bancos', 'id')->whereNull('deleted_at')],
            'tipo_cuenta' => ['required', 'string', Rule::in(array_keys(CuentaBancaria::TIPOS))],
            'numero_cuenta' => ['required', 'string', 'max:50',
                Rule::unique('cuentas_bancarias', 'numero_cuenta')
                    ->where('banco_id', $this->input('banco_id'))
                    ->whereNull('deleted_at')],
            'titular' => ['nullable', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'status' => self::getStatusValidationRule(),
        ];
    }

    /**
     * La cuenta debe quedar asociada al menos a una sede o a una ciudad.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $sedes = $this->input('sedes', []);
            $poblacion = $this->input('poblacion_id');

            if (empty($sedes) && empty($poblacion)) {
                $v->errors()->add('sedes', 'Indique al menos una sede o una ciudad donde se reciben los pagos en esta cuenta.');
            }
        });
    }

    /**
     * Mensajes de validación en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'sedes.array' => 'Las sedes deben enviarse como una lista.',
            'sedes.*.exists' => 'Una de las sedes seleccionadas no existe.',
            'sedes.*.distinct' => 'Hay sedes repetidas.',
            'poblacion_id.exists' => 'La ciudad seleccionada no existe.',
            'banco_id.required' => 'El banco es obligatorio.',
            'banco_id.exists' => 'El banco seleccionado no existe.',
            'tipo_cuenta.required' => 'El tipo de cuenta es obligatorio.',
            'tipo_cuenta.in' => 'El tipo de cuenta no es válido.',
            'numero_cuenta.required' => 'El número de cuenta es obligatorio.',
            'numero_cuenta.max' => 'El número de cuenta no puede exceder los 50 caracteres.',
            'numero_cuenta.unique' => 'Ya existe una cuenta con este número en el banco seleccionado.',
            'titular.max' => 'El titular no puede exceder los 200 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder los 255 caracteres.',
        ], self::getStatusValidationMessages());
    }

    /**
     * Nombres de los atributos en español.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sedes' => 'sedes',
            'poblacion_id' => 'ciudad',
            'banco_id' => 'banco',
            'tipo_cuenta' => 'tipo de cuenta',
            'numero_cuenta' => 'número de cuenta',
            'titular' => 'titular',
            'descripcion' => 'descripción',
            'status' => 'estado',
        ];
    }
}
