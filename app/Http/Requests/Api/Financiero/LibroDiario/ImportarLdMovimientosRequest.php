<?php

namespace App\Http\Requests\Api\Financiero\LibroDiario;

use App\Services\Financiero\LibroDiario\LdTurnoCajaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Valida el archivo de carga masiva de movimientos del libro diario.
 *
 * El contenido de cada fila lo valida LdMovimientoImportacionService con las reglas
 * del registro individual; aquí solo el archivo y que quien carga tenga turno abierto.
 */
class ImportarLdMovimientosRequest extends FormRequest
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
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ];
    }

    /**
     * Exige un turno de caja abierto de quien carga.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! app(LdTurnoCajaService::class)->turnoAbiertoDe($this->user()->id)) {
                $v->errors()->add('turno', 'Debe abrir un turno de caja antes de registrar movimientos.');
            }
        });
    }

    /**
     * Mensajes de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'El archivo es obligatorio.',
            'archivo.file' => 'El archivo no es válido.',
            'archivo.mimes' => 'El archivo debe ser Excel (.xlsx, .xls) o CSV.',
            'archivo.max' => 'El archivo no puede exceder 5 MB.',
        ];
    }

    /**
     * Nombres de los atributos.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'archivo' => 'archivo',
        ];
    }
}
