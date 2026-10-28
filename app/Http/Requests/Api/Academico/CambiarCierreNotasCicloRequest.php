<?php

namespace App\Http\Requests\Api\Academico;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cierre o reapertura de las notas de un ciclo. La acción sale de la ruta
 * (`.../cerrar` o `.../reabrir`); reabrir exige un motivo para la bitácora.
 */
class CambiarCierreNotasCicloRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motivo' => [$this->esReapertura() ? 'required' : 'nullable', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Indique el motivo para reabrir las notas del ciclo.',
            'motivo.min'      => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max'      => 'El motivo no puede superar 500 caracteres.',
        ];
    }

    /**
     * Obtiene los atributos personalizados.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['motivo' => 'motivo'];
    }

    /**
     * No se cierra un ciclo ya cerrado ni se reabre uno abierto.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\Academico\Ciclo $ciclo */
            $ciclo = $this->route('ciclo');

            if ($this->esReapertura() && ! $ciclo->notasCerradas()) {
                $validator->errors()->add('ciclo', 'Las notas del ciclo ya están abiertas.');
            } elseif (! $this->esReapertura() && $ciclo->notasCerradas()) {
                $validator->errors()->add('ciclo', 'Las notas del ciclo ya están cerradas.');
            }
        });
    }

    /**
     * Indica si la petición es para reabrir las notas.
     *
     * @return bool
     */
    public function esReapertura(): bool
    {
        return $this->routeIs('notas-estudiantes.cierre-ciclo.reabrir');
    }
}
