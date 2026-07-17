<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreCicloRequest;
use App\Http\Requests\Api\Academico\UpdateCicloRequest;
use App\Http\Resources\Api\Academico\CicloResource;
use App\Models\Academico\Ciclo;
use App\Services\Academico\CalendarioGrupoService;
use App\Traits\HasActiveStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CicloController extends Controller
{
    use HasActiveStatus;

    /**
     * Constructor del controlador.
     */
    public function __construct(private readonly CalendarioGrupoService $calendario)
    {
        $this->middleware('permission:aca_ciclos')->only(['index', 'show', 'filters', 'statistics', 'previsualizarCalendario']);
        $this->middleware('permission:aca_cicloCrear')->only(['store']);
        $this->middleware('permission:aca_cicloEditar')->only([
            'update', 'asignarGrupos', 'desasignarGrupo',
            'calcularFechaFin', 'actualizarOrdenGrupo', 'reordenarGrupos',
        ]);
        $this->middleware('permission:aca_cicloInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista de los ciclos.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'status', 'sede_id', 'curso_id', 'include_trashed', 'only_trashed',
            'inscritos_min', 'inscritos_max', 'inscritos_range',
        ]);

        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sede', 'curso', 'grupos'];

        $includeCounts = $request->has('with') && (
            str_contains($request->with, 'sede') ||
            str_contains($request->with, 'curso') ||
            str_contains($request->with, 'grupos')
        );

        $ciclos = Ciclo::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CicloResource::collection($ciclos),
            'meta' => [
                'current_page' => $ciclos->currentPage(),
                'last_page'    => $ciclos->lastPage(),
                'per_page'     => $ciclos->perPage(),
                'total'        => $ciclos->total(),
                'from'         => $ciclos->firstItem(),
                'to'           => $ciclos->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena un nuevo ciclo y asigna sus grupos respetando el calendario cíclico.
     * Si un grupo tiene ejecución activa en otro ciclo, reutiliza esas fechas.
     * Los grupos entre ejecuciones reciben fechas calculadas desde la duración del módulo.
     *
     * @param StoreCicloRequest $request
     * @return JsonResponse
     */
    public function store(StoreCicloRequest $request): JsonResponse
    {
        $ciclo = Ciclo::create([
            'sede_id'             => $request->sede_id,
            'curso_id'            => $request->curso_id,
            'nombre'              => $request->nombre,
            'descripcion'         => $request->descripcion,
            'fecha_inicio'        => $request->fecha_inicio,
            'fecha_fin'           => $request->fecha_fin,
            'fecha_fin_automatica' => $request->fecha_fin_automatica ?? true,
            'inscritos'           => $request->inscritos,
            'status'              => $request->status ?? 1,
        ]);

        if ($request->has('grupos') && is_array($request->grupos)) {
            $gruposConOrden = [];

            if ($request->boolean('con_orden')) {
                $ciclo->asignarGruposConOrden($request->grupos);
            } else {
                foreach ($request->grupos as $index => $grupoId) {
                    $gruposConOrden[] = ['grupo_id' => $grupoId, 'orden' => $index + 1];
                }
                $ciclo->asignarGruposConOrden($gruposConOrden);
            }

            if ($ciclo->fecha_fin_automatica) {
                $this->aplicarFechasYGuardar($ciclo);
            }
        }

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Ciclo creado exitosamente.',
            'data'    => new CicloResource($ciclo),
        ], 201);
    }

    /**
     * Muestra el ciclo especificado.
     *
     * @param Request $request
     * @param Ciclo   $ciclo
     * @return JsonResponse
     */
    public function show(Request $request, Ciclo $ciclo): JsonResponse
    {
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sede', 'curso', 'grupos'];

        if (in_array('aplazamientos', $relations)) {
            $relations[] = 'aplazamientos.tipoAplazamiento';
            $relations[] = 'aplazamientos.user';
        }
        if (in_array('aplazamientoActivo', $relations)) {
            $relations[] = 'aplazamientoActivo.tipoAplazamiento';
        }

        $ciclo->load($relations);
        $ciclo->loadCount(['sede', 'curso', 'grupos']);

        return response()->json([
            'data' => new CicloResource($ciclo),
        ]);
    }

    /**
     * Actualiza el ciclo especificado.
     *
     * @param UpdateCicloRequest $request
     * @param Ciclo              $ciclo
     * @return JsonResponse
     */
    public function update(UpdateCicloRequest $request, Ciclo $ciclo): JsonResponse
    {
        $ciclo->update($request->only([
            'sede_id', 'curso_id', 'nombre', 'descripcion',
            'fecha_inicio', 'fecha_fin', 'fecha_fin_automatica',
            'inscritos', 'status',
        ]));

        if ($request->has('grupos')) {
            if (is_array($request->grupos)) {
                $ciclo->grupos()->sync($request->grupos);
            } else {
                $ciclo->grupos()->detach();
            }

            if ($ciclo->fecha_fin_automatica) {
                $this->aplicarFechasYGuardar($ciclo);
            }
        }

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Ciclo actualizado exitosamente.',
            'data'    => new CicloResource($ciclo),
        ]);
    }

    /**
     * Elimina el ciclo (soft delete). No permite eliminación si tiene grupos asociados.
     *
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function destroy(Ciclo $ciclo): JsonResponse
    {
        if ($ciclo->grupos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el ciclo porque tiene grupos asociados.',
            ], 422);
        }

        $ciclo->delete();

        return response()->json([
            'message' => 'Ciclo eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un ciclo eliminado.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $ciclo = Ciclo::onlyTrashed()->findOrFail($id);
        $ciclo->restore();

        return response()->json([
            'message' => 'Ciclo restaurado exitosamente.',
            'data'    => new CicloResource($ciclo->load(['sede', 'curso', 'grupos'])),
        ]);
    }

    /**
     * Elimina permanentemente un ciclo.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $ciclo = Ciclo::onlyTrashed()->findOrFail($id);

        if ($ciclo->grupos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el ciclo porque tiene grupos asociados.',
            ], 422);
        }

        $ciclo->forceDelete();

        return response()->json([
            'message' => 'Ciclo eliminado permanentemente.',
        ]);
    }

    /**
     * Lista los ciclos eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'sede_id', 'curso_id']);
        $filters['only_trashed'] = true;

        $relations    = $request->has('with') ? explode(',', $request->with) : ['sede', 'curso', 'grupos'];
        $includeCounts = $request->has('with') && (
            str_contains($request->with, 'sede') ||
            str_contains($request->with, 'curso') ||
            str_contains($request->with, 'grupos')
        );

        $ciclos = Ciclo::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CicloResource::collection($ciclos),
            'meta' => [
                'current_page' => $ciclos->currentPage(),
                'last_page'    => $ciclos->lastPage(),
                'per_page'     => $ciclos->perPage(),
                'total'        => $ciclos->total(),
                'from'         => $ciclos->firstItem(),
                'to'           => $ciclos->lastItem(),
            ],
        ]);
    }

    /**
     * Opciones de filtros disponibles para ciclos.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        $sedes  = \App\Models\Configuracion\Sede::select('id', 'nombre')->get();
        $cursos = \App\Models\Academico\Curso::select('id', 'nombre')->get();

        return response()->json([
            'data' => [
                'status_options' => self::getActiveStatusOptions(),
                'sedes'          => $sedes,
                'cursos'         => $cursos,
            ],
        ]);
    }

    /**
     * Estadísticas generales de ciclos.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total'     => Ciclo::count(),
                'activos'   => Ciclo::whereNull('deleted_at')->count(),
                'eliminados' => Ciclo::onlyTrashed()->count(),
            ],
            'por_status' => [
                'activos'   => Ciclo::where('status', 1)->count(),
                'inactivos' => Ciclo::where('status', 0)->count(),
            ],
            'por_sede' => Ciclo::with('sede')
                ->selectRaw('sede_id, COUNT(*) as total')
                ->groupBy('sede_id')
                ->get()
                ->map(fn ($item) => ['sede' => $item->sede->nombre ?? 'Sin sede', 'total' => $item->total]),
            'por_curso' => Ciclo::with('curso')
                ->selectRaw('curso_id, COUNT(*) as total')
                ->groupBy('curso_id')
                ->get()
                ->map(fn ($item) => ['curso' => $item->curso->nombre ?? 'Sin curso', 'total' => $item->total]),
            'con_grupos'  => Ciclo::whereHas('grupos')->count(),
            'sin_grupos'  => Ciclo::whereDoesntHave('grupos')->count(),
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * Previsualiza el calendario de módulos de un curso antes de crear un ciclo.
     * Muestra qué grupos tienen ejecución activa (con_fechas=true) y cuáles están
     * entre ejecuciones (con_fechas=false) para que el frontend los presente
     * al usuario con la posibilidad de reordenar.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function previsualizarCalendario(Request $request): JsonResponse
    {
        $request->validate([
            'curso_id'    => 'required|integer|exists:cursos,id',
            'fecha_inicio' => 'required|date',
        ]);

        $resultado = $this->calendario->previsualizarCurso(
            (int) $request->curso_id,
            $request->fecha_inicio
        );

        return response()->json(['data' => $resultado]);
    }

    /**
     * Asigna grupos a un ciclo respetando el calendario cíclico.
     *
     * @param Request $request
     * @param Ciclo   $ciclo
     * @return JsonResponse
     */
    public function asignarGrupos(Request $request, Ciclo $ciclo): JsonResponse
    {
        $request->validate([
            'grupos'           => 'required|array',
            'grupos.*'         => 'exists:grupos,id',
        ]);

        if ($request->boolean('con_orden')) {
            $request->validate([
                'grupos.*.grupo_id' => 'required|exists:grupos,id',
                'grupos.*.orden'    => 'required|integer|min:1',
            ]);
            $ciclo->asignarGruposConOrden($request->grupos);
        } else {
            $siguienteOrden = $ciclo->getSiguienteOrden();
            $gruposConOrden = [];
            foreach ($request->grupos as $grupoId) {
                $gruposConOrden[] = ['grupo_id' => $grupoId, 'orden' => $siguienteOrden++];
            }
            $ciclo->asignarGruposConOrden($gruposConOrden);
        }

        if ($ciclo->fecha_fin_automatica) {
            $this->aplicarFechasYGuardar($ciclo);
        }

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Grupos asignados exitosamente.',
            'data'    => new CicloResource($ciclo),
        ]);
    }

    /**
     * Desasigna un grupo del ciclo y recalcula la fecha de fin.
     *
     * @param Request $request
     * @param Ciclo   $ciclo
     * @return JsonResponse
     */
    public function desasignarGrupo(Request $request, Ciclo $ciclo): JsonResponse
    {
        $request->validate([
            'grupo_id' => 'required|exists:grupos,id',
        ]);

        $ciclo->grupos()->detach($request->grupo_id);

        if ($ciclo->fecha_fin_automatica) {
            $this->aplicarFechasYGuardar($ciclo);
        }

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Grupo desasignado exitosamente.',
            'data'    => new CicloResource($ciclo),
        ]);
    }

    /**
     * Calcula y actualiza la fecha de fin del ciclo según el calendario cíclico.
     *
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function calcularFechaFin(Ciclo $ciclo): JsonResponse
    {
        $fechaFin = $this->calendario->calcularYAsignarFechas($ciclo);

        if (! $fechaFin) {
            return response()->json([
                'message' => 'No se pudo calcular la fecha de fin. Verifique que el ciclo tenga fecha de inicio y grupos con horarios configurados.',
            ], 422);
        }

        $ciclo->fecha_fin     = $fechaFin;
        $ciclo->duracion_dias = $ciclo->fecha_inicio->diffInDays($fechaFin);
        $ciclo->saveQuietly();

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Fecha de fin calculada exitosamente.',
            'data'    => new CicloResource($ciclo),
        ]);
    }

    /**
     * Información detallada del cálculo de fechas del ciclo.
     *
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function informacionCalculo(Ciclo $ciclo): JsonResponse
    {
        $ciclo->load(['grupos.modulo', 'grupos.horarios']);

        $informacion = [
            'ciclo' => [
                'id'                  => $ciclo->id,
                'nombre'              => $ciclo->nombre,
                'fecha_inicio'        => $ciclo->fecha_inicio,
                'fecha_fin'           => $ciclo->fecha_fin,
                'fecha_fin_automatica' => $ciclo->fecha_fin_automatica,
                'duracion_dias'       => $ciclo->duracion_dias,
            ],
            'grupos' => $ciclo->grupos->map(function ($grupo) {
                return [
                    'id'     => $grupo->id,
                    'nombre' => $grupo->nombre,
                    'modulo' => [
                        'nombre'   => $grupo->modulo->nombre ?? 'Sin módulo',
                        'duracion' => $grupo->modulo->duracion ?? 0,
                    ],
                    'horarios' => $grupo->horarios->map(fn ($h) => [
                        'dia'           => $h->dia,
                        'hora'          => $h->hora,
                        'duracion_horas' => $h->duracion_horas,
                    ]),
                    'total_horas_semana' => $grupo->getTotalHorasSemanaAttribute(),
                    'fecha_inicio_grupo' => $grupo->pivot->fecha_inicio_grupo,
                    'fecha_fin_grupo'    => $grupo->pivot->fecha_fin_grupo,
                    'con_fechas_activas' => $this->calendario->fechasActivasGrupo($grupo->id, $ciclo->id) !== null,
                ];
            }),
            'calculos' => [
                'total_horas'       => $ciclo->getTotalHorasAttribute(),
                'horas_por_semana'  => $ciclo->getHorasPorSemanaAttribute(),
                'semanas_estimadas' => $ciclo->getHorasPorSemanaAttribute() > 0
                    ? ceil($ciclo->getTotalHorasAttribute() / $ciclo->getHorasPorSemanaAttribute())
                    : 0,
            ],
            'estado' => [
                'en_curso'   => $ciclo->getEnCursoAttribute(),
                'finalizado' => $ciclo->getFinalizadoAttribute(),
                'por_iniciar' => $ciclo->getPorIniciarAttribute(),
            ],
        ];

        return response()->json(['data' => $informacion]);
    }

    /**
     * Actualiza el orden de un grupo específico en el ciclo.
     *
     * @param Request $request
     * @param Ciclo   $ciclo
     * @return JsonResponse
     */
    public function actualizarOrdenGrupo(Request $request, Ciclo $ciclo): JsonResponse
    {
        $request->validate([
            'grupo_id'    => 'required|exists:grupos,id',
            'nuevo_orden' => 'required|integer|min:1',
        ]);

        if (! $ciclo->grupos()->where('grupo_id', $request->grupo_id)->exists()) {
            return response()->json([
                'message' => 'El grupo no está asignado a este ciclo.',
            ], 422);
        }

        $actualizado = $ciclo->actualizarOrdenGrupo($request->grupo_id, $request->nuevo_orden);

        if (! $actualizado) {
            return response()->json([
                'message' => 'No se pudo actualizar el orden del grupo.',
            ], 422);
        }

        if ($ciclo->fecha_fin_automatica) {
            $this->aplicarFechasYGuardar($ciclo);
        }

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Orden del grupo actualizado exitosamente.',
            'data'    => new CicloResource($ciclo),
        ]);
    }

    /**
     * Reordena todos los grupos del ciclo y recalcula las fechas.
     *
     * @param Request $request
     * @param Ciclo   $ciclo
     * @return JsonResponse
     */
    public function reordenarGrupos(Request $request, Ciclo $ciclo): JsonResponse
    {
        $request->validate([
            'nuevo_orden'   => 'required|array',
            'nuevo_orden.*' => 'integer|exists:grupos,id',
        ]);

        $gruposAsignados   = $ciclo->grupos()->pluck('grupo_id')->toArray();
        $gruposSolicitados = $request->nuevo_orden;

        if (count($gruposSolicitados) !== count($gruposAsignados) ||
            array_diff($gruposSolicitados, $gruposAsignados)) {
            return response()->json([
                'message' => 'El nuevo orden debe incluir todos los grupos asignados al ciclo.',
            ], 422);
        }

        $ciclo->reordenarGrupos($request->nuevo_orden);

        if ($ciclo->fecha_fin_automatica) {
            $this->aplicarFechasYGuardar($ciclo);
        }

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Grupos reordenados exitosamente.',
            'data'    => new CicloResource($ciclo),
        ]);
    }

    /**
     * Cronograma detallado del ciclo.
     *
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function cronograma(Ciclo $ciclo): JsonResponse
    {
        $ciclo->load(['grupos.modulo', 'grupos.horarios']);
        $cronograma = $ciclo->cronograma;

        return response()->json([
            'data' => [
                'ciclo' => [
                    'id'                  => $ciclo->id,
                    'nombre'              => $ciclo->nombre,
                    'fecha_inicio'        => $ciclo->fecha_inicio,
                    'fecha_fin'           => $ciclo->fecha_fin,
                    'fecha_fin_automatica' => $ciclo->fecha_fin_automatica,
                ],
                'cronograma' => $cronograma,
                'resumen'    => [
                    'total_grupos'           => count($cronograma),
                    'duracion_total_dias'    => $ciclo->duracion_dias,
                    'total_horas'            => $ciclo->getTotalHorasAttribute(),
                    'horas_por_semana_promedio' => count($cronograma) > 0
                        ? round($ciclo->getHorasPorSemanaAttribute() / count($cronograma), 2)
                        : 0,
                ],
            ],
        ]);
    }

    /**
     * Devuelve el siguiente orden disponible para asignar un nuevo grupo al ciclo.
     *
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function siguienteOrden(Ciclo $ciclo): JsonResponse
    {
        return response()->json([
            'data' => ['siguiente_orden' => $ciclo->getSiguienteOrden()],
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Delega al CalendarioGrupoService el cálculo de fechas del pivot y
     * persiste fecha_fin + duracion_dias en el ciclo sin disparar eventos.
     */
    private function aplicarFechasYGuardar(Ciclo $ciclo): void
    {
        $fechaFin = $this->calendario->calcularYAsignarFechas($ciclo);

        if ($fechaFin) {
            $ciclo->fecha_fin     = $fechaFin;
            $ciclo->duracion_dias = $ciclo->fecha_inicio->diffInDays($fechaFin);
            $ciclo->saveQuietly();
        }
    }
}
