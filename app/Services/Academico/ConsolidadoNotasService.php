<?php

namespace App\Services\Academico;

use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use Illuminate\Support\Collection;

/**
 * Servicio ConsolidadoNotasService
 *
 * Consulta transversal de resultados por módulo: una fila por estudiante y grupo con su
 * nota final, recuperación, nota definitiva y estado. Se filtra por estudiante, grupo,
 * ciclo o profesor (combinables) y reutiliza el cálculo de {@see SabanaNotasService}
 * para que la consulta, las sábanas y la evaluación de cierre digan lo mismo.
 *
 * Incluye matrículas cerradas (graduadas, reprobadas...) para consultar el histórico, pero
 * solo ofrece recuperar (`puede_recuperar`) a las vigentes. Los grupos cuyo módulo no tiene
 * esquema activo no producen filas: se informan aparte en `sin_esquema`.
 *
 * @package App\Services\Academico
 */
class ConsolidadoNotasService
{
    public function __construct(private readonly SabanaNotasService $sabanaNotas)
    {
    }

    /**
     * Calcula el consolidado.
     *
     * @param array<string, mixed> $filtros ciclo_id, grupo_id, profesor_id, estudiante_id, buscar,
     *                                      estado (pendiente|aprobado|reprobado), por_recuperar
     *                                      y sede_ids (sedes visibles; null = sin restricción).
     * @return array{filas: Collection<int, array<string, mixed>>, sin_esquema: array<int, array<string, mixed>>}
     */
    public function consultar(array $filtros): array
    {
        $cicloId = $filtros['ciclo_id'] ?? null;

        $grupos = Grupo::with(['modulo', 'profesor'])
            ->when($filtros['grupo_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when($filtros['profesor_id'] ?? null, fn ($q, $id) => $q->where('profesor_id', $id))
            ->when(isset($filtros['sede_ids']), fn ($q) => $q->whereIn('sede_id', $filtros['sede_ids']))
            ->whereHas('ciclos', fn ($q) => $q->when($cicloId, fn ($c) => $c->whereKey($cicloId)))
            ->orderBy('nombre')
            ->get();

        $filas      = collect();
        $sinEsquema = [];

        foreach ($grupos as $grupo) {
            $matriculas = $this->matriculasDelGrupo($grupo, $filtros);

            if ($matriculas->isEmpty()) {
                continue;
            }

            $calculo = $this->sabanaNotas->resultadosGrupo($grupo, $matriculas->pluck('estudiante_id'));

            if (!$calculo) {
                $sinEsquema[] = [
                    'grupo'  => ['id' => $grupo->id, 'nombre' => $grupo->nombre],
                    'modulo' => ['id' => $grupo->modulo->id, 'nombre' => $grupo->modulo->nombre],
                ];
                continue;
            }

            foreach ($matriculas as $matricula) {
                $resultado = $calculo['resultados'][$matricula->estudiante_id];
                // Como las notas, la recuperación solo se registra con la matrícula vigente:
                // una ya cerrada (graduada, reprobada...) se consulta pero no se recupera.
                $resultado['puede_recuperar'] = $resultado['puede_recuperar'] && (int) $matricula->status === 1;

                $filas->push([
                    'estudiante' => [
                        'id'        => $matricula->estudiante->id,
                        'name'      => $matricula->estudiante->name,
                        'documento' => $matricula->estudiante->documento,
                    ],
                    'ciclo' => [
                        'id'             => $matricula->ciclo->id,
                        'nombre'         => $matricula->ciclo->nombre,
                        'notas_cerradas' => $matricula->ciclo->notasCerradas(),
                    ],
                    'grupo'    => ['id' => $grupo->id, 'nombre' => $grupo->nombre],
                    'modulo'   => ['id' => $grupo->modulo->id, 'nombre' => $grupo->modulo->nombre],
                    'profesor' => $grupo->profesor ? ['id' => $grupo->profesor->id, 'name' => $grupo->profesor->name] : null,
                    'esquema_calificacion' => [
                        'id'             => $calculo['esquema']->id,
                        'nombre_esquema' => $calculo['esquema']->nombre_esquema,
                    ],
                ] + $resultado);
            }
        }

        $filas = $filas
            ->when($filtros['estado'] ?? null, fn ($c, $estado) => $c->where('estado', $estado))
            ->when($filtros['por_recuperar'] ?? false, fn ($c) => $c->filter(fn ($f) => $f['puede_recuperar'] && !$f['recuperacion']))
            ->sortBy([['estudiante.name', 'asc'], ['modulo.nombre', 'asc']])
            ->values();

        return ['filas' => $filas, 'sin_esquema' => $sinEsquema];
    }

    /**
     * Totales del consolidado: conteo por estado, recuperaciones pendientes y promedio
     * de las notas definitivas de los módulos completos.
     *
     * @param Collection<int, array<string, mixed>> $filas
     * @return array<string, mixed>
     */
    public function resumen(Collection $filas): array
    {
        $definitivas = $filas->pluck('nota_definitiva')->filter(fn ($nota) => $nota !== null);

        return [
            'total'         => $filas->count(),
            'aprobados'     => $filas->where('estado', SabanaNotasService::ESTADO_APROBADO)->count(),
            'reprobados'    => $filas->where('estado', SabanaNotasService::ESTADO_REPROBADO)->count(),
            'pendientes'    => $filas->where('estado', SabanaNotasService::ESTADO_PENDIENTE)->count(),
            'por_recuperar' => $filas->filter(fn ($f) => $f['puede_recuperar'] && !$f['recuperacion'])->count(),
            'promedio'      => $definitivas->isNotEmpty() ? round($definitivas->avg(), 2) : null,
        ];
    }

    /**
     * Matrículas no anuladas de los ciclos del grupo (o solo del ciclo filtrado), una por
     * estudiante: si un grupo compartido aparece en dos ciclos del mismo estudiante, sus
     * notas son las mismas y basta una fila.
     *
     * @param Grupo                $grupo
     * @param array<string, mixed> $filtros
     * @return Collection<int, Matricula>
     */
    private function matriculasDelGrupo(Grupo $grupo, array $filtros): Collection
    {
        $ciclos = $grupo->ciclos()
            ->when($filtros['ciclo_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->select('ciclos.id');

        return Matricula::whereIn('ciclo_id', $ciclos)
            ->where('status', '!=', 2) // las anuladas no cuentan
            ->when($filtros['estudiante_id'] ?? null, fn ($q, $id) => $q->where('estudiante_id', $id))
            ->when($filtros['buscar'] ?? null, fn ($q, $texto) => $q->whereHas('estudiante', fn ($e) => $e->buscar($texto)))
            ->with(['estudiante', 'ciclo'])
            ->orderByDesc('status')
            ->get()
            ->unique('estudiante_id')
            ->values();
    }
}
