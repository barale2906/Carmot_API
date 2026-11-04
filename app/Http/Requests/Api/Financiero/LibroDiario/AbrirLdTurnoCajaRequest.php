<?php

namespace App\Http\Requests\Api\Financiero\LibroDiario;

use App\Services\Financiero\LibroDiario\LdTurnoCajaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Valida la apertura del turno de caja: sede asignada al cajero, base inicial
 * y que el cajero pueda abrir turno en esa sede (sin otro abierto en ella y, si ya
 * cerró caja hoy en ella, con autorización). Si el cajero tiene una sola sede no
 * necesita indicarla: se toma esa.
 */
class AbrirLdTurnoCajaRequest extends FormRequest
{
    /**
     * La autorización se resuelve con el middleware de permisos.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Con una sola sede asignada, el cajero solo registra la base inicial.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('sede_id')) {
            return;
        }

        $sedes = $this->user()->idsSedesAccesibles();
        if (count($sedes) === 1) {
            $this->merge(['sede_id' => $sedes[0]]);
        }
    }

    /**
     * Reglas de validación.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sede_id' => ['required', 'integer', 'exists:sedes,id'],
            'base_inicial' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Valida el acceso a la sede y las reglas de apertura del turno en ella.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->has('sede_id')) {
                return;
            }

            if (! $this->user()->tieneAccesoSede((int) $this->sede_id)) {
                $v->errors()->add('sede_id', 'No tiene asignada esta sede.');

                return;
            }

            $motivo = app(LdTurnoCajaService::class)->motivoNoPuedeAbrir($this->user()->id, (int) $this->sede_id);
            if ($motivo) {
                $v->errors()->add('turno', $motivo);
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
            'sede_id.required' => 'La sede es obligatoria.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
            'base_inicial.required' => 'La base inicial de caja es obligatoria.',
            'base_inicial.min' => 'La base inicial no puede ser negativa.',
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
            'sede_id' => 'sede',
            'base_inicial' => 'base inicial',
        ];
    }
}
