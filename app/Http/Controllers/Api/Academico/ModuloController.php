<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreModuloRequest;
use App\Http\Requests\Api\Academico\UpdateModuloRequest;
use App\Http\Resources\Api\Academico\ModuloResource;
use App\Models\Academico\Modulo;
use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ModuloController extends Controller
{
    use HasActiveStatus;
    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_modulos')->only(['index', 'show', 'filters', 'statistics']);
        $this->middleware('permission:aca_moduloCrear')->only(['store']);
        $this->middleware('permission:aca_moduloEditar')->only(['update']);
        $this->middleware('permission:aca_moduloInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista de los módulos.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'status', 'include_trashed', 'only_trashed']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['cursos', 'grupos'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (
            str_contains($request->with, 'cursos') ||
            str_contains($request->with, 'grupos')
        );

        // Construir query usando scopes
        $modulos = Modulo::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ModuloResource::collection($modulos),
            'meta' => [
                'current_page' => $modulos->currentPage(),
                'last_page' => $modulos->lastPage(),
                'per_page' => $modulos->perPage(),
                'total' => $modulos->total(),
                'from' => $modulos->firstItem(),
                'to' => $modulos->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena un nuevo módulo en la base de datos.
     *
     * @param StoreModuloRequest $request
     * @return JsonResponse
     */
    public function store(StoreModuloRequest $request): JsonResponse
    {
        $modulo = Modulo::create([
            'nombre' => $request->nombre,
            'status' => $request->status ?? 1, // Por defecto estado "Activo"
        ]);

        // Asociar cursos si se proporcionan
        if ($request->has('curso_ids') && is_array($request->curso_ids)) {
            $modulo->cursos()->attach($request->curso_ids);
        }

        $modulo->load(['cursos']);

        return response()->json([
            'message' => 'Módulo creado exitosamente.',
            'data' => new ModuloResource($modulo),
        ], 201);
    }

    /**
     * Muestra el módulo especificado.
     *
     * @param Request $request
     * @param Modulo $modulo
     * @return JsonResponse
     */
    public function show(Request $request, Modulo $modulo): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['cursos', 'grupos'];

        // Cargar relaciones y contadores usando el modelo
        $modulo->load($relations);
        $modulo->loadCount(['cursos', 'grupos']);

        return response()->json([
            'data' => new ModuloResource($modulo),
        ]);
    }

    /**
     * Actualiza el módulo especificado en la base de datos.
     *
     * @param UpdateModuloRequest $request
     * @param Modulo $modulo
     * @return JsonResponse
     */
    public function update(UpdateModuloRequest $request, Modulo $modulo): JsonResponse
    {
        $modulo->update($request->only([
            'nombre',
            'status',
        ]));

        // Actualizar cursos si se proporcionan
        if ($request->has('curso_ids') && is_array($request->curso_ids)) {
            $modulo->cursos()->sync($request->curso_ids);
        }

        $modulo->load(['cursos']);

        return response()->json([
            'message' => 'Módulo actualizado exitosamente.',
            'data' => new ModuloResource($modulo),
        ]);
    }

    /**
     * Elimina el módulo especificado de la base de datos (soft delete).
     *
     * @param Modulo $modulo
     * @return JsonResponse
     */
    public function destroy(Modulo $modulo): JsonResponse
    {
        // Verificar si tiene cursos o grupos asociados
        if ($modulo->cursos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el módulo porque tiene cursos asociados.',
            ], 422);
        }

        if ($modulo->grupos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el módulo porque tiene grupos asociados.',
            ], 422);
        }

        $modulo->delete(); // Soft delete

        return response()->json([
            'message' => 'Módulo eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un módulo eliminado (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $modulo = Modulo::onlyTrashed()->findOrFail($id);
        $modulo->restore();

        return response()->json([
            'message' => 'Módulo restaurado exitosamente.',
            'data' => new ModuloResource($modulo->load(['cursos'])),
        ]);
    }

    /**
     * Elimina permanentemente un módulo.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $modulo = Modulo::onlyTrashed()->findOrFail($id);

        // Verificar si tiene cursos o grupos asociados
        if ($modulo->cursos()->withTrashed()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el módulo porque tiene cursos asociados.',
            ], 422);
        }

        if ($modulo->grupos()->withTrashed()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el módulo porque tiene grupos asociados.',
            ], 422);
        }

        $modulo->forceDelete();

        return response()->json([
            'message' => 'Módulo eliminado permanentemente.',
        ]);
    }

    /**
     * Obtiene solo los módulos eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'status']);
        $filters['only_trashed'] = true;

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['cursos', 'grupos'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (
            str_contains($request->with, 'cursos') ||
            str_contains($request->with, 'grupos')
        );

        // Construir query usando scopes (solo eliminados)
        $modulos = Modulo::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ModuloResource::collection($modulos),
            'meta' => [
                'current_page' => $modulos->currentPage(),
                'last_page' => $modulos->lastPage(),
                'per_page' => $modulos->perPage(),
                'total' => $modulos->total(),
                'from' => $modulos->firstItem(),
                'to' => $modulos->lastItem(),
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
        $modulos = Modulo::select('id', 'nombre')->get();

        return response()->json([
            'data' => [
                'status_options' => self::getActiveStatusOptions(),
                'modulos' => $modulos,
            ],
        ]);
    }

    /**
     * Obtiene estadísticas de módulos.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Modulo::count(),
                'activos' => Modulo::whereNull('deleted_at')->count(),
                'eliminados' => Modulo::onlyTrashed()->count(),
            ],
            'por_status' => [
                'activos' => Modulo::where('status', 1)->count(),
                'inactivos' => Modulo::where('status', 0)->count(),
            ],
            'con_cursos' => Modulo::with('cursos')
                ->selectRaw('id, count(modulo_curso.curso_id) as total_cursos')
                ->leftJoin('modulo_curso', 'modulos.id', '=', 'modulo_curso.modulo_id')
                ->groupBy('modulos.id')
                ->having('total_cursos', '>', 0)
                ->get(),
            'con_grupos' => Modulo::with('grupos')
                ->selectRaw('id, count(grupos.id) as total_grupos')
                ->leftJoin('grupos', 'modulos.id', '=', 'grupos.modulo_id')
                ->groupBy('modulos.id')
                ->having('total_grupos', '>', 0)
                ->get(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}
