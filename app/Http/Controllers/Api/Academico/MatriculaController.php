<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreMatriculaRequest;
use App\Http\Requests\Api\Academico\UpdateMatriculaRequest;
use App\Http\Resources\Api\Academico\MatriculaResource;
use App\Models\Academico\Matricula;
use App\Traits\HasActiveStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatriculaController extends Controller
{
    use HasActiveStatus;

    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_matriculas')->only(['index', 'show', 'filters', 'statistics']);
        $this->middleware('permission:aca_matriculaCrear')->only(['store']);
        $this->middleware('permission:aca_matriculaEditar')->only(['update']);
        $this->middleware('permission:aca_matriculaInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista de las matrículas.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only([
            'search', 'status', 'curso_id', 'ciclo_id', 'estudiante_id',
            'fecha_matricula_inicio', 'fecha_matricula_fin',
            'monto_min', 'monto_max', 'include_trashed', 'only_trashed'
        ]);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['curso', 'ciclo', 'estudiante'];

        // Verificar si incluir contadores
        $includeCounts = false;

        // Construir query usando scopes
        $matriculas = Matricula::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => MatriculaResource::collection($matriculas),
            'meta' => [
                'current_page' => $matriculas->currentPage(),
                'last_page' => $matriculas->lastPage(),
                'per_page' => $matriculas->perPage(),
                'total' => $matriculas->total(),
                'from' => $matriculas->firstItem(),
                'to' => $matriculas->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena una nueva matrícula en la base de datos.
     *
     * @param StoreMatriculaRequest $request
     * @return JsonResponse
     */
    public function store(StoreMatriculaRequest $request): JsonResponse
    {
        $matricula = Matricula::create([
            'curso_id' => $request->curso_id,
            'ciclo_id' => $request->ciclo_id,
            'estudiante_id' => $request->estudiante_id,
            'matriculado_por_id' => $request->matriculado_por_id,
            'comercial_id' => $request->comercial_id,
            'fecha_matricula' => $request->fecha_matricula,
            'fecha_inicio' => $request->fecha_inicio,
            'monto' => $request->monto,
            'observaciones' => $request->observaciones,
            'status' => $request->status ?? 1, // Por defecto estado "Activo"
        ]);

        $matricula->load(['curso', 'ciclo', 'estudiante', 'matriculadoPor', 'comercial']);

        return response()->json([
            'message' => 'Matrícula creada exitosamente.',
            'data' => new MatriculaResource($matricula),
        ], 201);
    }

    /**
     * Muestra la matrícula especificada.
     *
     * @param Request $request
     * @param Matricula $matricula
     * @return JsonResponse
     */
    public function show(Request $request, Matricula $matricula): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['curso', 'ciclo', 'estudiante', 'matriculadoPor', 'comercial'];

        // Cargar relaciones
        $matricula->load($relations);

        return response()->json([
            'data' => new MatriculaResource($matricula),
        ]);
    }

    /**
     * Actualiza la matrícula especificada en la base de datos.
     *
     * @param UpdateMatriculaRequest $request
     * @param Matricula $matricula
     * @return JsonResponse
     */
    public function update(UpdateMatriculaRequest $request, Matricula $matricula): JsonResponse
    {
        $matricula->update($request->only([
            'curso_id',
            'ciclo_id',
            'estudiante_id',
            'matriculado_por_id',
            'comercial_id',
            'fecha_matricula',
            'fecha_inicio',
            'monto',
            'observaciones',
            'status',
        ]));

        $matricula->load(['curso', 'ciclo', 'estudiante', 'matriculadoPor', 'comercial']);

        return response()->json([
            'message' => 'Matrícula actualizada exitosamente.',
            'data' => new MatriculaResource($matricula),
        ]);
    }

    /**
     * Elimina la matrícula especificada de la base de datos (soft delete).
     *
     * @param Matricula $matricula
     * @return JsonResponse
     */
    public function destroy(Matricula $matricula): JsonResponse
    {
        $matricula->delete(); // Soft delete

        return response()->json([
            'message' => 'Matrícula eliminada exitosamente.',
        ]);
    }

    /**
     * Restaura una matrícula eliminada (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $matricula = Matricula::onlyTrashed()->findOrFail($id);
        $matricula->restore();

        $matricula->load(['curso', 'ciclo', 'estudiante', 'matriculadoPor', 'comercial']);

        return response()->json([
            'message' => 'Matrícula restaurada exitosamente.',
            'data' => new MatriculaResource($matricula),
        ]);
    }

    /**
     * Elimina permanentemente una matrícula.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $matricula = Matricula::onlyTrashed()->findOrFail($id);
        $matricula->forceDelete();

        return response()->json([
            'message' => 'Matrícula eliminada permanentemente.',
        ]);
    }

    /**
     * Obtiene solo las matrículas eliminadas (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only([
            'search', 'status', 'curso_id', 'ciclo_id', 'estudiante_id',
            'fecha_matricula_inicio', 'fecha_matricula_fin',
            'monto_min', 'monto_max'
        ]);
        $filters['only_trashed'] = true;

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['curso', 'ciclo', 'estudiante'];

        // Construir query usando scopes (solo eliminadas)
        $matriculas = Matricula::withFilters($filters)
            ->withRelationsAndCounts($relations, false)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => MatriculaResource::collection($matriculas),
            'meta' => [
                'current_page' => $matriculas->currentPage(),
                'last_page' => $matriculas->lastPage(),
                'per_page' => $matriculas->perPage(),
                'total' => $matriculas->total(),
                'from' => $matriculas->firstItem(),
                'to' => $matriculas->lastItem(),
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
        $cursos = \App\Models\Academico\Curso::select('id', 'nombre')->get();
        $ciclos = \App\Models\Academico\Ciclo::select('id', 'nombre')->get();
        $estudiantes = \App\Models\User::select('id', 'name', 'email')->get();

        return response()->json([
            'data' => [
                'status_options' => self::getActiveStatusOptions(),
                'cursos' => $cursos,
                'ciclos' => $ciclos,
                'estudiantes' => $estudiantes,
            ],
        ]);
    }

    /**
     * Obtiene estadísticas de matrículas.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Matricula::count(),
                'activas' => Matricula::whereNull('deleted_at')->count(),
                'eliminadas' => Matricula::onlyTrashed()->count(),
            ],
            'por_status' => [
                'activas' => Matricula::where('status', 1)->count(),
                'inactivas' => Matricula::where('status', 0)->count(),
                'anuladas' => Matricula::where('status', 2)->count(),
            ],
            'por_curso' => Matricula::with('curso')
                ->selectRaw('curso_id, COUNT(*) as total')
                ->groupBy('curso_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'curso' => $item->curso->nombre ?? 'Sin curso',
                        'total' => $item->total
                    ];
                }),
            'por_ciclo' => Matricula::with('ciclo')
                ->selectRaw('ciclo_id, COUNT(*) as total')
                ->groupBy('ciclo_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'ciclo' => $item->ciclo->nombre ?? 'Sin ciclo',
                        'total' => $item->total
                    ];
                }),
            'monto_total' => Matricula::sum('monto'),
            'monto_promedio' => Matricula::avg('monto'),
            'monto_minimo' => Matricula::min('monto'),
            'monto_maximo' => Matricula::max('monto'),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}
