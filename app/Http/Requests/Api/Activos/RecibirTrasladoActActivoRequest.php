<?php

namespace App\Http\Requests\Api\Activos;

use App\Http\Requests\Api\Activos\Concerns\ValidaUbicacionActivo;
use App\Models\Activos\ActMovimiento;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Recepción de un activo trasladado: quien lo recibe en la sede de destino confirma
 * la llegada, la fecha y el estado en que llegó, y puede asignar el responsable.
 */
class RecibirTrasladoActActivoRequest extends FormRequest
{
    use ValidaUbicacionActivo;

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
            'fecha_recepcion'         => ['required', 'date', 'before_or_equal:today'],
            'observaciones_recepcion' => ['nullable', 'string', 'max:1000'],
            'responsable_id'          => ['sometimes', 'nullable', 'integer', 'exists:users,id', $this->reglaResponsable()],
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
            'fecha_recepcion.before_or_equal' => 'La fecha de recepción no puede ser futura.',
        ];
    }

    /**
     * Obtiene los atributos personalizados para los mensajes de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha_recepcion'         => 'fecha de recepción',
            'observaciones_recepcion' => 'observaciones de la recepción',
            'responsable_id'          => 'responsable',
        ];
    }

    /**
     * Solo se recibe un traslado pendiente de un activo vigente, y no antes de la fecha en que se envió.
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
            } elseif (! $traslado->activo) {
                $validator->errors()->add('traslado', 'El activo de este traslado fue eliminado.');
            } elseif ($validator->errors()->isEmpty() && $this->date('fecha_recepcion')->lt($traslado->fecha)) {
                $validator->errors()->add('fecha_recepcion', 'La fecha de recepción no puede ser anterior a la del envío.');
            }
        });
    }
}
