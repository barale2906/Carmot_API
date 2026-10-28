<?php

namespace App\Services\Academico;

use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\Academico\NotaEstudiante;
use App\Models\Academico\RecuperacionNota;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Servicio SabanaNotasService
 *
 * Fuente única del resultado de un estudiante en un módulo: reúne los tipos de nota del
 * esquema activo, la nota obtenida, su ponderación, la nota final y la recuperación, y
 * define si aprueba. Con eso arma la sábana por estudiante (todos sus módulos) y la
 * grupal (todos los estudiantes de un grupo).
 *
 * Regla de recuperación: solo cuenta si la nota final no alcanza `academico.nota_aprobatoria`.
 * En ese caso la nota de recuperación pasa a ser la definitiva y decide si aprueba.
 *
 * Lo consumen los endpoints de sábana y consolidado, la evaluación de cierre de la
 * matrícula y el bloque de documentos que la imprime, para que todos calculen lo mismo.
 *
 * @package App\Services\Academico
 */
class SabanaNotasService
{
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_APROBADO  = 'aprobado';
    public const ESTADO_REPROBADO = 'reprobado';

    /**
     * Calcula la sábana de notas de un estudiante.
     *
     * @param int      $estudianteId
     * @param int|null $cicloId Restringe la matrícula a un ciclo.
     * @param int|null $cursoId Restringe la matrícula a un curso.
     * @return array<string, mixed>|null Null si el estudiante no tiene matrícula activa.
     */
    public function porEstudiante(int $estudianteId, ?int $cicloId = null, ?int $cursoId = null): ?array
    {
        $estudiante = User::findOrFail($estudianteId);

        $matriculaQuery = Matricula::where('estudiante_id', $estudianteId)
            ->where('status', 1);

        if ($cicloId) {
            $matriculaQuery->where('ciclo_id', $cicloId);
        }

        if ($cursoId) {
            $matriculaQuery->where('curso_id', $cursoId);
        }

        $matricula = $matriculaQuery->with(['curso', 'ciclo'])->first();

        if (!$matricula) {
            return null;
        }

        return $this->construir($estudiante, $matricula);
    }

    /**
     * Calcula la sábana de notas a partir de una matrícula.
     *
     * @param Matricula $matricula
     * @return array<string, mixed>|null Null si la matrícula no tiene estudiante o ciclo.
     */
    public function porMatricula(Matricula $matricula): ?array
    {
        $matricula->loadMissing(['curso', 'ciclo', 'estudiante']);

        if (!$matricula->estudiante || !$matricula->ciclo) {
            return null;
        }

        return $this->construir($matricula->estudiante, $matricula);
    }

    /**
     * Sábana grupal: el resultado de cada estudiante con matrícula activa en algún ciclo del grupo.
     *
     * @param Grupo $grupo
     * @return array<string, mixed>|null Null si el módulo del grupo no tiene esquema activo.
     */
    public function porGrupo(Grupo $grupo): ?array
    {
        $grupo->loadMissing('modulo');

        $matriculas = Matricula::whereIn('ciclo_id', $grupo->ciclos()->select('ciclos.id'))
            ->where('status', 1)
            ->with('estudiante')
            ->get()
            ->unique('estudiante_id');

        $calculo = $this->resultadosGrupo($grupo, $matriculas->pluck('estudiante_id'));

        if (!$calculo) {
            return null;
        }

        $estudiantes = $matriculas->map(fn (Matricula $matricula) => [
            'estudiante' => $this->datosEstudiante($matricula->estudiante),
        ] + $calculo['resultados'][$matricula->estudiante_id])->values();

        return [
            'grupo'                 => ['id' => $grupo->id, 'nombre' => $grupo->nombre],
            'modulo'                => ['id' => $grupo->modulo->id, 'nombre' => $grupo->modulo->nombre],
            'esquema_calificacion'  => $calculo['esquema']->load(['modulo', 'grupo', 'profesor', 'tiposNota']),
            'estudiantes'           => $estudiantes->all(),
            'total_estudiantes'     => $estudiantes->count(),
            'estudiantes_con_notas' => $estudiantes->where('completo', true)->count(),
        ];
    }

    /**
     * Resultado del módulo del grupo para cada estudiante indicado, con tres consultas
     * (esquema, notas y recuperaciones) sin importar cuántos estudiantes sean.
     *
     * @param Grupo                $grupo
     * @param Collection<int, int> $estudianteIds
     * @return array{esquema: EsquemaCalificacion, resultados: array<int, array<string, mixed>>}|null
     *         Null si el módulo del grupo no tiene esquema activo.
     */
    public function resultadosGrupo(Grupo $grupo, Collection $estudianteIds): ?array
    {
        $esquema = EsquemaCalificacion::activoParaModuloGrupo($grupo->modulo_id, $grupo->id)
            ->with(['tiposNota' => fn ($q) => $q->orderBy('orden')])
            ->first();

        if (!$esquema) {
            return null;
        }

        $notas = NotaEstudiante::whereIn('estudiante_id', $estudianteIds)
            ->where('grupo_id', $grupo->id)
            ->where('modulo_id', $grupo->modulo_id)
            ->where('esquema_calificacion_id', $esquema->id)
            ->where('status', 1)
            ->get()
            ->groupBy('estudiante_id');

        $recuperaciones = RecuperacionNota::whereIn('estudiante_id', $estudianteIds)
            ->where('grupo_id', $grupo->id)
            ->where('modulo_id', $grupo->modulo_id)
            ->get()
            ->keyBy('estudiante_id');

        $cerrados        = $grupo->idsEstudiantesConNotasCerradas();
        $notaAprobatoria = (float) config('academico.nota_aprobatoria');

        $resultados = [];
        foreach ($estudianteIds->unique() as $estudianteId) {
            $resultados[$estudianteId] = $this->resultado(
                $esquema->tiposNota,
                $notas->get($estudianteId, collect()),
                $recuperaciones->get($estudianteId),
                $cerrados->contains((int) $estudianteId),
                $notaAprobatoria
            );
        }

        return ['esquema' => $esquema, 'resultados' => $resultados];
    }

    /**
     * Resultado de un estudiante en un módulo.
     *
     * @param Collection<int, \App\Models\Academico\TipoNotaEsquema> $tipos
     * @param Collection<int, NotaEstudiante>                        $notas Notas del estudiante en el esquema.
     * @param RecuperacionNota|null                                  $recuperacion
     * @param bool                                                   $cerrado Notas cerradas por el ciclo.
     * @param float                                                  $notaAprobatoria
     * @return array<string, mixed>
     */
    private function resultado(Collection $tipos, Collection $notas, ?RecuperacionNota $recuperacion, bool $cerrado, float $notaAprobatoria): array
    {
        $porTipo   = $notas->keyBy('tipo_nota_esquema_id');
        $notaFinal = round((float) $notas->sum('nota_ponderada'), 2);
        $completo  = $tipos->isNotEmpty() && $tipos->every(fn ($tipo) => $porTipo->has($tipo->id));

        $apruebaPorNotas = $completo && $notaFinal >= $notaAprobatoria;
        $reprobadoNotas  = $completo && !$apruebaPorNotas;
        $cuentaRecuperar = $reprobadoNotas && $recuperacion !== null;

        $notaDefinitiva = match (true) {
            $cuentaRecuperar => (float) $recuperacion->nota,
            $completo        => $notaFinal,
            default          => null,
        };
        $aprobado = $apruebaPorNotas || ($cuentaRecuperar && $notaDefinitiva >= $notaAprobatoria);

        return [
            'tipos_nota' => $tipos->map(function ($tipo) use ($porTipo) {
                $nota = $porTipo->get($tipo->id);

                return [
                    'id'             => $tipo->id,
                    'nombre_tipo'    => $tipo->nombre_tipo,
                    'peso'           => (float) $tipo->peso,
                    'nota'           => $nota ? (float) $nota->nota : null,
                    'nota_ponderada' => $nota ? (float) $nota->nota_ponderada : null,
                    'pendiente'      => !$nota,
                ];
            })->values()->all(),
            'nota_final'      => $notaFinal,
            'completo'        => $completo,
            'recuperacion'    => $recuperacion ? [
                'id'             => $recuperacion->id,
                'nota'           => (float) $recuperacion->nota,
                'fecha_registro' => $recuperacion->fecha_registro?->format('Y-m-d'),
                'observaciones'  => $recuperacion->observaciones,
            ] : null,
            'nota_definitiva' => $notaDefinitiva,
            'aprobado'        => $aprobado,
            'estado'          => match (true) {
                !$completo => self::ESTADO_PENDIENTE,
                $aprobado  => self::ESTADO_APROBADO,
                default    => self::ESTADO_REPROBADO,
            },
            'puede_recuperar' => $reprobadoNotas && !$cerrado,
            'notas_cerradas'  => $cerrado,
        ];
    }

    /**
     * Arma la estructura de la sábana para un estudiante y su matrícula.
     *
     * @param User      $estudiante
     * @param Matricula $matricula
     * @return array<string, mixed>
     */
    private function construir(User $estudiante, Matricula $matricula): array
    {
        $grupos           = $matricula->ciclo->grupos()->with('modulo')->get();
        $estudianteIds    = collect([$estudiante->id]);
        $modulosData      = [];
        $sumaDefinitivas  = 0;
        $modulosCompletos = 0;

        foreach ($grupos as $grupo) {
            $calculo = $this->resultadosGrupo($grupo, $estudianteIds);

            if (!$calculo) {
                continue;
            }

            $fila = $calculo['resultados'][$estudiante->id];

            if ($fila['completo']) {
                $modulosCompletos++;
                $sumaDefinitivas += $fila['nota_definitiva'];
            }

            $modulosData[] = [
                'modulo' => [
                    'id'     => $grupo->modulo->id,
                    'nombre' => $grupo->modulo->nombre,
                ],
                'grupo' => [
                    'id'     => $grupo->id,
                    'nombre' => $grupo->nombre,
                ],
                'esquema_calificacion' => [
                    'id'             => $calculo['esquema']->id,
                    'nombre_esquema' => $calculo['esquema']->nombre_esquema,
                ],
            ] + $fila;
        }

        return [
            'estudiante' => $this->datosEstudiante($estudiante),
            'curso' => [
                'id'     => $matricula->curso->id,
                'nombre' => $matricula->curso->nombre,
            ],
            'ciclo' => [
                'id'             => $matricula->ciclo->id,
                'nombre'         => $matricula->ciclo->nombre,
                'notas_cerradas' => $matricula->ciclo->notasCerradas(),
            ],
            'modulos'           => $modulosData,
            'promedio_general'  => $modulosCompletos > 0 ? round($sumaDefinitivas / $modulosCompletos, 2) : null,
            'total_modulos'     => count($modulosData),
            'modulos_completos' => $modulosCompletos,
        ];
    }

    /**
     * Datos de identificación del estudiante que viajan en las sábanas.
     *
     * @param User $estudiante
     * @return array<string, mixed>
     */
    private function datosEstudiante(User $estudiante): array
    {
        return [
            'id'        => $estudiante->id,
            'name'      => $estudiante->name,
            'email'     => $estudiante->email,
            'documento' => $estudiante->documento,
        ];
    }
}
