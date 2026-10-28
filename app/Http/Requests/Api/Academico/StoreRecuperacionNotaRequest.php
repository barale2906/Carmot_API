<?php

namespace App\Http\Requests\Api\Academico;

use App\Http\Requests\Api\Academico\Concerns\ValidaEstudiantesDelGrupo;
use App\Http\Requests\Api\Academico\Concerns\ValidaNotasAbiertas;
use App\Models\Academico\Grupo;
use App\Services\Academico\SabanaNotasService;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecuperacionNotaRequest extends FormRequest
{
    use ValidaEstudiantesDelGrupo, ValidaNotasAbiertas;

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
            'estudiante_id'  => 'required|integer|exists:users,id',
            'grupo_id'       => 'required|integer|exists:grupos,id',
            'nota'           => 'required|numeric|min:0',
            'fecha_registro' => 'required|date',
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
            'estudiante_id.required' => 'El estudiante es obligatorio.',
            'estudiante_id.exists'   => 'El estudiante seleccionado no existe.',
            'grupo_id.required'      => 'El grupo es obligatorio.',
            'grupo_id.exists'        => 'El grupo seleccionado no existe.',
            'nota.required'          => 'La nota de recuperación es obligatoria.',
            'nota.numeric'           => 'La nota de recuperación debe ser un número.',
            'nota.min'               => 'La nota de recuperación no puede ser menor a 0.',
            'fecha_registro.date'    => 'La fecha de registro debe ser una fecha válida.',
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
            'estudiante_id'  => 'estudiante',
            'grupo_id'       => 'grupo',
            'nota'           => 'nota de recuperación',
            'fecha_registro' => 'fecha de registro',
            'observaciones'  => 'observaciones',
        ];
    }

    /**
     * La fecha de registro es hoy si no se indica.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('fecha_registro')) {
            $this->merge(['fecha_registro' => now()->toDateString()]);
        }
    }

    /**
     * El estudiante debe estar en el grupo, con las notas abiertas, y haber reprobado
     * el módulo con la nota final completa; la nota debe caber en la escala del esquema.
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

            $grupo        = Grupo::find($this->grupo_id);
            $estudianteId = (int) $this->estudiante_id;

            $this->validarEstudiantesDelGrupo($validator, $grupo, [$estudianteId]);
            $this->validarNotasAbiertas($validator, $grupo, [$estudianteId]);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $calculo = app(SabanaNotasService::class)->resultadosGrupo($grupo, collect([$estudianteId]));

            if (! $calculo) {
                $validator->errors()->add('grupo_id', 'El módulo del grupo no tiene un esquema de calificación activo.');
                return;
            }

            $resultado = $calculo['resultados'][$estudianteId];

            if (! $resultado['completo']) {
                $validator->errors()->add('estudiante_id', 'El estudiante aún tiene notas pendientes en el módulo: la recuperación se registra cuando la nota final está completa.');
            } elseif (! $resultado['puede_recuperar']) {
                $validator->errors()->add('estudiante_id', "El estudiante aprobó el módulo con nota final {$resultado['nota_final']}: no requiere recuperación.");
            }

            $rango = $calculo['esquema']->rangoNotas();
            if ((float) $this->nota < $rango['min'] || (float) $this->nota > $rango['max']) {
                $validator->errors()->add('nota', "La nota de recuperación debe estar entre {$rango['min']} y {$rango['max']}.");
            }
        });
    }
}
