<?php

namespace App\Services\Asistencia;

use App\Models\Academico\Asistencia;
use App\Models\Academico\AsistenciaClaseProgramada;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Registra (o corrige) la lista de asistencia de una clase programada.
 *
 * Un estudiante tiene una sola asistencia por clase (índice único), así que volver a
 * guardar la lista actualiza los registros existentes —incluidos los eliminados, que
 * se restauran— en lugar de duplicarlos. Al registrarla, la clase pasa a "dictada".
 * La justificación va aparte del estado: una ausencia justificada sigue siendo falla.
 */
class RegistroAsistenciaService
{
    /**
     * @param AsistenciaClaseProgramada $clase
     * @param array<int, array{estudiante_id: int, estado: string, justificada?: bool, justificacion?: string|null, observaciones?: string|null}> $registros
     * @param int $usuarioId Usuario que toma la asistencia
     * @return array{creadas: int, actualizadas: int, asistencias: EloquentCollection}
     */
    public function registrarClase(AsistenciaClaseProgramada $clase, array $registros, int $usuarioId): array
    {
        return DB::transaction(function () use ($clase, $registros, $usuarioId) {
            $clase->loadMissing(['grupo', 'ciclo']);

            $existentes = Asistencia::withTrashed()
                ->where('clase_programada_id', $clase->id)
                ->whereIn('estudiante_id', array_column($registros, 'estudiante_id'))
                ->get()
                ->keyBy('estudiante_id');

            $creadas      = 0;
            $actualizadas = 0;
            $asistencias  = new EloquentCollection();

            foreach ($registros as $registro) {
                $justificada = filter_var($registro['justificada'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $datos = [
                    'estado'            => $registro['estado'],
                    'justificada'       => $justificada,
                    'justificacion'     => $justificada ? ($registro['justificacion'] ?? null) : null,
                    'observaciones'     => $registro['observaciones'] ?? null,
                    'registrado_por_id' => $usuarioId,
                    'hora_registro'     => now()->format('H:i:s'),
                    'fecha_registro'    => now(),
                ];

                $asistencia = $existentes->get($registro['estudiante_id']);

                if ($asistencia) {
                    if ($asistencia->trashed()) {
                        $asistencia->restore();
                    }
                    $asistencia->update($datos);
                    $actualizadas++;
                } else {
                    $asistencia = Asistencia::create($datos + [
                        'estudiante_id'       => $registro['estudiante_id'],
                        'clase_programada_id' => $clase->id,
                        'grupo_id'            => $clase->grupo_id,
                        'ciclo_id'            => $clase->ciclo_id,
                        'modulo_id'           => $clase->grupo->modulo_id,
                        'curso_id'            => $clase->ciclo->curso_id,
                    ]);
                    $creadas++;
                }

                $asistencias->push($asistencia);
            }

            if ($clase->estado === 'programada') {
                $clase->update(['estado' => 'dictada']);
            }

            return compact('creadas', 'actualizadas', 'asistencias');
        });
    }
}
