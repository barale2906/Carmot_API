<?php

namespace App\Http\Requests\Api\Academico\Concerns;

use App\Models\Academico\Grupo;

/**
 * Validación compartida al registrar o cambiar notas y recuperaciones: el ciclo del
 * estudiante no puede tener las notas cerradas. Para modificarlas, quien tenga el
 * permiso `aca_notaReabrir` debe reabrir primero el ciclo.
 */
trait ValidaNotasAbiertas
{
    /**
     * Agrega un error en "{campo}.{i}.estudiante_id" (o en "estudiante_id" si $campo es null)
     * por cada estudiante con las notas cerradas en el grupo.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @param Grupo|null $grupo
     * @param array<int, mixed> $estudianteIds Índice → estudiante_id
     * @param string|null $campo Arreglo del payload (p. ej. "notas"); null para un solo estudiante
     * @return void
     */
    protected function validarNotasAbiertas($validator, ?Grupo $grupo, array $estudianteIds, ?string $campo = null): void
    {
        if (! $grupo || $estudianteIds === []) {
            return;
        }

        $cerrados = $grupo->idsEstudiantesConNotasCerradas();

        foreach ($estudianteIds as $i => $estudianteId) {
            $clave = $campo ? "{$campo}.{$i}.estudiante_id" : 'estudiante_id';
            if ($estudianteId && $cerrados->contains((int) $estudianteId) && ! $validator->errors()->has($clave)) {
                $validator->errors()->add($clave, "Las notas del ciclo están cerradas para este estudiante en el grupo {$grupo->nombre}.");
            }
        }
    }
}
