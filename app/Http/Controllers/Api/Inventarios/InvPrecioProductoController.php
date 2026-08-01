<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\StoreInvPrecioProductoRequest;
use App\Http\Requests\Api\Inventarios\UpdateInvPrecioProductoRequest;
use App\Http\Resources\Api\Inventarios\InvPrecioProductoResource;
use App\Models\Inventarios\InvPrecioProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de precios de productos de inventario.
 *
 * Administra los precios que se asignan a productos dentro de una lista de precios,
 * permitiendo configurar el valor de venta por lista y población.
 *
 * @package App\Http\Controllers\Api\Inventarios
 */
class InvPrecioProductoController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inv_precios')->only(['index', 'show', 'porProducto']);
        $this->middleware('permission:inv_preciosCrear')->only(['store']);
        $this->middleware('permission:inv_preciosEditar')->only(['update']);
        $this->middleware('permission:inv_preciosEliminar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Lista paginada de precios con filtros por lista y producto.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $precios = InvPrecioProducto::with(['listaPrecio', 'producto'])
            ->when(
                $request->filled('lista_precio_id'),
                fn ($q) => $q->where('lista_precio_id', $request->lista_precio_id)
            )
            ->when(
                $request->filled('producto_id'),
                fn ($q) => $q->where('producto_id', $request->producto_id)
            )
            ->when(
                $request->filled('search'),
                fn ($q) => $q->whereHas('producto', fn ($pq) =>
                    $pq->where('nombre', 'like', "%{$request->search}%")
                       ->orWhere('codigo', 'like', "%{$request->search}%")
                )
            )
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvPrecioProductoResource::collection($precios),
            'meta' => [
                'current_page' => $precios->currentPage(),
                'last_page'    => $precios->lastPage(),
                'per_page'     => $precios->perPage(),
                'total'        => $precios->total(),
                'from'         => $precios->firstItem(),
                'to'           => $precios->lastItem(),
            ],
        ]);
    }

    /**
     * Crea un precio para un producto en una lista de precios.
     *
     * @param StoreInvPrecioProductoRequest $request
     * @return JsonResponse
     */
    public function store(StoreInvPrecioProductoRequest $request): JsonResponse
    {
        $precio = InvPrecioProducto::create($request->only(['lista_precio_id', 'producto_id', 'precio', 'observaciones']));
        $precio->load(['listaPrecio', 'producto']);

        return response()->json([
            'message' => 'Precio creado exitosamente.',
            'data'    => new InvPrecioProductoResource($precio),
        ], 201);
    }

    /**
     * Muestra el precio especificado.
     *
     * @param InvPrecioProducto $precio
     * @return JsonResponse
     */
    public function show(InvPrecioProducto $precio): JsonResponse
    {
        $precio->load(['listaPrecio', 'producto']);

        return response()->json([
            'data' => new InvPrecioProductoResource($precio),
        ]);
    }

    /**
     * Actualiza el valor y observaciones del precio especificado.
     *
     * @param UpdateInvPrecioProductoRequest $request
     * @param InvPrecioProducto              $precio
     * @return JsonResponse
     */
    public function update(UpdateInvPrecioProductoRequest $request, InvPrecioProducto $precio): JsonResponse
    {
        $precio->update($request->only(['precio', 'observaciones']));
        $precio->load(['listaPrecio', 'producto']);

        return response()->json([
            'message' => 'Precio actualizado exitosamente.',
            'data'    => new InvPrecioProductoResource($precio),
        ]);
    }

    /**
     * Elimina el precio (soft delete).
     *
     * @param InvPrecioProducto $precio
     * @return JsonResponse
     */
    public function destroy(InvPrecioProducto $precio): JsonResponse
    {
        $precio->delete();

        return response()->json([
            'message' => 'Precio eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un precio eliminado lógicamente.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $precio = InvPrecioProducto::onlyTrashed()->findOrFail($id);
        $precio->restore();

        return response()->json([
            'message' => 'Precio restaurado exitosamente.',
            'data'    => new InvPrecioProductoResource($precio),
        ]);
    }

    /**
     * Elimina permanentemente el precio.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $precio = InvPrecioProducto::onlyTrashed()->findOrFail($id);
        $precio->forceDelete();

        return response()->json([
            'message' => 'Precio eliminado permanentemente.',
        ]);
    }

    /**
     * Lista los precios eliminados lógicamente.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $precios = InvPrecioProducto::onlyTrashed()
            ->with(['listaPrecio', 'producto'])
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvPrecioProductoResource::collection($precios),
            'meta' => [
                'current_page' => $precios->currentPage(),
                'last_page'    => $precios->lastPage(),
                'per_page'     => $precios->perPage(),
                'total'        => $precios->total(),
                'from'         => $precios->firstItem(),
                'to'           => $precios->lastItem(),
            ],
        ]);
    }

    /**
     * Lista todos los precios vigentes de un producto dado.
     * Útil para que el frontend muestre el precio según la sede del estudiante.
     *
     * @param int $productoId
     * @return JsonResponse
     */
    public function porProducto(int $productoId): JsonResponse
    {
        $precios = InvPrecioProducto::where('producto_id', $productoId)
            ->with('listaPrecio')
            ->get();

        return response()->json([
            'data' => InvPrecioProductoResource::collection($precios),
        ]);
    }
}
