<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\StoreInvCategoriaRequest;
use App\Http\Requests\Api\Inventarios\UpdateInvCategoriaRequest;
use App\Http\Resources\Api\Inventarios\InvCategoriaResource;
use App\Models\Inventarios\InvCategoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de categorías del inventario.
 *
 * Administra el ciclo de vida de las categorías: CRUD, soft delete,
 * restauración y eliminación permanente.
 *
 * @package App\Http\Controllers\Api\Inventarios
 */
class InvCategoriaController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inv_categorias')->only(['index', 'show', 'filters', 'activas']);
        $this->middleware('permission:inv_categoriasCrear')->only(['store']);
        $this->middleware('permission:inv_categoriasEditar')->only(['update']);
        $this->middleware('permission:inv_categoriasInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista paginada de categorías con filtros y ordenamiento.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status']);

        $categorias = InvCategoria::withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->withCount('productos')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvCategoriaResource::collection($categorias),
            'meta' => [
                'current_page' => $categorias->currentPage(),
                'last_page'    => $categorias->lastPage(),
                'per_page'     => $categorias->perPage(),
                'total'        => $categorias->total(),
                'from'         => $categorias->firstItem(),
                'to'           => $categorias->lastItem(),
            ],
        ]);
    }

    /**
     * Lista todas las categorías activas sin paginación (para selectores).
     *
     * @return JsonResponse
     */
    public function activas(): JsonResponse
    {
        $categorias = InvCategoria::where('status', 1)->orderBy('nombre')->get();

        return response()->json([
            'data' => InvCategoriaResource::collection($categorias),
            'meta' => [
                'total'       => $categorias->count(),
                'scope'       => 'activas',
                'descripcion' => 'Categorías activas y sin eliminación lógica.',
            ],
        ]);
    }

    /**
     * Almacena una nueva categoría en la base de datos.
     *
     * @param StoreInvCategoriaRequest $request
     * @return JsonResponse
     */
    public function store(StoreInvCategoriaRequest $request): JsonResponse
    {
        $categoria = InvCategoria::create([
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion,
            'status'      => $request->status ?? 1,
        ]);

        return response()->json([
            'message' => 'Categoría creada exitosamente.',
            'data'    => new InvCategoriaResource($categoria),
        ], 201);
    }

    /**
     * Muestra la categoría especificada.
     *
     * @param InvCategoria $categoria
     * @return JsonResponse
     */
    public function show(InvCategoria $categoria): JsonResponse
    {
        $categoria->loadCount('productos');

        return response()->json([
            'data' => new InvCategoriaResource($categoria),
        ]);
    }

    /**
     * Actualiza la categoría especificada en la base de datos.
     *
     * @param UpdateInvCategoriaRequest $request
     * @param InvCategoria $categoria
     * @return JsonResponse
     */
    public function update(UpdateInvCategoriaRequest $request, InvCategoria $categoria): JsonResponse
    {
        $categoria->update($request->only(['nombre', 'descripcion', 'status']));

        return response()->json([
            'message' => 'Categoría actualizada exitosamente.',
            'data'    => new InvCategoriaResource($categoria),
        ]);
    }

    /**
     * Elimina la categoría (soft delete). No permite eliminar si tiene productos activos.
     *
     * @param InvCategoria $categoria
     * @return JsonResponse
     */
    public function destroy(InvCategoria $categoria): JsonResponse
    {
        if ($categoria->productos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene productos asociados.',
            ], 422);
        }

        $categoria->delete();

        return response()->json([
            'message' => 'Categoría eliminada exitosamente.',
        ]);
    }

    /**
     * Restaura una categoría eliminada (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $categoria = InvCategoria::onlyTrashed()->findOrFail($id);
        $categoria->restore();

        return response()->json([
            'message' => 'Categoría restaurada exitosamente.',
            'data'    => new InvCategoriaResource($categoria),
        ]);
    }

    /**
     * Elimina permanentemente una categoría (solo si está en soft delete y sin productos).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $categoria = InvCategoria::onlyTrashed()->findOrFail($id);

        if ($categoria->productos()->withTrashed()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente la categoría porque tiene productos asociados.',
            ], 422);
        }

        $categoria->forceDelete();

        return response()->json([
            'message' => 'Categoría eliminada permanentemente.',
        ]);
    }

    /**
     * Obtiene solo las categorías eliminadas (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status']);

        $categorias = InvCategoria::onlyTrashed()
            ->withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->withCount('productos')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvCategoriaResource::collection($categorias),
            'meta' => [
                'current_page' => $categorias->currentPage(),
                'last_page'    => $categorias->lastPage(),
                'per_page'     => $categorias->perPage(),
                'total'        => $categorias->total(),
                'from'         => $categorias->firstItem(),
                'to'           => $categorias->lastItem(),
            ],
        ]);
    }

    /**
     * Obtiene las opciones de filtros disponibles para el módulo de categorías.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => InvCategoria::getActiveStatusOptions(),
            ],
        ]);
    }
}
