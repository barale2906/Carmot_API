<?php

namespace App\Http\Requests\Api\Academico\Concerns;

use App\Models\Academico\Grupo;

/**
 * Validación compartida al registrar asistencia y notas: cada estudiante debe tener
 * matrícula vigente en un ciclo que incluya el grupo (B23, B26). Antes se aceptaban
 * estudiantes de otros grupos o sedes, y contaminaban reportes y porcentajes.
 */
trait ValidaEstudiantesDelGrupo
{
    /**
     * Agrega un error en "{campo}.{i}.estudiante_id" (o en "estudiante_id" si $campo es null)
     * por cada estudiante sin matrícula vigente en el grupo.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @param Grupo|null $grupo
     * @param array<int, mixed> $estudianteIds Índice → estudiante_id
     * @param string|null $campo Arreglo del payload (p. ej. "notas"); null para un solo estudiante
     * @return void
     */
    protected function validarEstudiantesDelGrupo($validator, ?Grupo $grupo, array $estudianteIds, ?string $campo = null): void
    {
        if (! $grupo || $estudianteIds === []) {
            return;
        }

        $vigentes = $grupo->idsEstudiantesVigentes();

        foreach ($estudianteIds as $i => $estudianteId) {
            $clave = $campo ? "{$campo}.{$i}.estudiante_id" : 'estudiante_id';
            if ($estudianteId && ! $vigentes->contains((int) $estudianteId) && ! $validator->errors()->has($clave)) {
                $validator->errors()->add($clave, "El estudiante no tiene matrícula vigente en el grupo {$grupo->nombre}.");
            }
        }
    }
}
