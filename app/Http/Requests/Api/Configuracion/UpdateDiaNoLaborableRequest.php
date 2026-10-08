<?php

namespace App\Http\Requests\Api\Configuracion;

use App\Models\Configuracion\DiaNoLaborable;
use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDiaNoLaborableRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;

    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha'         => ['sometimes', 'date_format:Y-m-d'],
            'nombre'        => ['sometimes', 'string', 'max:255'],
            'tipo'          => ['sometimes', Rule::in(array_keys(DiaNoLaborable::TIPOS))],
            'sede_id'       => ['sometimes', 'nullable', 'integer', 'exists:sedes,id'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'status'        => self::getStatusValidationRule(),
        ];
    }

    /**
     * Valida duplicados con la fecha y sede resultantes (las enviadas o las actuales).
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var DiaNoLaborable $dia */
            $dia    = $this->route('diaNoLaborable');
            $fecha  = $this->input('fecha', $dia->fecha->toDateString());
            $sedeId = $this->has('sede_id')
                ? ($this->filled('sede_id') ? (int) $this->input('sede_id') : null)
                : $dia->sede_id;

            $mensaje = DiaNoLaborable::motivoDuplicado($fecha, $sedeId, $dia->id);

            if ($mensaje) {
                $validator->errors()->add('fecha', $mensaje);
            }
        });
    }

    /**
     * Obtiene los mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'nombre.max'        => 'El nombre no puede exceder los 255 caracteres.',
            'tipo.in'           => 'El tipo debe ser: ' . implode(', ', array_keys(DiaNoLaborable::TIPOS)) . '.',
            'sede_id.exists'    => 'La sede seleccionada no existe.',
            'observaciones.max' => 'Las observaciones no pueden exceder los 1000 caracteres.',
        ], self::getStatusValidationMessages());
    }

    /**
     * Obtiene los atributos personalizados para los mensajes de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha'         => 'fecha',
            'nombre'        => 'nombre',
            'tipo'          => 'tipo',
            'sede_id'       => 'sede',
            'observaciones' => 'observaciones',
            'status'        => 'estado',
        ];
    }
}
