<?php

namespace App\Http\Requests\Api\Activos;

use App\Models\Activos\ActMovimiento;
use Illuminate\Foundation\Http\FormRequest;

class CancelarTrasladoActActivoRequest extends FormRequest
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
            'motivo.required' => 'Indica el motivo de la cancelación.',
        ];
    }

    /**
     * Solo se cancela un traslado pendiente.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var ActMovimiento $traslado */
            $traslado = $this->route('traslado');

            if (! $traslado->estaPendiente()) {
                $validator->errors()->add('traslado', 'El traslado ya fue recibido o cancelado.');
            }
        });
    }
}
