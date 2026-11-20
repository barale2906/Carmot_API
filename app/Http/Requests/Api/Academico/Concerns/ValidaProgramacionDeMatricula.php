<?php

namespace App\Http\Requests\Api\Academico\Concerns;

use App\Models\Academico\Programacion;

/**
 * Validación compartida al crear y editar matrículas: la programación elegida debe ser
 * de la sede y del curso de la matrícula, porque de ella sale la jornada que define
 * los descuentos aplicables.
 */
trait ValidaProgramacionDeMatricula
{
    /**
     * @param  \Illuminate\Validation\Validator  $validator
     * @param  mixed  $programacionId
     * @param  mixed  $sedeId
     * @param  mixed  $cursoId
     */
    protected function validarProgramacionDeMatricula($validator, $programacionId, $sedeId, $cursoId): void
    {
        if (! $programacionId || $validator->errors()->has('programacion_id')) {
            return;
        }

        $programacion = Programacion::find($programacionId, ['id', 'sede_id', 'curso_id']);
        if (! $programacion) {
            return;
        }

        if ((int) $programacion->sede_id !== (int) $sedeId) {
            $validator->errors()->add('programacion_id', 'La programación no pertenece a la sede de la matrícula.');
        }

        if ((int) $programacion->curso_id !== (int) $cursoId) {
            $validator->errors()->add('programacion_id', 'La programación no pertenece al curso de la matrícula.');
        }
    }
}
