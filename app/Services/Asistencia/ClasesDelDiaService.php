<?php

namespace App\Services\Asistencia;

use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Matricula;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Clases con asistencia por tomar en un día, para que quien recibe a los estudiantes en la
 * sede las encuentre sin buscar el grupo. Un profesor ve solo sus grupos; el personal, los
 * de sus sedes. Cada clase trae su lista de estudiantes con matrícula activa (la misma
 * regla de la lista de asistencia del grupo) y cuántos ya tienen asistencia registrada.
 */
class ClasesDelDiaService
{
    /**
     * @param User     $usuario
     * @param string   $fecha   "YYYY-MM-DD"
     * @param int|null $sedeId  Limita a una sede (debe ser accesible para el usuario)
     * @return array{sedes: Collection, clases: Collection}
     */
    public function obtener(User $usuario, string $fecha, ?int $sedeId = null): array
    {
        $sedes = $usuario->esSoloProfesor()
            ? collect()
            : $usuario->sedesAccesibles()->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre])->values();

        $clases = AsistenciaClaseProgramada::query()
            ->whereDate('fecha_clase', $fecha)
            ->where('estado', '!=', 'cancelada')
            ->whereHas('ciclo', fn ($q) => $q->abiertosParaRegistro())
            ->whereHas('grupo', function ($q) use ($usuario, $sedeId) {
                if ($usuario->esSoloProfesor()) {
                    $q->where('profesor_id', $usuario->id);
                } else {
                    $q->whereIn('sede_id', $usuario->idsSedesAccesibles());
                }
                $q->when($sedeId, fn ($q2) => $q2->where('sede_id', $sedeId));
            })
            ->with(['grupo.sede', 'grupo.modulo', 'ciclo.curso'])
            ->withCount('asistencias')
            ->orderBy('hora_inicio')
            ->get();

        $estudiantesPorCiclo = $this->estudiantesPorCiclo($clases->pluck('ciclo_id')->unique());

        return [
            'sedes'  => $sedes,
            'clases' => $clases->map(fn ($clase) => [
                'id'                    => $clase->id,
                'fecha_clase'           => $clase->fecha_clase->format('Y-m-d'),
                'hora_inicio'           => $clase->hora_inicio,
                'hora_fin'              => $clase->hora_fin,
                'estado'                => $clase->estado,
                'grupo'                 => ['id' => $clase->grupo->id, 'nombre' => $clase->grupo->nombre],
                'modulo'                => $clase->grupo->modulo ? ['id' => $clase->grupo->modulo->id, 'nombre' => $clase->grupo->modulo->nombre] : null,
                'curso'                 => $clase->ciclo->curso ? ['id' => $clase->ciclo->curso->id, 'nombre' => $clase->ciclo->curso->nombre] : null,
                'sede'                  => $clase->grupo->sede ? ['id' => $clase->grupo->sede->id, 'nombre' => $clase->grupo->sede->nombre] : null,
                'asistencias_registradas' => $clase->asistencias_count,
                'estudiantes'           => $estudiantesPorCiclo->get($clase->ciclo_id, collect())->values(),
            ])->values(),
        ];
    }

    /**
     * Estudiantes con matrícula vigente y activa (no aplazados) por ciclo.
     *
     * @return Collection<int, Collection>
     */
    private function estudiantesPorCiclo(Collection $cicloIds): Collection
    {
        if ($cicloIds->isEmpty()) {
            return collect();
        }

        return Matricula::whereIn('ciclo_id', $cicloIds)
            ->where('status', 1)
            ->where('estado_academico', Matricula::ESTADO_ACTIVO)
            ->with('estudiante:id,primer_nombre,segundo_nombre,primer_apellido,segundo_apellido,documento')
            ->get()
            ->filter(fn ($m) => $m->estudiante)
            ->groupBy('ciclo_id')
            ->map(fn ($matriculas) => $matriculas
                ->unique('estudiante_id')
                ->map(fn ($m) => [
                    'id'        => $m->estudiante->id,
                    'name'      => $m->estudiante->name,
                    'documento' => $m->estudiante->documento,
                ])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE));
    }
}
