<?php

namespace App\Http\Requests\Api\Configuracion;

use App\Models\Configuracion\DiaNoLaborable;
use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDiaNoLaborableRequest extends FormRequest
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
     * sede_id NULL o ausente = aplica a todas las sedes.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fecha'         => ['required', 'date_format:Y-m-d'],
            'nombre'        => ['required', 'string', 'max:255'],
            'tipo'          => ['required', Rule::in(array_keys(DiaNoLaborable::TIPOS))],
            'sede_id'       => ['nullable', 'integer', 'exists:sedes,id'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'status'        => self::getStatusValidationRule(),
        ];
    }

    /**
     * Rechaza la fecha si ya está registrada para la misma sede o si ya existe
     * como día general (todas las sedes).
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

            $mensaje = DiaNoLaborable::motivoDuplicado($this->input('fecha'), $this->filled('sede_id') ? (int) $this->input('sede_id') : null);

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
            'fecha.required'    => 'La fecha es obligatoria.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'nombre.required'   => 'El nombre es obligatorio.',
            'nombre.max'        => 'El nombre no puede exceder los 255 caracteres.',
            'tipo.required'     => 'El tipo es obligatorio.',
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
