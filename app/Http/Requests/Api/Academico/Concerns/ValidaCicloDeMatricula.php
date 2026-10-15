<?php

namespace App\Http\Requests\Api\Academico\Concerns;

use App\Models\Academico\Ciclo;

/**
 * Validación compartida al crear y editar matrículas: el ciclo debe ser de la
 * sede y del curso de la matrícula (B14). Antes se aceptaba un ciclo de otra
 * sede o de otro curso, y sumaba un inscrito a ese ciclo.
 */
trait ValidaCicloDeMatricula
{
    /**
     * @param \Illuminate\Validation\Validator $validator
     * @param mixed $cicloId
     * @param mixed $sedeId
     * @param mixed $cursoId
     * @return void
     */
    protected function validarCicloDeMatricula($validator, $cicloId, $sedeId, $cursoId): void
    {
        if (! $cicloId || $validator->errors()->has('ciclo_id')) {
            return;
        }

        $ciclo = Ciclo::find($cicloId, ['id', 'sede_id', 'curso_id']);
        if (! $ciclo) {
            return;
        }

        if ($sedeId && (int) $ciclo->sede_id !== (int) $sedeId) {
            $validator->errors()->add('ciclo_id', 'El ciclo seleccionado no pertenece a la sede de la matrícula.');
        }
        if ($cursoId && (int) $ciclo->curso_id !== (int) $cursoId) {
            $validator->errors()->add('ciclo_id', 'El ciclo seleccionado no pertenece al curso de la matrícula.');
        }
    }
}
