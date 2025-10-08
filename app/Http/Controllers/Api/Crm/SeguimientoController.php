<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\StoreSeguimientoRequest;
use App\Http\Requests\Api\Crm\UpdateSeguimientoRequest;
use App\Http\Resources\Api\Crm\SeguimientoResource;
use App\Models\Crm\Seguimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeguimientoController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:crm_seguimientos')->only(['index', 'show', 'byReferido', 'bySeguidor']);
        $this->middleware('permission:crm_seguimientoCrear')->only(['store']);
        $this->middleware('permission:crm_seguimientoEditar')->only(['update', 'restore']);
        $this->middleware('permission:crm_seguimientoInactivar')->only(['destroy', 'forceDelete']);
    }

    /**
     * Muestra una lista de los seguimientos.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['referido_id', 'seguidor_id', 'search', 'fecha_desde', 'fecha_hasta', 'include_trashed', 'only_trashed']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['referido', 'seguidor'];

        // Construir query usando scopes
        $seguimientos = Seguimiento::withSeguimientoFilters($filters)
            ->withRelations($relations)
            ->withSorting($request->get('sort_by', 'fecha'), $request->get('sort_direction', 'desc'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => SeguimientoResource::collection($seguimientos),
            'meta' => [
                'current_page' => $seguimientos->currentPage(),
                'last_page' => $seguimientos->lastPage(),
                'per_page' => $seguimientos->perPage(),
                'total' => $seguimientos->total(),
                'from' => $seguimientos->firstItem(),
                'to' => $seguimientos->lastItem(),
            ],
        ]);
    }

    /**
     * Crea un nuevo seguimiento en el sistema.
     *
     * @param StoreSeguimientoRequest $request
     * @return JsonResponse
     */
    public function store(StoreSeguimientoRequest $request): JsonResponse
    {
        $seguimiento = Seguimiento::create($request->validated());

        $seguimiento->load(['referido', 'seguidor']);

        return response()->json([
            'message' => 'Seguimiento creado exitosamente.',
            'data' => new SeguimientoResource($seguimiento),
        ], 201);
    }

    /**
     * Muestra un seguimiento específico con sus relaciones.
     *
     * @param Request $request
     * @param Seguimiento $seguimiento
     * @return JsonResponse
     */
    public function show(Request $request, Seguimiento $seguimiento): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['referido', 'seguidor'];

        // Cargar relaciones
        $seguimiento->load($relations);

        return response()->json([
            'data' => new SeguimientoResource($seguimiento),
        ]);
    }

    /**
     * Actualiza un seguimiento existente.
     *
     * @param UpdateSeguimientoRequest $request
     * @param Seguimiento $seguimiento
     * @return JsonResponse
     */
    public function update(UpdateSeguimientoRequest $request, Seguimiento $seguimiento): JsonResponse
    {
        $seguimiento->update($request->validated());

        $seguimiento->load(['referido', 'seguidor']);

        return response()->json([
            'message' => 'Seguimiento actualizado exitosamente.',
            'data' => new SeguimientoResource($seguimiento),
        ]);
    }

    /**
     * Elimina un seguimiento (soft delete).
     *
     * @param Seguimiento $seguimiento
     * @return JsonResponse
     */
    public function destroy(Seguimiento $seguimiento): JsonResponse
    {
        $seguimiento->delete(); // Soft delete

        return response()->json([
            'message' => 'Seguimiento eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un seguimiento eliminado (soft delete).
     *
     * @param int $id ID del seguimiento a restaurar
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $seguimiento = Seguimiento::onlyTrashed()->findOrFail($id);
        $seguimiento->restore();

        return response()->json([
            'message' => 'Seguimiento restaurado exitosamente.',
            'data' => new SeguimientoResource($seguimiento->load(['referido', 'seguidor'])),
        ]);
    }

    /**
     * Elimina permanentemente un seguimiento.
     *
     * @param int $id ID del seguimiento a eliminar permanentemente
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $seguimiento = Seguimiento::onlyTrashed()->findOrFail($id);
        $seguimiento->forceDelete();

        return response()->json([
            'message' => 'Seguimiento eliminado permanentemente.',
        ]);
    }

    /**
     * Obtiene todos los seguimientos de un referido específico.
     *
     * @param Request $request
     * @param int $referidoId ID del referido
     * @return JsonResponse
     */
    public function byReferido(Request $request, int $referidoId): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['referido', 'seguidor'];

        // Construir query usando scopes
        $seguimientos = Seguimiento::byReferido($referidoId)
            ->withRelations($relations)
            ->withSorting($request->get('sort_by', 'fecha'), $request->get('sort_direction', 'desc'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => SeguimientoResource::collection($seguimientos),
            'meta' => [
                'current_page' => $seguimientos->currentPage(),
                'last_page' => $seguimientos->lastPage(),
                'per_page' => $seguimientos->perPage(),
                'total' => $seguimientos->total(),
                'from' => $seguimientos->firstItem(),
                'to' => $seguimientos->lastItem(),
            ],
        ]);
    }

    /**
     * Obtiene todos los seguimientos realizados por un seguidor específico.
     *
     * @param Request $request
     * @param int $seguidorId ID del seguidor
     * @return JsonResponse
     */
    public function bySeguidor(Request $request, int $seguidorId): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['referido', 'seguidor'];

        // Construir query usando scopes
        $seguimientos = Seguimiento::bySeguidor($seguidorId)
            ->withRelations($relations)
            ->withSorting($request->get('sort_by', 'fecha'), $request->get('sort_direction', 'desc'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => SeguimientoResource::collection($seguimientos),
            'meta' => [
                'current_page' => $seguimientos->currentPage(),
                'last_page' => $seguimientos->lastPage(),
                'per_page' => $seguimientos->perPage(),
                'total' => $seguimientos->total(),
                'from' => $seguimientos->firstItem(),
                'to' => $seguimientos->lastItem(),
            ],
        ]);
    }
}
