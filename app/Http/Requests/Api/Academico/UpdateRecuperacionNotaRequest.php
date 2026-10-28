<?php

namespace App\Http\Requests\Api\Academico;

use App\Http\Requests\Api\Academico\Concerns\ValidaNotasAbiertas;
use App\Models\Academico\EsquemaCalificacion;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRecuperacionNotaRequest extends FormRequest
{
    use ValidaNotasAbiertas;

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
            'nota'           => 'sometimes|required|numeric|min:0',
            'fecha_registro' => 'sometimes|required|date',
            'observaciones'  => 'nullable|string|max:1000',
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
            'nota.required'       => 'La nota de recuperación es obligatoria.',
            'nota.numeric'        => 'La nota de recuperación debe ser un número.',
            'nota.min'            => 'La nota de recuperación no puede ser menor a 0.',
            'fecha_registro.date' => 'La fecha de registro debe ser una fecha válida.',
        ];
    }

    /**
     * Obtiene los atributos personalizados.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nota'           => 'nota de recuperación',
            'fecha_registro' => 'fecha de registro',
            'observaciones'  => 'observaciones',
        ];
    }

    /**
     * Las notas del ciclo deben seguir abiertas y la nota caber en la escala del esquema.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var \App\Models\Academico\RecuperacionNota $recuperacion */
            $recuperacion = $this->route('recuperacion_nota');

            $this->validarNotasAbiertas($validator, $recuperacion->grupo, [$recuperacion->estudiante_id]);

            $esquema = EsquemaCalificacion::activoParaModuloGrupo($recuperacion->modulo_id, $recuperacion->grupo_id)->first();
            if ($esquema && $this->has('nota')) {
                $rango = $esquema->rangoNotas();
                if ((float) $this->nota < $rango['min'] || (float) $this->nota > $rango['max']) {
                    $validator->errors()->add('nota', "La nota de recuperación debe estar entre {$rango['min']} y {$rango['max']}.");
                }
            }
        });
    }
}
