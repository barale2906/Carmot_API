<?php

namespace App\Http\Requests\Api\Academico\Concerns;

use App\Models\Academico\Grupo;
use Illuminate\Support\Facades\DB;

/**
 * Validación compartida al crear y editar ciclos. Cada grupo debe:
 *  - ser de la sede del ciclo (B07),
 *  - dictar un módulo que pertenezca al curso del ciclo (B06),
 *  - tener horarios, porque sus fechas se calculan con las sesiones del horario;
 *    sin ellos el módulo quedaba sin fechas y sin advertencia (F20).
 */
trait ValidaGruposDelCiclo
{
    /**
     * Agrega un error en `grupos` por cada grupo enviado que incumpla alguna regla.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @param mixed $sedeId  Sede del ciclo
     * @param mixed $cursoId Curso del ciclo
     * @return void
     */
    protected function validarGruposDelCiclo($validator, $sedeId, $cursoId): void
    {
        $ids = $this->input('grupos');

        if (! is_array($ids) || $ids === [] || $validator->errors()->has('grupos.*')) {
            return;
        }

        $modulosCurso = $cursoId
            ? DB::table('modulo_curso')->where('curso_id', $cursoId)->pluck('modulo_id')
            : null;

        Grupo::whereKey($ids)->withCount('horarios')->get()->each(function (Grupo $grupo) use ($validator, $sedeId, $modulosCurso) {
            if ($sedeId && (int) $grupo->sede_id !== (int) $sedeId) {
                $validator->errors()->add('grupos', "El grupo {$grupo->nombre} no pertenece a la sede del ciclo.");
            }
            if ($modulosCurso && ! $modulosCurso->contains($grupo->modulo_id)) {
                $validator->errors()->add('grupos', "El módulo del grupo {$grupo->nombre} no pertenece al curso del ciclo.");
            }
            if ($grupo->horarios_count === 0) {
                $validator->errors()->add('grupos', "El grupo {$grupo->nombre} no tiene horarios: no se pueden calcular las fechas de su módulo.");
            }
        });
    }
}
