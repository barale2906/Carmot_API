<?php

namespace App\Services\Academico;

use App\Models\Academico\Matricula;
use App\Models\Academico\MatriculaEstado;
use Illuminate\Support\Facades\DB;

/**
 * Cambia el estado académico de una matrícula y deja constancia en su historial.
 *
 * Desertado, Retirado y Graduado cierran la formación: la matrícula pasa a status 0
 * (inactiva), lo que descuenta el cupo del ciclo y libera el curso para una nueva
 * matrícula. Volver a Activo o Aplazado desde un estado final la reactiva (status 1).
 * La cartera no se toca: las cuotas pendientes siguen siendo exigibles.
 */
class MatriculaEstadoService
{
    /**
     * @param Matricula $matricula
     * @param int $estadoNuevo Uno de Matricula::ESTADOS_ACADEMICOS
     * @param string $fecha Fecha desde la que rige el estado (Y-m-d)
     * @param string $motivo
     * @param int|null $usuarioId
     * @return MatriculaEstado
     */
    public function cambiar(Matricula $matricula, int $estadoNuevo, string $fecha, string $motivo, ?int $usuarioId): MatriculaEstado
    {
        return DB::transaction(function () use ($matricula, $estadoNuevo, $fecha, $motivo, $usuarioId) {
            $registro = $matricula->historialEstados()->create([
                'estado_anterior' => $matricula->estado_academico,
                'estado_nuevo'    => $estadoNuevo,
                'fecha'           => $fecha,
                'motivo'          => $motivo,
                'usuario_id'      => $usuarioId,
            ]);

            $matricula->update([
                'estado_academico'       => $estadoNuevo,
                'fecha_estado_academico' => $fecha,
                'status'                 => in_array($estadoNuevo, Matricula::ESTADOS_ACADEMICOS_FINALES, true) ? 0 : 1,
            ]);

            return $registro;
        });
    }
}
