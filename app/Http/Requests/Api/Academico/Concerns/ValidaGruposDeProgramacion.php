<?php

namespace App\Http\Requests\Api\Academico\Concerns;

use App\Models\Academico\Grupo;
use Illuminate\Support\Facades\DB;

/**
 * Validación compartida al crear y editar una programación y al asignarle grupos:
 * cada grupo debe ser de la sede de la programación y dictar un módulo de su curso (N23).
 * Los grupos llegan como `grupos.*.grupo_id`.
 */
trait ValidaGruposDeProgramacion
{
    /**
     * Agrega un error en `grupos` por cada grupo de otra sede o de un módulo ajeno al curso.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @param mixed $sedeId  Sede de la programación
     * @param mixed $cursoId Curso de la programación
     * @return void
     */
    protected function validarGruposDeProgramacion($validator, $sedeId, $cursoId): void
    {
        $ids = collect($this->input('grupos'))->pluck('grupo_id')->filter()->unique();

        if ($ids->isEmpty() || $validator->errors()->has('grupos.*')) {
            return;
        }

        $modulosCurso = $cursoId
            ? DB::table('modulo_curso')->where('curso_id', $cursoId)->pluck('modulo_id')
            : null;

        Grupo::whereKey($ids)->get()->each(function (Grupo $grupo) use ($validator, $sedeId, $modulosCurso) {
            if ($sedeId && (int) $grupo->sede_id !== (int) $sedeId) {
                $validator->errors()->add('grupos', "El grupo {$grupo->nombre} no pertenece a la sede de la programación.");
            }
            if ($modulosCurso && ! $modulosCurso->contains($grupo->modulo_id)) {
                $validator->errors()->add('grupos', "El módulo del grupo {$grupo->nombre} no pertenece al curso de la programación.");
            }
        });
    }
}
