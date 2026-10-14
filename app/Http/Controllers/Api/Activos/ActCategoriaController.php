<?php

namespace App\Http\Controllers\Api\Activos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Activos\StoreActCategoriaRequest;
use App\Http\Requests\Api\Activos\UpdateActCategoriaRequest;
use App\Http\Resources\Api\Activos\ActCategoriaResource;
use App\Models\Activos\ActCategoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo de categorías de activos fijos.
 */
class ActCategoriaController extends Controller
{
    /**
     * Registra los middlewares de permisos del catálogo.
     */
    public function __construct()
    {
        $this->middleware('permission:act_categorias')->only(['index', 'show']);
        $this->middleware('permission:act_categorias|act_activos')->only(['activas']);
        $this->middleware('permission:act_categoriaCrear')->only(['store']);
        $this->middleware('permission:act_categoriaEditar')->only(['update']);
        $this->middleware('permission:act_categoriaInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Lista paginada de categorías con su cantidad de activos.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $categorias = ActCategoria::withFilters($request->only(['search', 'status']))
            ->withCount('activos')
            ->withSorting($request->get('sort_by', 'nombre'), $request->get('sort_direction', 'asc'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ActCategoriaResource::collection($categorias),
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
     * Categorías activas sin paginar, para los selectores del formulario de activos.
     *
     * @return JsonResponse
     */
    public function activas(): JsonResponse
    {
        return response()->json([
            'data' => ActCategoriaResource::collection(ActCategoria::where('status', 1)->orderBy('nombre')->get()),
        ]);
    }

    /**
     * Crea una categoría.
     *
     * @param StoreActCategoriaRequest $request
     * @return JsonResponse
     */
    public function store(StoreActCategoriaRequest $request): JsonResponse
    {
        $categoria = ActCategoria::create($request->validated() + ['status' => 1]);

        return response()->json([
            'message' => 'Categoría creada exitosamente.',
            'data'    => new ActCategoriaResource($categoria),
        ], 201);
    }

    /**
     * Muestra una categoría.
     *
     * @param ActCategoria $categoria
     * @return JsonResponse
     */
    public function show(ActCategoria $categoria): JsonResponse
    {
        return response()->json([
            'data' => new ActCategoriaResource($categoria->loadCount('activos')),
        ]);
    }

    /**
     * Actualiza una categoría.
     *
     * @param UpdateActCategoriaRequest $request
     * @param ActCategoria $categoria
     * @return JsonResponse
     */
    public function update(UpdateActCategoriaRequest $request, ActCategoria $categoria): JsonResponse
    {
        $categoria->update($request->validated());

        return response()->json([
            'message' => 'Categoría actualizada exitosamente.',
            'data'    => new ActCategoriaResource($categoria),
        ]);
    }

    /**
     * Elimina la categoría (soft delete); no se permite si tiene activos.
     *
     * @param ActCategoria $categoria
     * @return JsonResponse
     */
    public function destroy(ActCategoria $categoria): JsonResponse
    {
        if ($categoria->activos()->withTrashed()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene activos. Inactívala en su lugar.',
            ], 422);
        }

        $categoria->delete();

        return response()->json(['message' => 'Categoría eliminada exitosamente.']);
    }

    /**
     * Restaura una categoría eliminada.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $categoria = ActCategoria::onlyTrashed()->findOrFail($id);
        $categoria->restore();

        return response()->json([
            'message' => 'Categoría restaurada exitosamente.',
            'data'    => new ActCategoriaResource($categoria),
        ]);
    }

    /**
     * Elimina permanentemente una categoría de la papelera.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        ActCategoria::onlyTrashed()->findOrFail($id)->forceDelete();

        return response()->json(['message' => 'Categoría eliminada permanentemente.']);
    }

    /**
     * Categorías en la papelera.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $categorias = ActCategoria::onlyTrashed()
            ->withFilters($request->only(['search']))
            ->orderBy('nombre')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ActCategoriaResource::collection($categorias),
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
}
