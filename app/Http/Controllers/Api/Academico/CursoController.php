<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreCursoRequest;
use App\Http\Requests\Api\Academico\UpdateCursoRequest;
use App\Http\Resources\Api\Academico\CursoResource;
use App\Models\Academico\Curso;
use App\Traits\HasTipo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CursoController extends Controller
{
    use HasTipo;
    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_cursos')->only(['index', 'show', 'filters', 'statistics']);
        $this->middleware('permission:aca_cursoCrear')->only(['store']);
        $this->middleware('permission:aca_cursoEditar')->only(['update']);
        $this->middleware('permission:aca_cursoInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista de los cursos.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'status', 'tipo', 'duracion_min', 'duracion_max']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['referidos', 'estudiantes'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (str_contains($request->with, 'referidos') || str_contains($request->with, 'estudiantes'));

        // Construir query usando scopes
        $cursos = Curso::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CursoResource::collection($cursos),
            'meta' => [
                'current_page' => $cursos->currentPage(),
                'last_page' => $cursos->lastPage(),
                'per_page' => $cursos->perPage(),
                'total' => $cursos->total(),
                'from' => $cursos->firstItem(),
                'to' => $cursos->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena un nuevo curso en la base de datos.
     *
     * @param StoreCursoRequest $request
     * @return JsonResponse
     */
    public function store(StoreCursoRequest $request): JsonResponse
    {
        $curso = Curso::create([
            'nombre' => $request->nombre,
            'duracion' => $request->duracion,
            'tipo' => $request->tipo,
            'status' => $request->status ?? 1, // Por defecto estado "Activo"
        ]);

        $curso->load(['referidos', 'estudiantes']);

        return response()->json([
            'message' => 'Curso creado exitosamente.',
            'data' => new CursoResource($curso),
        ], 201);
    }

    /**
     * Muestra el curso especificado.
     *
     * @param Request $request
     * @param Curso $curso
     * @return JsonResponse
     */
    public function show(Request $request, Curso $curso): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['referidos', 'estudiantes'];

        // Cargar relaciones y contadores usando el modelo
        $curso->load($relations);
        $curso->loadCount(['referidos', 'estudiantes']);

        return response()->json([
            'data' => new CursoResource($curso),
        ]);
    }

    /**
     * Actualiza el curso especificado en la base de datos.
     *
     * @param UpdateCursoRequest $request
     * @param Curso $curso
     * @return JsonResponse
     */
    public function update(UpdateCursoRequest $request, Curso $curso): JsonResponse
    {
        $curso->update($request->only([
            'nombre',
            'duracion',
            'tipo',
            'status',
        ]));

        $curso->load(['referidos', 'estudiantes']);

        return response()->json([
            'message' => 'Curso actualizado exitosamente.',
            'data' => new CursoResource($curso),
        ]);
    }

    /**
     * Elimina el curso especificado de la base de datos (soft delete).
     *
     * @param Curso $curso
     * @return JsonResponse
     */
    public function destroy(Curso $curso): JsonResponse
    {
        // Verificar si tiene referidos asociados
        if ($curso->referidos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el curso porque tiene referidos asociados.',
            ], 422);
        }

        // Verificar si tiene estudiantes asociados
        if ($curso->estudiantes()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el curso porque tiene estudiantes asociados.',
            ], 422);
        }

        $curso->delete(); // Soft delete

        return response()->json([
            'message' => 'Curso eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un curso eliminado (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $curso = Curso::onlyTrashed()->findOrFail($id);
        $curso->restore();

        return response()->json([
            'message' => 'Curso restaurado exitosamente.',
            'data' => new CursoResource($curso->load(['referidos', 'estudiantes'])),
        ]);
    }

    /**
     * Elimina permanentemente un curso.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $curso = Curso::onlyTrashed()->findOrFail($id);

        // Verificar si tiene referidos asociados
        if ($curso->referidos()->withTrashed()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el curso porque tiene referidos asociados.',
            ], 422);
        }

        // Verificar si tiene estudiantes asociados
        if ($curso->estudiantes()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el curso porque tiene estudiantes asociados.',
            ], 422);
        }

        $curso->forceDelete();

        return response()->json([
            'message' => 'Curso eliminado permanentemente.',
        ]);
    }

    /**
     * Obtiene solo los cursos eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'status', 'tipo', 'duracion_min', 'duracion_max']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['referidos', 'estudiantes'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (str_contains($request->with, 'referidos') || str_contains($request->with, 'estudiantes'));

        // Construir query usando scopes (solo eliminados)
        $cursos = Curso::onlyTrashed()
            ->withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CursoResource::collection($cursos),
            'meta' => [
                'current_page' => $cursos->currentPage(),
                'last_page' => $cursos->lastPage(),
                'per_page' => $cursos->perPage(),
                'total' => $cursos->total(),
                'from' => $cursos->firstItem(),
                'to' => $cursos->lastItem(),
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
        $cursos = Curso::select('id', 'nombre')->get();

        return response()->json([
            'data' => [
                'status_options' => [
                    0 => 'Inactivo',
                    1 => 'Activo',
                ],
                'tipo_options' => self::getTipoOptions(),
                'cursos' => $cursos,
            ],
        ]);
    }

    /**
     * Obtiene estadísticas de cursos.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Curso::count(),
                'activos' => Curso::whereNull('deleted_at')->count(),
                'eliminados' => Curso::onlyTrashed()->count(),
            ],
            'por_status' => [
                'activos' => Curso::where('status', 1)->count(),
                'inactivos' => Curso::where('status', 0)->count(),
            ],
            'por_duracion' => Curso::selectRaw('duracion, count(*) as total')
                ->groupBy('duracion')
                ->orderBy('total', 'desc')
                ->get(),
            'por_tipo' => collect(self::getTipoOptions())->mapWithKeys(function ($text, $tipo) {
                return [$text => Curso::where('tipo', $tipo)->count()];
            }),
            'con_referidos' => Curso::with('referidos')
                ->selectRaw('id, count(referidos.id) as total_referidos')
                ->leftJoin('referidos', 'cursos.id', '=', 'referidos.curso_id')
                ->groupBy('cursos.id')
                ->having('total_referidos', '>', 0)
                ->get(),
            'con_estudiantes' => Curso::with('estudiantes')
                ->selectRaw('cursos.id, count(curso_user.user_id) as total_estudiantes')
                ->leftJoin('curso_user', 'cursos.id', '=', 'curso_user.curso_id')
                ->groupBy('cursos.id')
                ->having('total_estudiantes', '>', 0)
                ->get(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}
