<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreCicloRequest;
use App\Http\Requests\Api\Academico\UpdateCicloRequest;
use App\Http\Resources\Api\Academico\CicloResource;
use App\Models\Academico\Ciclo;
use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CicloController extends Controller
{
    use HasActiveStatus;

    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_ciclos')->only(['index', 'show', 'filters', 'statistics']);
        $this->middleware('permission:aca_cicloCrear')->only(['store']);
        $this->middleware('permission:aca_cicloEditar')->only(['update']);
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
        // Preparar filtros
        $filters = $request->only([
            'search', 'status', 'sede_id', 'curso_id', 'include_trashed', 'only_trashed'
        ]);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sede', 'curso', 'grupos'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (
            str_contains($request->with, 'sede') ||
            str_contains($request->with, 'curso') ||
            str_contains($request->with, 'grupos')
        );

        // Construir query usando scopes
        $ciclos = Ciclo::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CicloResource::collection($ciclos),
            'meta' => [
                'current_page' => $ciclos->currentPage(),
                'last_page' => $ciclos->lastPage(),
                'per_page' => $ciclos->perPage(),
                'total' => $ciclos->total(),
                'from' => $ciclos->firstItem(),
                'to' => $ciclos->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena un nuevo ciclo en la base de datos.
     *
     * @param StoreCicloRequest $request
     * @return JsonResponse
     */
    public function store(StoreCicloRequest $request): JsonResponse
    {
        $ciclo = Ciclo::create([
            'sede_id' => $request->sede_id,
            'curso_id' => $request->curso_id,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'status' => $request->status ?? 1, // Por defecto estado "Activo"
        ]);

        // Asignar grupos al ciclo si se proporcionan
        if ($request->has('grupos') && is_array($request->grupos)) {
            $ciclo->grupos()->attach($request->grupos);
        }

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Ciclo creado exitosamente.',
            'data' => new CicloResource($ciclo),
        ], 201);
    }

    /**
     * Muestra el ciclo especificado.
     *
     * @param Request $request
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function show(Request $request, Ciclo $ciclo): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sede', 'curso', 'grupos'];

        // Cargar relaciones y contadores
        $ciclo->load($relations);
        $ciclo->loadCount(['sede', 'curso', 'grupos']);

        return response()->json([
            'data' => new CicloResource($ciclo),
        ]);
    }

    /**
     * Actualiza el ciclo especificado en la base de datos.
     *
     * @param UpdateCicloRequest $request
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function update(UpdateCicloRequest $request, Ciclo $ciclo): JsonResponse
    {
        $ciclo->update($request->only([
            'sede_id',
            'curso_id',
            'nombre',
            'descripcion',
            'status',
        ]));

        // Actualizar grupos asignados al ciclo si se proporcionan
        if ($request->has('grupos')) {
            if (is_array($request->grupos)) {
                $ciclo->grupos()->sync($request->grupos);
            } else {
                // Si se envía null o array vacío, desasignar todos los grupos
                $ciclo->grupos()->detach();
            }
        }

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Ciclo actualizado exitosamente.',
            'data' => new CicloResource($ciclo),
        ]);
    }

    /**
     * Elimina el ciclo especificado de la base de datos (soft delete).
     *
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function destroy(Ciclo $ciclo): JsonResponse
    {
        // Verificar si tiene grupos asociados
        if ($ciclo->grupos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el ciclo porque tiene grupos asociados.',
            ], 422);
        }

        $ciclo->delete(); // Soft delete

        return response()->json([
            'message' => 'Ciclo eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un ciclo eliminado (soft delete).
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
            'data' => new CicloResource($ciclo->load(['sede', 'curso', 'grupos'])),
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

        // Verificar si tiene grupos asociados
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
     * Obtiene solo los ciclos eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only([
            'search', 'status', 'sede_id', 'curso_id'
        ]);
        $filters['only_trashed'] = true;

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sede', 'curso', 'grupos'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (
            str_contains($request->with, 'sede') ||
            str_contains($request->with, 'curso') ||
            str_contains($request->with, 'grupos')
        );

        // Construir query usando scopes (solo eliminados)
        $ciclos = Ciclo::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CicloResource::collection($ciclos),
            'meta' => [
                'current_page' => $ciclos->currentPage(),
                'last_page' => $ciclos->lastPage(),
                'per_page' => $ciclos->perPage(),
                'total' => $ciclos->total(),
                'from' => $ciclos->firstItem(),
                'to' => $ciclos->lastItem(),
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
            ],
        ]);
    }


    /**
     * Obtiene estadísticas de ciclos.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Ciclo::count(),
                'activos' => Ciclo::whereNull('deleted_at')->count(),
                'eliminados' => Ciclo::onlyTrashed()->count(),
            ],
            'por_status' => [
                'activos' => Ciclo::where('status', 1)->count(),
                'inactivos' => Ciclo::where('status', 0)->count(),
            ],
            'por_sede' => Ciclo::with('sede')
                ->selectRaw('sede_id, COUNT(*) as total')
                ->groupBy('sede_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'sede' => $item->sede->nombre ?? 'Sin sede',
                        'total' => $item->total
                    ];
                }),
            'por_curso' => Ciclo::with('curso')
                ->selectRaw('curso_id, COUNT(*) as total')
                ->groupBy('curso_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'curso' => $item->curso->nombre ?? 'Sin curso',
                        'total' => $item->total
                    ];
                }),
            'con_grupos' => Ciclo::whereHas('grupos')->count(),
            'sin_grupos' => Ciclo::whereDoesntHave('grupos')->count(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}
