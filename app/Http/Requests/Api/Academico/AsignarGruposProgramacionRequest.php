<?php

namespace App\Http\Requests\Api\Academico;

use App\Http\Requests\Api\Academico\Concerns\ValidaGruposDeProgramacion;
use App\Models\Academico\Programacion;
use Illuminate\Foundation\Http\FormRequest;

class AsignarGruposProgramacionRequest extends FormRequest
{
    use ValidaGruposDeProgramacion;

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
     * Reglas de los grupos a asignar: las fechas de cada grupo deben caer dentro de la programación.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Programacion $programacion */
        $programacion = $this->route('programacion');
        $inicio       = $programacion->fecha_inicio->format('Y-m-d');
        $fin          = $programacion->fecha_fin->format('Y-m-d');

        return [
            'grupos'                      => 'required|array',
            'grupos.*.grupo_id'           => 'required|integer|exists:grupos,id',
            'grupos.*.fecha_inicio_grupo' => "nullable|date|after_or_equal:{$inicio}|before_or_equal:{$fin}",
            'grupos.*.fecha_fin_grupo'    => "nullable|date|after:grupos.*.fecha_inicio_grupo|before_or_equal:{$fin}",
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
            'grupos.required'                            => 'Seleccione al menos un grupo.',
            'grupos.*.grupo_id.exists'                   => 'Uno o más grupos seleccionados no existen.',
            'grupos.*.fecha_inicio_grupo.after_or_equal' => 'La fecha de inicio del grupo debe estar dentro de la programación.',
            'grupos.*.fecha_inicio_grupo.before_or_equal' => 'La fecha de inicio del grupo debe estar dentro de la programación.',
            'grupos.*.fecha_fin_grupo.after'             => 'La fecha de fin del grupo debe ser posterior a su fecha de inicio.',
            'grupos.*.fecha_fin_grupo.before_or_equal'   => 'La fecha de fin del grupo debe estar dentro de la programación.',
        ];
    }

    /**
     * Los grupos deben ser de la sede de la programación y de módulos de su curso (N23).
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        /** @var Programacion $programacion */
        $programacion = $this->route('programacion');

        $validator->after(fn ($validator) => $this->validarGruposDeProgramacion(
            $validator,
            $programacion->sede_id,
            $programacion->curso_id
        ));
    }
}
