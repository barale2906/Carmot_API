<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\StoreInvUnidadMedidaRequest;
use App\Http\Requests\Api\Inventarios\UpdateInvUnidadMedidaRequest;
use App\Http\Resources\Api\Inventarios\InvUnidadMedidaResource;
use App\Models\Inventarios\InvUnidadMedida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de unidades de medida del inventario.
 *
 * Administra el ciclo de vida de las unidades: CRUD, soft delete,
 * restauración y eliminación permanente.
 *
 * @package App\Http\Controllers\Api\Inventarios
 */
class InvUnidadMedidaController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inv_unidades')->only(['index', 'show', 'filters', 'activas']);
        $this->middleware('permission:inv_unidadesCrear')->only(['store']);
        $this->middleware('permission:inv_unidadesEditar')->only(['update']);
        $this->middleware('permission:inv_unidadesInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista paginada de unidades de medida con filtros y ordenamiento.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status']);

        $unidades = InvUnidadMedida::withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->withCount('productos')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvUnidadMedidaResource::collection($unidades),
            'meta' => [
                'current_page' => $unidades->currentPage(),
                'last_page'    => $unidades->lastPage(),
                'per_page'     => $unidades->perPage(),
                'total'        => $unidades->total(),
                'from'         => $unidades->firstItem(),
                'to'           => $unidades->lastItem(),
            ],
        ]);
    }

    /**
     * Lista todas las unidades activas sin paginación (para selectores).
     *
     * @return JsonResponse
     */
    public function activas(): JsonResponse
    {
        $unidades = InvUnidadMedida::where('status', 1)->orderBy('nombre')->get();

        return response()->json([
            'data' => InvUnidadMedidaResource::collection($unidades),
            'meta' => [
                'total'       => $unidades->count(),
                'scope'       => 'activas',
                'descripcion' => 'Unidades de medida activas y sin eliminación lógica.',
            ],
        ]);
    }

    /**
     * Almacena una nueva unidad de medida en la base de datos.
     *
     * @param StoreInvUnidadMedidaRequest $request
     * @return JsonResponse
     */
    public function store(StoreInvUnidadMedidaRequest $request): JsonResponse
    {
        $unidad = InvUnidadMedida::create([
            'nombre'      => $request->nombre,
            'abreviatura' => $request->abreviatura,
            'status'      => $request->status ?? 1,
        ]);

        return response()->json([
            'message' => 'Unidad de medida creada exitosamente.',
            'data'    => new InvUnidadMedidaResource($unidad),
        ], 201);
    }

    /**
     * Muestra la unidad de medida especificada.
     *
     * @param InvUnidadMedida $unidadMedida
     * @return JsonResponse
     */
    public function show(InvUnidadMedida $unidadMedida): JsonResponse
    {
        $unidadMedida->loadCount('productos');

        return response()->json([
            'data' => new InvUnidadMedidaResource($unidadMedida),
        ]);
    }

    /**
     * Actualiza la unidad de medida especificada.
     *
     * @param UpdateInvUnidadMedidaRequest $request
     * @param InvUnidadMedida $unidadMedida
     * @return JsonResponse
     */
    public function update(UpdateInvUnidadMedidaRequest $request, InvUnidadMedida $unidadMedida): JsonResponse
    {
        $unidadMedida->update($request->only(['nombre', 'abreviatura', 'status']));

        return response()->json([
            'message' => 'Unidad de medida actualizada exitosamente.',
            'data'    => new InvUnidadMedidaResource($unidadMedida),
        ]);
    }

    /**
     * Elimina la unidad de medida (soft delete). No permite eliminar si tiene productos.
     *
     * @param InvUnidadMedida $unidadMedida
     * @return JsonResponse
     */
    public function destroy(InvUnidadMedida $unidadMedida): JsonResponse
    {
        if ($unidadMedida->productos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la unidad de medida porque tiene productos asociados.',
            ], 422);
        }

        $unidadMedida->delete();

        return response()->json([
            'message' => 'Unidad de medida eliminada exitosamente.',
        ]);
    }

    /**
     * Restaura una unidad de medida eliminada (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $unidad = InvUnidadMedida::onlyTrashed()->findOrFail($id);
        $unidad->restore();

        return response()->json([
            'message' => 'Unidad de medida restaurada exitosamente.',
            'data'    => new InvUnidadMedidaResource($unidad),
        ]);
    }

    /**
     * Elimina permanentemente una unidad de medida.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $unidad = InvUnidadMedida::onlyTrashed()->findOrFail($id);

        if ($unidad->productos()->withTrashed()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente la unidad de medida porque tiene productos asociados.',
            ], 422);
        }

        $unidad->forceDelete();

        return response()->json([
            'message' => 'Unidad de medida eliminada permanentemente.',
        ]);
    }

    /**
     * Obtiene solo las unidades de medida eliminadas (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status']);

        $unidades = InvUnidadMedida::onlyTrashed()
            ->withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->withCount('productos')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvUnidadMedidaResource::collection($unidades),
            'meta' => [
                'current_page' => $unidades->currentPage(),
                'last_page'    => $unidades->lastPage(),
                'per_page'     => $unidades->perPage(),
                'total'        => $unidades->total(),
                'from'         => $unidades->firstItem(),
                'to'           => $unidades->lastItem(),
            ],
        ]);
    }

    /**
     * Obtiene las opciones de filtros disponibles para unidades de medida.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => InvUnidadMedida::getActiveStatusOptions(),
            ],
        ]);
    }
}
