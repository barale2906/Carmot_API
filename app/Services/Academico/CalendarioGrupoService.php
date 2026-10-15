<?php

namespace App\Services\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Grupo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CalendarioGrupoService
 *
 * Gestiona el calendario cíclico de grupos/módulos:
 *  - fechasActivasGrupo: consulta si un grupo tiene ejecución activa o futura en algún ciclo.
 *  - calcularYAsignarFechas: asigna fechas y horas planeadas al pivot ciclo_grupo,
 *    usando el calendario existente para grupos en ejecución y calculando las
 *    sesiones reales (horarios + días no laborables) para grupos entre ejecuciones.
 *  - recalcularCiclo: calcularYAsignarFechas + persistencia en el ciclo.
 *  - previsualizarCurso: devuelve el panorama de módulos/grupos antes de crear un ciclo.
 *
 * Modos de fecha fin:
 *  - Automática (fecha_fin_automatica = true): cada módulo recibe el 100 % de
 *    sus horas y la fecha fin es la última sesión.
 *  - Fija (fecha_fin_automatica = false y fecha_fin definida): se busca el mayor
 *    factor F (0 < F <= 1) con el que la secuencia de módulos, cada uno con
 *    duración × F horas, termina en o antes de la fecha fijada. Si con el 100 %
 *    ya cabe, F = 1 y el ciclo termina antes (holgura). F por debajo del umbral
 *    (config academico.factor_ajuste_minimo) solo genera una advertencia.
 *
 * Fechas compartidas: cuando un ciclo toma las fechas de un grupo que ya se
 * ejecuta en otro ciclo, guarda ese ciclo en ciclo_grupo.ciclo_origen_id.
 * Solo las filas originales (ciclo_origen_id NULL) sirven de referencia, así
 * un recálculo del origen se propaga a las copias y nunca al revés. Un grupo
 * compartido es un tramo fijo: no avanza el cursor ni se ajusta con F.
 * Un ciclo solo comparte la ejecución de un ciclo que inicia antes o el mismo
 * día y que aún no ha terminado al iniciar este; así varias ejecuciones
 * independientes del mismo grupo (p. ej. un ciclo y su clon) no se adoptan
 * entre sí al recalcular.
 *
 * Calendario cíclico: los módulos rotan (1 → 2 → 3 → 1 …) y los grupos son fijos
 * aunque formen parte de varios ciclos. Un ciclo que inicia con módulos ya en
 * ejecución entra en el que está corriendo: la secuencia arranca en el tramo
 * compartido que empieza primero y los módulos anteriores pasan al final. Los
 * módulos libres se programan después de los tramos que los preceden, nunca en
 * paralelo; si aun así se cruzan (con un tramo del ciclo o con el mismo grupo en
 * otro ciclo) se advierte.
 */
class CalendarioGrupoService
{
    /** Iteraciones de la bisección del factor (precisión ≈ 0,0001). */
    private const ITERACIONES_FACTOR = 14;

    public function __construct(private readonly CalendarioLaboralService $calendarioLaboral)
    {
    }

    /**
     * Retorna las fechas activas o futuras más próximas de un grupo en cualquier ciclo.
     * "Activa o futura" = fecha_fin_grupo >= hoy y fecha_inicio_grupo no nulo.
     * Solo considera filas calculadas por su propio ciclo (no copias).
     * Opcionalmente excluye el ciclo que se está construyendo para evitar
     * que el registro recién creado se use como referencia de sí mismo.
     *
     * @param int      $grupoId
     * @param int|null $excluirCicloId
     * @return array{ciclo_id:int,fecha_inicio:string,fecha_fin:string,horas_planeadas:float|null}|null
     */
    public function fechasActivasGrupo(int $grupoId, ?int $excluirCicloId = null): ?array
    {
        $hoy = now()->toDateString();

        $query = DB::table('ciclo_grupo')
            ->where('grupo_id', $grupoId)
            ->whereNull('ciclo_origen_id')
            ->whereNotNull('fecha_inicio_grupo')
            ->where(function ($q) use ($hoy) {
                $q->whereNull('fecha_fin_grupo')
                  ->orWhere('fecha_fin_grupo', '>=', $hoy);
            })
            ->orderBy('fecha_inicio_grupo')
            ->select(['ciclo_id', 'fecha_inicio_grupo', 'fecha_fin_grupo', 'horas_planeadas']);

        if ($excluirCicloId !== null) {
            $query->where('ciclo_id', '!=', $excluirCicloId);
        }

        return $this->filaAFechas($query->first());
    }

    /**
     * Calcula y asigna las fechas y horas planeadas del pivot ciclo_grupo para
     * todos los grupos del ciclo (ver modos en la descripción de la clase).
     * Deja en el modelo, sin guardar, fecha_fin_teorica, factor_ajuste y
     * advertencia_ajuste.
     *
     * Devuelve la fecha_fin más tardía de los grupos (= fecha fin real del ciclo).
     *
     * @param Ciclo $ciclo
     * @return Carbon|null
     */
    public function calcularYAsignarFechas(Ciclo $ciclo): ?Carbon
    {
        if (! $ciclo->fecha_inicio) {
            return null;
        }

        $ciclo->refresh();
        $grupos = $ciclo->grupos()->with(['modulo', 'horarios'])->orderBy('ciclo_grupo.orden')->get();

        if ($grupos->isEmpty()) {
            return null;
        }

        $plan = $this->planificar($ciclo, $grupos);

        foreach ($plan['grupos'] as $grupoId => $fechas) {
            $ciclo->grupos()->updateExistingPivot($grupoId, [
                'fecha_inicio_grupo' => $fechas['fecha_inicio']->format('Y-m-d'),
                'fecha_fin_grupo'    => $fechas['fecha_fin']->format('Y-m-d'),
                'ciclo_origen_id'    => $fechas['ciclo_origen_id'],
                'horas_planeadas'    => $fechas['horas_planeadas'],
            ]);
        }

        $ciclo->fecha_fin_teorica  = $plan['fecha_fin_teorica'];
        $ciclo->factor_ajuste      = $plan['factor_ajuste'];
        $ciclo->advertencia_ajuste = $plan['advertencia'];

        return $plan['fecha_fin'];
    }

    /**
     * Recalcula el pivot del ciclo y persiste, sin disparar eventos, el
     * resultado del ajuste y, si la fecha fin es automática, fecha_fin y
     * duracion_dias. En modo fijo la fecha_fin del usuario se conserva.
     *
     * @param Ciclo $ciclo
     * @return Carbon|null fecha fin real calculada (última sesión del ciclo)
     */
    public function recalcularCiclo(Ciclo $ciclo): ?Carbon
    {
        $fechaFin = $this->calcularYAsignarFechas($ciclo);

        if ($fechaFin && $ciclo->fecha_fin_automatica) {
            $ciclo->fecha_fin     = $fechaFin;
            $ciclo->duracion_dias = (int) $ciclo->fecha_inicio->diffInDays($fechaFin);
        }

        if ($ciclo->isDirty()) {
            $ciclo->saveQuietly();
        }

        return $fechaFin;
    }

    /**
     * Previsualiza el calendario de módulos de un curso antes de crear un ciclo.
     * Para cada módulo (en su orden canónico) muestra sus grupos con:
     *  - con_fechas=true  → el grupo ya tiene ejecución activa o futura en otro ciclo.
     *  - con_fechas=false → el grupo está entre ejecuciones; las fechas se estiman
     *    con las sesiones reales del grupo, omitiendo días no laborables
     *    (listados en dias_omitidos).
     *
     * Con $fechaFin se simula el modo de fecha fin fija: se informa el factor de
     * ajuste, las horas planeadas por grupo, la fecha fin teórica (100 %) y la
     * intensidad semanal sugerida para dictar el curso completo.
     *
     * Con $sedeId solo se listan los grupos de esa sede (el ciclo solo admite grupos de su sede).
     *
     * @param int         $cursoId
     * @param string      $fechaInicio Fecha de inicio propuesta (Y-m-d).
     * @param string|null $fechaFin    Fecha fin fija propuesta (Y-m-d) o null.
     * @param int|null    $sedeId      Sede del ciclo; null = todas.
     * @return array
     */
    public function previsualizarCurso(int $cursoId, string $fechaInicio, ?string $fechaFin = null, ?int $sedeId = null): array
    {
        $curso = Curso::with([
            'modulosOrdenados.grupos' => fn ($q) => $q->when($sedeId, fn ($q) => $q->where('grupos.sede_id', $sedeId)),
            'modulosOrdenados.grupos.horarios',
            'modulosOrdenados.grupos.modulo',
        ])->findOrFail($cursoId);

        $cursor0     = Carbon::parse($fechaInicio);
        $noLab       = $this->noLaborablesMemo($cursor0);
        $existentes  = $curso->modulosOrdenados->flatMap->grupos
            ->mapWithKeys(fn ($grupo) => [$grupo->id => $this->fechasActivasGrupo($grupo->id)])
            ->all();
        $simular     = fn (float $factor) => $this->simularPreview($curso, $cursor0, $factor, $noLab, $existentes);
        $teorico  = $simular(1.0);
        $factor   = null;
        $preview  = $teorico;
        $advertencia = null;

        if ($fechaFin) {
            $limite = Carbon::parse($fechaFin);
            [$factor, $preview] = $this->buscarFactor($simular, $teorico, $limite);
            $advertencia = $this->advertencia($factor, $limite, $teorico['fin'], $teorico['secuencia'], []);
            $preview ??= $teorico;
        }

        return [
            'curso_id'               => $curso->id,
            'curso_nombre'           => $curso->nombre,
            'fecha_inicio'           => $fechaInicio,
            'fecha_fin'              => $fechaFin,
            'fecha_fin_teorica'      => $teorico['fin']?->format('Y-m-d'),
            'factor_ajuste'          => $factor,
            'porcentaje_horas'       => $factor !== null ? round($factor * 100, 2) : null,
            'horas_semana_sugeridas' => $factor !== null && $factor < 1
                ? $this->horasSemanaSugeridas($teorico['secuencia'], $factor)
                : null,
            'advertencia'            => $advertencia,
            'modulos'                => $preview['resultado'],
        ];
    }

    // -------------------------------------------------------------------------
    // Planificación
    // -------------------------------------------------------------------------

    /**
     * Arma el plan de fechas del ciclo: tramos compartidos (fijos) y grupos
     * libres en secuencia, ajustados por el factor en modo fecha fin fija.
     *
     * @param Ciclo                  $ciclo
     * @param Collection<int, Grupo> $grupos Ordenados, con modulo y horarios
     * @return array{grupos: array<int, array>, fecha_fin: Carbon|null, fecha_fin_teorica: Carbon|null, factor_ajuste: float|null, advertencia: string|null}
     */
    private function planificar(Ciclo $ciclo, Collection $grupos): array
    {
        $cursor0     = Carbon::parse($ciclo->fecha_inicio);
        $noLab       = $this->noLaborablesMemo($cursor0);
        $compartidos = [];
        $libres      = [];

        foreach ($grupos as $grupo) {
            $existentes = $this->fechasDeOrigen($grupo->pivot->ciclo_origen_id, $grupo->id)
                ?? $this->ejecucionCompartible($ciclo, $grupo->id);

            if ($existentes) {
                $compartidos[$grupo->id] = [
                    'grupo'           => $grupo,
                    'fecha_inicio'    => Carbon::parse($existentes['fecha_inicio']),
                    'fecha_fin'       => Carbon::parse($existentes['fecha_fin']),
                    'ciclo_origen_id' => $existentes['ciclo_id'],
                    'horas_planeadas' => $existentes['horas_planeadas'] ?? (float) ($grupo->modulo->duracion ?? 0),
                ];
            } else {
                $libres[] = $grupo;
            }
        }

        $pasos     = $this->secuenciaCiclica($grupos, $compartidos);
        $simular   = fn (float $factor) => $this->simularLibres($pasos, $cursor0, $factor, $noLab);
        $teorico   = $simular(1.0);
        $factor    = null;
        $resultado = $teorico;
        $advertencia = null;

        $finCompartidos = collect($compartidos)->max(fn ($c) => $c['fecha_fin']);
        $finTeorica     = $this->maxFecha($teorico['fin'], $finCompartidos);

        if (! $ciclo->fecha_fin_automatica && $ciclo->fecha_fin) {
            $limite = Carbon::parse($ciclo->fecha_fin);
            [$factor, $ajustado] = $this->buscarFactor($simular, $teorico, $limite);
            $resultado   = $ajustado ?? $teorico;
            $advertencia = $this->advertencia($factor, $limite, $teorico['fin'], $teorico['secuencia'], $compartidos);
        }

        $cruces = $this->cruces($ciclo, $resultado['resultado'], $compartidos, $libres);
        if ($cruces) {
            $advertencia = trim(($advertencia ?? '') . ' ' . implode(' ', $cruces));
        }

        $plan = [];
        foreach ($resultado['resultado'] as $grupoId => $rango) {
            $plan[$grupoId] = [
                'fecha_inicio'    => $rango['fecha_inicio'],
                'fecha_fin'       => $rango['fecha_fin'],
                'ciclo_origen_id' => null,
                'horas_planeadas' => $rango['horas_planeadas'],
            ];
        }
        foreach ($compartidos as $grupoId => $compartido) {
            $plan[$grupoId] = collect($compartido)->except('grupo')->all();
        }

        return [
            'grupos'            => $plan,
            'fecha_fin'         => $this->maxFecha($resultado['fin'], $finCompartidos),
            'fecha_fin_teorica' => $finTeorica,
            'factor_ajuste'     => $factor,
            'advertencia'       => $advertencia,
        ];
    }

    /**
     * Simula la secuencia cíclica con un factor. Un tramo compartido es fijo: solo
     * lleva el cursor al día siguiente de su fin. Cada grupo libre cubre
     * duración × factor horas desde el cursor; el siguiente arranca el día después
     * de su última sesión. Los grupos sin horarios o sin duración se omiten.
     *
     * @param array<int, array{grupo: Grupo, compartido: array|null}> $pasos Ver secuenciaCiclica()
     * @return array{resultado: array<int, array>, fin: Carbon|null, secuencia: array<int, Grupo>}
     */
    private function simularLibres(array $pasos, Carbon $cursor0, float $factor, \Closure $noLab): array
    {
        $cursor    = $cursor0->copy();
        $resultado = [];
        $secuencia = [];
        $fin       = null;

        foreach ($pasos as ['grupo' => $grupo, 'compartido' => $compartido]) {
            if ($compartido) {
                $cursor = $this->maxFecha($cursor, $compartido['fecha_fin']->copy()->addDay());
                continue;
            }

            $horas = (float) ($grupo->modulo->duracion ?? 0) * $factor;
            $rango = $horas > 0
                ? $this->calendarioLaboral->calcularRangoGrupo($grupo, $cursor, $horas, $noLab($grupo->sede_id))
                : null;

            if (! $rango) {
                continue;
            }

            $resultado[$grupo->id] = $rango + ['horas_planeadas' => round($horas, 2)];
            $secuencia[]           = $grupo;
            $cursor                = $rango['fecha_fin']->copy()->addDay();
            $fin                   = $rango['fecha_fin'];
        }

        return ['resultado' => $resultado, 'fin' => $fin, 'secuencia' => $secuencia];
    }

    /**
     * Simula la previsualización de un curso con un factor. Recorre los módulos en
     * secuencia cíclica (ver secuenciaCiclica): arranca en el módulo cuya ejecución
     * existente empieza primero. Un módulo con ejecución existente lleva el cursor al
     * día siguiente de su fin; si no, el primer grupo entre ejecuciones determina
     * cuándo avanza el cursor y esos grupos forman la secuencia usada para el ajuste.
     * Los módulos se devuelven en su orden canónico.
     *
     * @param array<int, array|null> $existentesPorGrupo Fechas activas de cada grupo (precalculadas)
     * @return array{resultado: array<int, array>, fin: Carbon|null, secuencia: array<int, Grupo>}
     */
    private function simularPreview(Curso $curso, Carbon $cursor0, float $factor, \Closure $noLab, array $existentesPorGrupo): array
    {
        $cursor    = $cursor0->copy();
        $modulos   = [];
        $secuencia = [];
        $fin       = null;

        $modulosCurso = $curso->modulosOrdenados->values();
        $inicioExistente = $modulosCurso->map(fn ($modulo) => $modulo->grupos
            ->map(fn ($g) => $existentesPorGrupo[$g->id]['fecha_inicio'] ?? null)
            ->filter()->min());
        $rotacion = $inicioExistente->filter()->sort()->keys()->first() ?? 0;
        $indices  = $modulosCurso->keys()->all();
        $orden    = array_merge(array_slice($indices, $rotacion), array_slice($indices, 0, $rotacion));

        foreach ($orden as $indice) {
            $modulo         = $modulosCurso[$indice];
            $gruposModulo   = [];
            $primerSinFecha = null;
            $finExistente   = null;
            $duracion       = (float) ($modulo->duracion ?? 0);

            foreach ($modulo->grupos as $grupo) {
                $horasSemana = (int) $grupo->horarios->sum('duracion_horas');
                $semanas     = ($horasSemana > 0 && $duracion > 0) ? (int) ceil($duracion / $horasSemana) : 0;
                $existentes  = $existentesPorGrupo[$grupo->id] ?? null;

                if ($existentes) {
                    if ($existentes['fecha_inicio'] === $inicioExistente[$indice]) {
                        $finExistente = Carbon::parse($existentes['fecha_fin']);
                    }
                    $gruposModulo[] = [
                        'grupo_id'            => $grupo->id,
                        'grupo_nombre'        => $grupo->nombre,
                        'con_fechas'          => true,
                        'fecha_inicio'        => $existentes['fecha_inicio'],
                        'fecha_fin'           => $existentes['fecha_fin'],
                        'ciclo_referencia_id' => $existentes['ciclo_id'],
                        'horas_semana'        => $horasSemana,
                        'semanas_estimadas'   => $semanas,
                        'sesiones'            => null,
                        'horas_planeadas'     => $existentes['horas_planeadas'] ?? $duracion,
                        'dias_omitidos'       => [],
                    ];
                    continue;
                }

                $horas = $duracion * $factor;
                $rango = $horas > 0
                    ? $this->calendarioLaboral->calcularRangoGrupo($grupo, $cursor, $horas, $noLab($grupo->sede_id))
                    : null;

                $gruposModulo[] = [
                    'grupo_id'            => $grupo->id,
                    'grupo_nombre'        => $grupo->nombre,
                    'con_fechas'          => false,
                    'fecha_inicio'        => ($rango['fecha_inicio'] ?? $cursor)->format('Y-m-d'),
                    'fecha_fin'           => $rango ? $rango['fecha_fin']->format('Y-m-d') : null,
                    'ciclo_referencia_id' => null,
                    'horas_semana'        => $horasSemana,
                    'semanas_estimadas'   => $semanas,
                    'sesiones'            => $rango['sesiones'] ?? null,
                    'horas_planeadas'     => $rango ? round($horas, 2) : null,
                    'dias_omitidos'       => $rango['dias_omitidos'] ?? [],
                ];

                // El primer grupo sin slot determina cuándo avanza el cursor
                if ($primerSinFecha === null && $rango) {
                    $primerSinFecha = $rango['fecha_fin'];
                    $secuencia[]    = $grupo;
                }
            }

            if ($finExistente) {
                // Tramo en ejecución: fijo, los módulos siguientes arrancan después
                $cursor = $this->maxFecha($cursor, $finExistente->copy()->addDay());
            } elseif ($primerSinFecha) {
                $cursor = $primerSinFecha->copy()->addDay();
                $fin    = $primerSinFecha;
            }

            $modulos[$indice] = [
                'modulo_id'     => $modulo->id,
                'modulo_nombre' => $modulo->nombre,
                'orden'         => $modulo->pivot->orden,
                'duracion'      => $modulo->duracion,
                'grupos'        => $gruposModulo,
            ];
        }

        ksort($modulos);

        return ['resultado' => array_values($modulos), 'fin' => $fin, 'secuencia' => $secuencia];
    }

    /**
     * Busca por bisección el mayor factor (0 < F <= 1, 4 decimales) con el que
     * la simulación termina en o antes de $limite. Las horas se simulan sin
     * redondear para que el factor reportado sea coherente con las fechas.
     *
     * @param \Closure $simular  fn (float $factor): array{resultado, fin, secuencia}
     * @param array    $teorico  Simulación con factor 1
     * @param Carbon   $limite
     * @return array{0: float|null, 1: array|null} [factor, simulación ajustada]; [null, null] si no cabe
     */
    private function buscarFactor(\Closure $simular, array $teorico, Carbon $limite): array
    {
        if ($teorico['fin'] === null || $teorico['fin']->lte($limite)) {
            return [1.0, $teorico];
        }

        $bajo  = 0.0;
        $alto  = 1.0;
        $mejor = null;

        for ($i = 0; $i < self::ITERACIONES_FACTOR; $i++) {
            $medio = ($bajo + $alto) / 2;
            $fin   = $simular($medio)['fin'];

            if ($fin !== null && $fin->lte($limite)) {
                $mejor = $medio;
                $bajo  = $medio;
            } else {
                $alto = $medio;
            }
        }

        if ($mejor === null) {
            return [null, null];
        }

        // Preferir un factor "limpio" (2 decimales) si también cabe: evita que un
        // 80 % exacto quede como 79,99 % y dispare la advertencia del umbral.
        $redondeado = round($mejor, 2);
        if ($redondeado > $mejor) {
            $simulacion = $simular($redondeado);
            if ($simulacion['fin'] !== null && $simulacion['fin']->lte($limite)) {
                return [$redondeado, $simulacion];
            }
        }

        $factor = floor($mejor * 10000) / 10000;

        return $factor > 0 ? [$factor, $simular($factor)] : [null, null];
    }

    /**
     * Construye la advertencia del ajuste en modo fecha fin fija, o null si no hay nada que advertir.
     *
     * @param float|null        $factor
     * @param Carbon            $limite     Fecha fin fijada
     * @param Carbon|null       $finTeorica Fin de la secuencia con el 100 % de las horas
     * @param array<int, Grupo> $secuencia  Grupos que se ajustan
     * @param array             $compartidos Tramos fijos de grupos compartidos
     * @return string|null
     */
    private function advertencia(?float $factor, Carbon $limite, ?Carbon $finTeorica, array $secuencia, array $compartidos): ?string
    {
        $mensajes = [];
        $fechaLimite = $limite->format('d/m/Y');

        if ($factor === null) {
            $mensajes[] = "La fecha fin fijada ({$fechaLimite}) no alcanza para dictar al menos una sesión por módulo. "
                . 'Los grupos se programaron con el 100 % de las horas y terminan el ' . $finTeorica?->format('d/m/Y') . '.';
        } elseif ($factor < config('academico.factor_ajuste_minimo')) {
            $total     = collect($secuencia)->sum(fn ($g) => (float) ($g->modulo->duracion ?? 0));
            $planeadas = round($total * $factor, 1);
            $mensajes[] = sprintf(
                'Con la fecha fin fijada (%s) se dictará el %s %% de las horas (%s de %s h), por debajo del mínimo de %s %%. '
                . 'Para dictar el 100 %% se necesitan aprox. %s h/semana o terminar el %s.',
                $fechaLimite,
                $this->numero($factor * 100),
                $this->numero($planeadas),
                $this->numero($total),
                $this->numero(config('academico.factor_ajuste_minimo') * 100),
                $this->numero($this->horasSemanaSugeridas($secuencia, $factor)),
                $finTeorica?->format('d/m/Y')
            );
        }

        foreach ($compartidos as $compartido) {
            if ($compartido['fecha_fin']->gt($limite)) {
                $mensajes[] = "El grupo '{$compartido['grupo']->nombre}' comparte fechas con el ciclo #{$compartido['ciclo_origen_id']} "
                    . 'y termina el ' . $compartido['fecha_fin']->format('d/m/Y') . ', después de la fecha fin fijada.';
            }
        }

        return $mensajes ? implode(' ', $mensajes) : null;
    }

    /**
     * Intensidad semanal uniforme que permitiría dictar el 100 % de las horas
     * en el tiempo que ocupa la secuencia ajustada:
     * h* = Σ duración / (F × Σ (duración_i / horas_semana_i)).
     *
     * @param array<int, Grupo> $secuencia
     * @param float             $factor
     * @return float|null
     */
    private function horasSemanaSugeridas(array $secuencia, float $factor): ?float
    {
        $total   = 0.0;
        $semanas = 0.0;

        foreach ($secuencia as $grupo) {
            $duracion    = (float) ($grupo->modulo->duracion ?? 0);
            $horasSemana = (float) $grupo->horarios->sum('duracion_horas');

            if ($horasSemana > 0) {
                $total   += $duracion;
                $semanas += $duracion / $horasSemana;
            }
        }

        return ($semanas > 0 && $factor > 0) ? round($total / ($factor * $semanas), 1) : null;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Ordena los grupos del ciclo en secuencia cíclica: si hay tramos compartidos,
     * la secuencia arranca en el que empieza primero (el módulo que está corriendo
     * cuando inicia el ciclo) y los grupos anteriores pasan al final.
     *
     * @param Collection<int, Grupo> $grupos      Ordenados por ciclo_grupo.orden
     * @param array<int, array>      $compartidos Tramos fijos por grupo_id
     * @return array<int, array{grupo: Grupo, compartido: array|null}>
     */
    private function secuenciaCiclica(Collection $grupos, array $compartidos): array
    {
        $lista = $grupos->values()->all();

        if ($compartidos) {
            $primero = collect($compartidos)->sortBy(fn ($c) => $c['fecha_inicio']->timestamp)->keys()->first();
            $pos     = collect($lista)->search(fn (Grupo $g) => $g->id === $primero);
            $lista   = array_merge(array_slice($lista, $pos), array_slice($lista, 0, $pos));
        }

        return array_map(fn (Grupo $g) => ['grupo' => $g, 'compartido' => $compartidos[$g->id] ?? null], $lista);
    }

    /**
     * Mensajes de cruce de los grupos libres programados: con un tramo compartido
     * del mismo ciclo o con el mismo grupo dictándose en otro ciclo en esas fechas.
     *
     * @param Ciclo              $ciclo
     * @param array<int, array>  $resultado   Rangos de los grupos libres por grupo_id
     * @param array<int, array>  $compartidos Tramos fijos por grupo_id
     * @param array<int, Grupo>  $libres
     * @return array<int, string>
     */
    private function cruces(Ciclo $ciclo, array $resultado, array $compartidos, array $libres): array
    {
        $mensajes = [];
        $nombres  = collect($libres)->mapWithKeys(fn (Grupo $g) => [$g->id => $g->nombre]);

        foreach ($resultado as $grupoId => $rango) {
            $nombre = $nombres[$grupoId] ?? "#{$grupoId}";

            foreach ($compartidos as $compartido) {
                if ($rango['fecha_inicio']->lte($compartido['fecha_fin']) && $rango['fecha_fin']->gte($compartido['fecha_inicio'])) {
                    $mensajes[] = "El módulo del grupo '{$nombre}' se cruza con el del grupo '{$compartido['grupo']->nombre}' "
                        . '(' . $compartido['fecha_inicio']->format('d/m/Y') . ' – ' . $compartido['fecha_fin']->format('d/m/Y') . ').';
                }
            }

            $otro = DB::table('ciclo_grupo')
                ->join('ciclos', 'ciclos.id', '=', 'ciclo_grupo.ciclo_id')
                ->whereNull('ciclos.deleted_at')
                ->where('ciclo_grupo.grupo_id', $grupoId)
                ->where('ciclo_grupo.ciclo_id', '!=', $ciclo->id)
                ->whereNull('ciclo_grupo.ciclo_origen_id')
                ->whereNotNull('ciclo_grupo.fecha_inicio_grupo')
                ->where('ciclo_grupo.fecha_inicio_grupo', '<=', $rango['fecha_fin']->toDateString())
                ->where('ciclo_grupo.fecha_fin_grupo', '>=', $rango['fecha_inicio']->toDateString())
                ->first(['ciclos.nombre', 'ciclo_grupo.fecha_inicio_grupo', 'ciclo_grupo.fecha_fin_grupo']);

            if ($otro) {
                $mensajes[] = "El grupo '{$nombre}' ya se dicta en el ciclo {$otro->nombre} del "
                    . Carbon::parse($otro->fecha_inicio_grupo)->format('d/m/Y') . ' al '
                    . Carbon::parse($otro->fecha_fin_grupo)->format('d/m/Y') . ': las fechas se cruzan.';
            }
        }

        return $mensajes;
    }

    /**
     * Ejecución del grupo en otro ciclo que este ciclo debe compartir: fila
     * original, activa o futura, que no termina antes del inicio del ciclo y
     * pertenece a un ciclo que inicia antes o el mismo día.
     *
     * @param Ciclo $ciclo
     * @param int   $grupoId
     * @return array{ciclo_id:int,fecha_inicio:string,fecha_fin:string,horas_planeadas:float|null}|null
     */
    private function ejecucionCompartible(Ciclo $ciclo, int $grupoId): ?array
    {
        $inicio = Carbon::parse($ciclo->fecha_inicio)->toDateString();
        $desde  = max(now()->toDateString(), $inicio);

        return $this->filaAFechas(DB::table('ciclo_grupo')
            ->join('ciclos', 'ciclos.id', '=', 'ciclo_grupo.ciclo_id')
            ->where('ciclo_grupo.grupo_id', $grupoId)
            ->where('ciclo_grupo.ciclo_id', '!=', $ciclo->id)
            ->whereNull('ciclo_grupo.ciclo_origen_id')
            ->whereNotNull('ciclo_grupo.fecha_inicio_grupo')
            ->where(function ($q) use ($desde) {
                $q->whereNull('ciclo_grupo.fecha_fin_grupo')
                  ->orWhere('ciclo_grupo.fecha_fin_grupo', '>=', $desde);
            })
            ->where('ciclos.fecha_inicio', '<=', $inicio)
            ->orderBy('ciclo_grupo.fecha_inicio_grupo')
            ->first([
                'ciclo_grupo.ciclo_id', 'ciclo_grupo.fecha_inicio_grupo',
                'ciclo_grupo.fecha_fin_grupo', 'ciclo_grupo.horas_planeadas',
            ]));
    }

    /**
     * Fechas del grupo en el ciclo origen del que este ciclo las copió.
     * Null si no hay origen o si el origen ya no tiene el grupo con fechas.
     *
     * @param int|null $cicloOrigenId
     * @param int      $grupoId
     * @return array{ciclo_id:int,fecha_inicio:string,fecha_fin:string,horas_planeadas:float|null}|null
     */
    private function fechasDeOrigen(?int $cicloOrigenId, int $grupoId): ?array
    {
        if ($cicloOrigenId === null) {
            return null;
        }

        return $this->filaAFechas(DB::table('ciclo_grupo')
            ->where('ciclo_id', $cicloOrigenId)
            ->where('grupo_id', $grupoId)
            ->whereNotNull('fecha_inicio_grupo')
            ->whereNotNull('fecha_fin_grupo')
            ->first(['ciclo_id', 'fecha_inicio_grupo', 'fecha_fin_grupo', 'horas_planeadas']));
    }

    /**
     * Convierte una fila de ciclo_grupo al formato de fechas compartidas.
     */
    private function filaAFechas(?object $fila): ?array
    {
        return $fila ? [
            'ciclo_id'        => $fila->ciclo_id,
            'fecha_inicio'    => $fila->fecha_inicio_grupo,
            'fecha_fin'       => $fila->fecha_fin_grupo,
            'horas_planeadas' => $fila->horas_planeadas !== null ? (float) $fila->horas_planeadas : null,
        ] : null;
    }

    /**
     * Devuelve un cargador de días no laborables por sede que consulta una
     * sola vez por sede durante un cálculo (la bisección simula muchas veces).
     *
     * @return \Closure(int|null): array<string, string>
     */
    private function noLaborablesMemo(Carbon $desde): \Closure
    {
        $memo = [];

        return function (?int $sedeId) use (&$memo, $desde): array {
            return $memo[$sedeId ?? 0] ??= $this->calendarioLaboral->cargarNoLaborables($desde, $sedeId);
        };
    }

    /**
     * La mayor de dos fechas opcionales.
     */
    private function maxFecha(?Carbon $a, ?Carbon $b): ?Carbon
    {
        if ($a === null || $b === null) {
            return $a ?? $b;
        }

        return $a->gt($b) ? $a : $b;
    }

    /**
     * Formato numérico colombiano con un decimal máximo (ej. 6,4).
     */
    private function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 1, ',', '.'), '0'), ',');
    }
}
