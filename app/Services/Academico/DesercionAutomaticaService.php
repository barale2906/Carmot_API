<?php

namespace App\Services\Academico;

use App\Models\Academico\Asistencia;
use App\Models\Academico\Matricula;

/**
 * Marca como Desertado, sin intervención de nadie, al estudiante que lleva
 * Asistencia::FALLAS_DESERCION fallas seguidas en su curso, y lo devuelve a Activo en cuanto
 * se le registra una asistencia o tardanza que corta la racha.
 *
 * Solo toca matrículas Activas (la desertada por el sistema) o Desertadas por el sistema
 * (`desercion_automatica`); un Desertado, Retirado o Graduado puesto a mano no se modifica.
 * Las fallas se cuentan con Asistencia::fallasConsecutivasPorCurso().
 */
class DesercionAutomaticaService
{
    /** Motivo que queda en el historial de estados al desertar automáticamente. */
    public const MOTIVO_DESERCION = 'Deserción automática: inasistencia a clases consecutivas';

    /** Motivo que queda en el historial de estados al reactivar tras registrar asistencia. */
    public const MOTIVO_REACTIVACION = 'Reactivación automática: asistió a clase tras la deserción automática';

    public function __construct(private readonly MatriculaEstadoService $estados) {}

    /**
     * Revisa a los estudiantes indicados, en el curso dado, tras registrar o corregir su asistencia.
     *
     * @param  array<int>  $estudianteIds
     * @param  string  $fecha  Fecha de la clase (Y-m-d) desde la que rige el cambio de estado
     */
    public function evaluar(array $estudianteIds, int $cursoId, string $fecha): void
    {
        if ($estudianteIds === []) {
            return;
        }

        $fallas = Asistencia::fallasConsecutivasPorCurso($estudianteIds);

        Matricula::whereIn('estudiante_id', $estudianteIds)
            ->where('curso_id', $cursoId)
            ->paraAsistencia()
            ->get()
            ->each(function (Matricula $matricula) use ($fallas, $fecha) {
                $seguidas = $fallas->get("{$matricula->estudiante_id}:{$matricula->curso_id}", 0);

                if ($matricula->estado_academico === Matricula::ESTADO_ACTIVO && $seguidas >= Asistencia::FALLAS_DESERCION) {
                    $this->estados->cambiar($matricula, Matricula::ESTADO_DESERTADO, $fecha, self::MOTIVO_DESERCION, null);
                    $matricula->update(['desercion_automatica' => true]);
                } elseif ($matricula->desercion_automatica && $matricula->estado_academico === Matricula::ESTADO_DESERTADO && $seguidas === 0) {
                    $this->estados->cambiar($matricula, Matricula::ESTADO_ACTIVO, $fecha, self::MOTIVO_REACTIVACION, null);
                    $matricula->update(['desercion_automatica' => false]);
                }
            });
    }
}
