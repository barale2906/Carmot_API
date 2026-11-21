<?php

namespace App\Services\Asistencia;

use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\AsistenciaReposicion;
use App\Models\Academico\Matricula;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Arma las opciones para pedir una reposición: las clases de la semana del estudiante en sus
 * grupos (origen) y, para cada una, las clases de otros grupos de su curso que se pueden tomar
 * en su lugar: misma semana, mismo módulo (los mismos temas) y sin estar canceladas ni reprogramadas.
 * Son las mismas reglas con las que StoreAsistenciaReposicionRequest valida la reposición.
 */
class ReposicionOpcionesService
{
    /**
     * @param  Carbon  $fecha  Cualquier día de la semana a consultar
     * @return Collection<int, array{origen: AsistenciaClaseProgramada, reposicion_id: int|null, destinos: Collection<int, AsistenciaClaseProgramada>}>
     */
    public function obtener(int $estudianteId, Carbon $fecha): Collection
    {
        $inicio = $fecha->copy()->startOfWeek();
        $fin = $fecha->copy()->endOfWeek();

        $ciclosIds = Matricula::where('estudiante_id', $estudianteId)->paraAsistencia()->pluck('ciclo_id');

        $origenes = AsistenciaClaseProgramada::whereIn('ciclo_id', $ciclosIds)
            ->whereBetween('fecha_clase', [$inicio->toDateString(), $fin->toDateString()])
            ->whereNotIn('estado', ['cancelada', 'reprogramada'])
            ->with(['grupo', 'ciclo'])
            ->orderBy('fecha_clase')
            ->orderBy('hora_inicio')
            ->get();

        if ($origenes->isEmpty()) {
            return collect();
        }

        $candidatas = AsistenciaClaseProgramada::whereBetween('fecha_clase', [$inicio->toDateString(), $fin->toDateString()])
            ->whereNotIn('estado', ['cancelada', 'reprogramada'])
            ->whereHas('ciclo', fn ($q) => $q->whereIn('curso_id', $origenes->pluck('ciclo.curso_id')->unique()))
            ->with(['grupo.sede', 'ciclo'])
            ->orderBy('fecha_clase')
            ->orderBy('hora_inicio')
            ->get();

        $reposiciones = AsistenciaReposicion::where('estudiante_id', $estudianteId)
            ->whereIn('clase_origen_id', $origenes->pluck('id'))
            ->pluck('id', 'clase_origen_id');

        return $origenes->map(fn (AsistenciaClaseProgramada $origen) => [
            'origen' => $origen,
            'reposicion_id' => $reposiciones->get($origen->id),
            'destinos' => $candidatas->filter(fn (AsistenciaClaseProgramada $c) => $c->grupo_id !== $origen->grupo_id
                && $c->ciclo->curso_id === $origen->ciclo->curso_id
                && $c->grupo->modulo_id === $origen->grupo->modulo_id)->values(),
        ])->values();
    }
}
