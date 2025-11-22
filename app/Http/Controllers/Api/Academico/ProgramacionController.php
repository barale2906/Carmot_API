<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreProgramacionRequest;
use App\Http\Requests\Api\Academico\UpdateProgramacionRequest;
use App\Http\Resources\Api\Academico\ProgramacionResource;
use App\Models\Academico\Programacion;
use App\Traits\HasActiveStatus;
use App\Traits\HasJornadaStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProgramacionController extends Controller
{
    use HasActiveStatus, HasJornadaStatus;

    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_programaciones')->only(['index', 'show', 'filters', 'statistics']);
        $this->middleware('permission:aca_programacionCrear')->only(['store']);
        $this->middleware('permission:aca_programacionEditar')->only(['update']);
        $this->middleware('permission:aca_programacionInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista de las programaciones.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only([
            'search', 'status', 'curso_id', 'sede_id', 'jornada', 'fecha_inicio', 'fecha_fin', 'fecha_inicio_range', 'fecha_fin_range', 'activas_en_fecha', 'include_trashed', 'only_trashed'
        ]);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['curso', 'sede', 'grupos'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (
            str_contains($request->with, 'curso') ||
            str_contains($request->with, 'sede') ||
            str_contains($request->with, 'grupos')
        );

        // Construir query usando scopes
        $programaciones = Programacion::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ProgramacionResource::collection($programaciones),
            'meta' => [
                'current_page' => $programaciones->currentPage(),
                'last_page' => $programaciones->lastPage(),
                'per_page' => $programaciones->perPage(),
                'total' => $programaciones->total(),
                'from' => $programaciones->firstItem(),
                'to' => $programaciones->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena una nueva programación en la base de datos.
     *
     * @param StoreProgramacionRequest $request
     * @return JsonResponse
     */
    public function store(StoreProgramacionRequest $request): JsonResponse
    {
        $programacion = Programacion::create([
            'curso_id' => $request->curso_id,
            'sede_id' => $request->sede_id,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
            'registrados' => $request->registrados ?? 0,
            'jornada' => $request->jornada,
            'status' => $request->status ?? 1, // Por defecto estado "Activo"
        ]);

        // Asignar grupos a la programación si se proporcionan
        if ($request->has('grupos') && is_array($request->grupos)) {
            $gruposConFechas = [];
            foreach ($request->grupos as $grupo) {
                $gruposConFechas[] = [
                    'grupo_id' => $grupo['grupo_id'],
                    'fecha_inicio_grupo' => $grupo['fecha_inicio_grupo'] ?? null,
                    'fecha_fin_grupo' => $grupo['fecha_fin_grupo'] ?? null,
                ];
            }
            $programacion->asignarGruposConFechas($gruposConFechas);
        }

        $programacion->load(['curso', 'sede', 'grupos']);

        return response()->json([
            'message' => 'Programación creada exitosamente.',
            'data' => new ProgramacionResource($programacion),
        ], 201);
    }

    /**
     * Muestra la programación especificada.
     *
     * @param Request $request
     * @param Programacion $programacion
     * @return JsonResponse
     */
    public function show(Request $request, Programacion $programacion): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['curso', 'sede', 'grupos'];

        // Cargar relaciones y contadores
        $programacion->load($relations);
        $programacion->loadCount(['curso', 'sede', 'grupos']);

        return response()->json([
            'data' => new ProgramacionResource($programacion),
        ]);
    }

    /**
     * Actualiza la programación especificada en la base de datos.
     *
     * @param UpdateProgramacionRequest $request
     * @param Programacion $programacion
     * @return JsonResponse
     */
    public function update(UpdateProgramacionRequest $request, Programacion $programacion): JsonResponse
    {
        $programacion->update($request->only([
            'curso_id',
            'sede_id',
            'nombre',
            'descripcion',
            'fecha_inicio',
            'fecha_fin',
            'registrados',
            'jornada',
            'status',
        ]));

        // Actualizar grupos asignados a la programación si se proporcionan
        if ($request->has('grupos')) {
            if (is_array($request->grupos) && !empty($request->grupos)) {
                $gruposConFechas = [];
                foreach ($request->grupos as $grupo) {
                    $gruposConFechas[] = [
                        'grupo_id' => $grupo['grupo_id'],
                        'fecha_inicio_grupo' => $grupo['fecha_inicio_grupo'] ?? null,
                        'fecha_fin_grupo' => $grupo['fecha_fin_grupo'] ?? null,
                    ];
                }
                $programacion->asignarGruposConFechas($gruposConFechas);
            } else {
                // Si se envía null o array vacío, desasignar todos los grupos
                $programacion->grupos()->detach();
            }
        }

        $programacion->load(['curso', 'sede', 'grupos']);

        return response()->json([
            'message' => 'Programación actualizada exitosamente.',
            'data' => new ProgramacionResource($programacion),
        ]);
    }

    /**
     * Elimina la programación especificada de la base de datos (soft delete).
     *
     * @param Programacion $programacion
     * @return JsonResponse
     */
    public function destroy(Programacion $programacion): JsonResponse
    {
        // Verificar si tiene grupos asociados
        if ($programacion->grupos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la programación porque tiene grupos asociados.',
            ], 422);
        }

        $programacion->delete(); // Soft delete

        return response()->json([
            'message' => 'Programación eliminada exitosamente.',
        ]);
    }

    /**
     * Restaura una programación eliminada (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $programacion = Programacion::onlyTrashed()->findOrFail($id);
        $programacion->restore();

        return response()->json([
            'message' => 'Programación restaurada exitosamente.',
            'data' => new ProgramacionResource($programacion->load(['curso', 'sede', 'grupos'])),
        ]);
    }

    /**
     * Elimina permanentemente una programación.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $programacion = Programacion::onlyTrashed()->findOrFail($id);

        // Verificar si tiene grupos asociados
        if ($programacion->grupos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente la programación porque tiene grupos asociados.',
            ], 422);
        }

        $programacion->forceDelete();

        return response()->json([
            'message' => 'Programación eliminada permanentemente.',
        ]);
    }

    /**
     * Obtiene solo las programaciones eliminadas (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only([
            'search', 'status', 'curso_id', 'sede_id', 'jornada'
        ]);
        $filters['only_trashed'] = true;

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['curso', 'sede', 'grupos'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (
            str_contains($request->with, 'curso') ||
            str_contains($request->with, 'sede') ||
            str_contains($request->with, 'grupos')
        );

        // Construir query usando scopes (solo eliminadas)
        $programaciones = Programacion::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ProgramacionResource::collection($programaciones),
            'meta' => [
                'current_page' => $programaciones->currentPage(),
                'last_page' => $programaciones->lastPage(),
                'per_page' => $programaciones->perPage(),
                'total' => $programaciones->total(),
                'from' => $programaciones->firstItem(),
                'to' => $programaciones->lastItem(),
            ],
        ]);
    }

    /**
     * Obtiene las opciones de filtros disponibles.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        $sedes = \App\Models\Configuracion\Sede::select('id', 'nombre')->get();
        $cursos = \App\Models\Academico\Curso::select('id', 'nombre')->get();

        return response()->json([
            'data' => [
                'status_options' => self::getActiveStatusOptions(),
                'sedes' => $sedes,
                'cursos' => $cursos,
                'jornadas' => self::getJornadaOptions(),
            ],
        ]);
    }

    /**
     * Obtiene estadísticas de programaciones.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Programacion::count(),
                'activos' => Programacion::whereNull('deleted_at')->count(),
                'eliminados' => Programacion::onlyTrashed()->count(),
            ],
            'por_status' => [
                'activos' => Programacion::where('status', 1)->count(),
                'inactivos' => Programacion::where('status', 0)->count(),
            ],
            'por_sede' => Programacion::with('sede')
                ->selectRaw('sede_id, COUNT(*) as total')
                ->groupBy('sede_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'sede' => $item->sede->nombre ?? 'Sin sede',
                        'total' => $item->total
                    ];
                }),
            'por_curso' => Programacion::with('curso')
                ->selectRaw('curso_id, COUNT(*) as total')
                ->groupBy('curso_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'curso' => $item->curso->nombre ?? 'Sin curso',
                        'total' => $item->total
                    ];
                }),
            'por_jornada' => Programacion::selectRaw('jornada, COUNT(*) as total')
                ->groupBy('jornada')
                ->get()
                ->map(function ($item) {
                    return [
                        'jornada' => self::getJornadaText($item->jornada),
                        'total' => $item->total
                    ];
                }),
            'con_grupos' => Programacion::whereHas('grupos')->count(),
            'sin_grupos' => Programacion::whereDoesntHave('grupos')->count(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Asigna grupos a una programación.
     *
     * @param Request $request
     * @param Programacion $programacion
     * @return JsonResponse
     */
    public function asignarGrupos(Request $request, Programacion $programacion): JsonResponse
    {
        $request->validate([
            'grupos' => 'required|array',
            'grupos.*.grupo_id' => 'required|integer|exists:grupos,id',
            'grupos.*.fecha_inicio_grupo' => 'nullable|date|after_or_equal:' . $programacion->fecha_inicio->format('Y-m-d') . '|before_or_equal:' . $programacion->fecha_fin->format('Y-m-d'),
            'grupos.*.fecha_fin_grupo' => 'nullable|date|after:grupos.*.fecha_inicio_grupo|before_or_equal:' . $programacion->fecha_fin->format('Y-m-d'),
        ]);

        $gruposConFechas = [];
        foreach ($request->grupos as $grupo) {
            $gruposConFechas[] = [
                'grupo_id' => $grupo['grupo_id'],
                'fecha_inicio_grupo' => $grupo['fecha_inicio_grupo'] ?? null,
                'fecha_fin_grupo' => $grupo['fecha_fin_grupo'] ?? null,
            ];
        }

        $programacion->asignarGruposConFechas($gruposConFechas);
        $programacion->load(['curso', 'sede', 'grupos']);

        return response()->json([
            'message' => 'Grupos asignados exitosamente.',
            'data' => new ProgramacionResource($programacion),
        ]);
    }

    /**
     * Desasigna un grupo de una programación.
     *
     * @param Request $request
     * @param Programacion $programacion
     * @return JsonResponse
     */
    public function desasignarGrupo(Request $request, Programacion $programacion): JsonResponse
    {
        $request->validate([
            'grupo_id' => 'required|exists:grupos,id'
        ]);

        $programacion->grupos()->detach($request->grupo_id);
        $programacion->load(['curso', 'sede', 'grupos']);

        return response()->json([
            'message' => 'Grupo desasignado exitosamente.',
            'data' => new ProgramacionResource($programacion),
        ]);
    }

    /**
     * Obtiene el cronograma detallado de la programación.
     *
     * @param Programacion $programacion
     * @return JsonResponse
     */
    public function cronograma(Programacion $programacion): JsonResponse
    {
        $programacion->load(['grupos.modulo', 'grupos.horarios', 'grupos.profesor']);

        $cronograma = $programacion->cronograma;

        return response()->json([
            'data' => [
                'programacion' => [
                    'id' => $programacion->id,
                    'nombre' => $programacion->nombre,
                    'fecha_inicio' => $programacion->fecha_inicio,
                    'fecha_fin' => $programacion->fecha_fin,
                ],
                'cronograma' => $cronograma,
                'resumen' => [
                    'total_grupos' => count($cronograma),
                    'duracion_total_dias' => $programacion->duracion_dias,
                    'total_horas' => $programacion->total_horas,
                    'horas_por_semana_promedio' => count($cronograma) > 0
                        ? round($programacion->horas_por_semana / count($cronograma), 2)
                        : 0
                ]
            ]
        ]);
    }
}
