<?php

namespace App\Http\Requests\Api\Activos;

use App\Models\Activos\ActActivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoActActivoRequest extends FormRequest
{
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(array_keys(ActActivo::ESTADOS))],
            'fecha'  => ['required', 'date', 'before_or_equal:today'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * Obtiene los mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.in'             => 'El estado no es válido.',
            'fecha.before_or_equal' => 'La fecha del cambio no puede ser futura.',
            'motivo.required'       => 'Indica el motivo del cambio de estado.',
        ];
    }

    /**
     * La baja es definitiva, el estado nuevo debe ser distinto del actual y un activo
     * en tránsito no cambia de estado hasta que se reciba o se cancele el traslado.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var ActActivo $activo */
            $activo = $this->route('activo');

            if ($activo->estaDeBaja()) {
                $validator->errors()->add('estado', 'El activo está dado de baja: la baja es definitiva.');
            } elseif ($activo->estaEnTransito()) {
                $validator->errors()->add('estado', 'El activo tiene un traslado pendiente de recepción: recíbelo o cancélalo antes de cambiar su estado.');
            } elseif ($activo->estado === $this->estado) {
                $validator->errors()->add('estado', 'El activo ya tiene ese estado.');
            }
        });
    }
}
