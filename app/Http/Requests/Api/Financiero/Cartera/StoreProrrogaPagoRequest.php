<?php

namespace App\Http\Requests\Api\Financiero\Cartera;

use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\Cartera\ProrrogaPago;
use Illuminate\Foundation\Http\FormRequest;

class StoreProrrogaPagoRequest extends FormRequest
{
    /**
     * La autorización se resuelve por middleware de permisos en el controlador.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $maximo = today()->addDays(ProrrogaPago::PLAZO_MAXIMO_DIAS)->toDateString();

        return [
            'estudiante_id' => ['required', 'integer', 'exists:users,id'],
            'fecha_compromiso' => ['required', 'date', 'after:today', "before_or_equal:{$maximo}"],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estudiante_id.required' => 'El estudiante es obligatorio.',
            'estudiante_id.exists' => 'El estudiante seleccionado no existe.',
            'fecha_compromiso.required' => 'La nueva fecha de pago es obligatoria.',
            'fecha_compromiso.date' => 'La nueva fecha de pago no es válida.',
            'fecha_compromiso.after' => 'La nueva fecha de pago debe ser posterior a hoy.',
            'fecha_compromiso.before_or_equal' => 'El plazo nuevo no puede superar '.ProrrogaPago::PLAZO_MAXIMO_DIAS.' días.',
            'observacion.max' => 'La observación no puede exceder los 500 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'estudiante_id' => 'estudiante',
            'fecha_compromiso' => 'nueva fecha de pago',
            'observacion' => 'observación',
        ];
    }

    /**
     * Solo se concede plazo a un estudiante en mora, con acceso a la sede de su cuota vencida más
     * antigua, y que no tenga ya una prórroga vigente.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $cuota = Cartera::vencidas()->where('saldo', '>', 0)
                ->where('estudiante_id', $this->estudiante_id)
                ->orderBy('fecha_vencimiento')->orderBy('numero_cuota')
                ->first();

            if (! $cuota) {
                $validator->errors()->add('estudiante_id', 'El estudiante no tiene cuotas vencidas: no necesita un plazo nuevo.');

                return;
            }

            if (! $this->user()->tieneAccesoSede((int) $cuota->sede_id)) {
                $validator->errors()->add('estudiante_id', 'No tiene acceso a la sede del estudiante.');
            }

            $tieneVigente = ProrrogaPago::vigentes()->where('estudiante_id', $this->estudiante_id)->get()
                ->contains(fn (ProrrogaPago $p) => ! $p->cuotasPagadas());

            if ($tieneVigente) {
                $validator->errors()->add('estudiante_id', 'El estudiante ya tiene un plazo nuevo vigente. Cancélelo para registrar otro.');
            }
        });
    }
}
