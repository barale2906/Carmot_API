<?php

namespace App\Http\Requests\Api\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdSoporte;
use App\Services\ArchivoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida la carga de un archivo de soporte (factura, recibo, comprobante)
 * para un movimiento o una consignación del libro diario.
 */
class StoreLdSoporteRequest extends FormRequest
{
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
            'soportable_tipo' => ['required', 'string', Rule::in(array_keys(LdSoporte::SOPORTABLES))],
            'soportable_id' => ['required', 'integer'],
            'tipo' => ['required', 'string', Rule::in(array_keys(LdSoporte::TIPOS))],
            'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.ArchivoService::MAX_KB],
        ];
    }

    /**
     * El registro al que se asocia el soporte debe existir.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $v->errors()->hasAny(['soportable_tipo', 'soportable_id']) && ! $this->soportable()) {
                $v->errors()->add('soportable_id', 'El registro al que se asocia el soporte no existe.');
            }
        });
    }

    /**
     * Registro (movimiento o consignación) al que se asocia el soporte.
     */
    public function soportable(): ?Model
    {
        $clase = LdSoporte::SOPORTABLES[$this->soportable_tipo];

        return $clase::find($this->soportable_id);
    }

    /**
     * Mensajes de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'soportable_tipo.in' => 'El tipo de registro no es válido.',
            'tipo.required' => 'El tipo de soporte es obligatorio.',
            'tipo.in' => 'El tipo de soporte no es válido.',
            'archivo.required' => 'El archivo es obligatorio.',
            'archivo.mimes' => 'El archivo debe ser PDF o imagen (JPG, PNG, WEBP).',
            'archivo.max' => 'El archivo no puede superar los 10 MB.',
            'archivo.uploaded' => 'No se recibió el archivo: puede superar el tamaño que permite el servidor.',
        ];
    }
}
