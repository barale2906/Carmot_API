<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\SincronizarInvPreciosRequest;
use App\Http\Requests\Api\Inventarios\StoreInvPrecioProductoRequest;
use App\Http\Requests\Api\Inventarios\UpdateInvPrecioProductoRequest;
use App\Http\Resources\Api\Inventarios\InvPrecioProductoResource;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Inventarios\InvPrecioProducto;
use App\Models\Inventarios\InvProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para la gestión de precios de productos de inventario.
 *
 * Administra los precios que se asignan a productos dentro de una lista de precios,
 * permitiendo configurar el valor de venta por lista y población.
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
        $this->middleware('permission:inv_preciosEditar')->only(['update', 'sincronizar']);
        $this->middleware('permission:inv_preciosEliminar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Lista paginada de precios con filtros por lista, producto y vigencia.
     *
     * Por defecto solo se muestran los precios de listas vigentes (estado "Activa" y
     * dentro de su rango de fechas), para no mezclar en pantalla precios de listas
     * activas e inactivas. Para analizar una lista puntual, sin importar su estado,
     * se usa `lista_precio_id`. Para ver todas las listas mezcladas (sin filtro de
     * vigencia) se envía `incluir_no_vigentes=1`.
     *
     * El orden agrupa los resultados por lista de precios (alfabético por nombre) y,
     * dentro de cada lista, por nombre de producto — así la pantalla muestra primero
     * todos los precios de una lista y luego los de la siguiente, en vez de mezclarlos.
     */
    public function index(Request $request): JsonResponse
    {
        $precios = InvPrecioProducto::with(['listaPrecio', 'producto'])
            ->when(
                $request->filled('lista_precio_id'),
                fn ($q) => $q->where('lista_precio_id', $request->lista_precio_id)
            )
            ->when(
                ! $request->filled('lista_precio_id') && ! $request->boolean('incluir_no_vigentes'),
                fn ($q) => $q->whereHas('listaPrecio', fn ($lq) => $lq->vigentes())
            )
            ->when(
                $request->filled('producto_id'),
                fn ($q) => $q->where('producto_id', $request->producto_id)
            )
            ->when(
                $request->filled('search'),
                fn ($q) => $q->whereHas('producto', fn ($pq) => $pq->where('nombre', 'like', "%{$request->search}%")
                    ->orWhere('codigo', 'like', "%{$request->search}%")
                )
            )
            ->orderBy(
                LpListaPrecio::select('nombre')->whereColumn('lp_listas_precios.id', 'inv_precios_producto.lista_precio_id')
            )
            ->orderBy(
                InvProducto::select('nombre')->whereColumn('inv_productos.id', 'inv_precios_producto.producto_id')
            )
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvPrecioProductoResource::collection($precios),
            'meta' => [
                'current_page' => $precios->currentPage(),
                'last_page' => $precios->lastPage(),
                'per_page' => $precios->perPage(),
                'total' => $precios->total(),
                'from' => $precios->firstItem(),
                'to' => $precios->lastItem(),
            ],
        ]);
    }

    /**
     * Crea o actualiza el precio de un producto en una lista de precios.
     * Usa updateOrCreate para manejar correctamente duplicados y soft-deletes previos.
     */
    public function store(StoreInvPrecioProductoRequest $request): JsonResponse
    {
        $precio = InvPrecioProducto::withTrashed()->updateOrCreate(
            [
                'lista_precio_id' => $request->lista_precio_id,
                'producto_id' => $request->producto_id,
            ],
            [
                'precio' => $request->precio,
                'observaciones' => $request->observaciones,
                'deleted_at' => null,
            ]
        );
        $precio->load(['listaPrecio', 'producto']);

        return response()->json([
            'message' => 'Precio guardado exitosamente.',
            'data' => new InvPrecioProductoResource($precio),
        ], 201);
    }

    /**
     * Muestra el precio especificado.
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
     */
    public function update(UpdateInvPrecioProductoRequest $request, InvPrecioProducto $precio): JsonResponse
    {
        $precio->update($request->only(['precio', 'observaciones']));
        $precio->load(['listaPrecio', 'producto']);

        return response()->json([
            'message' => 'Precio actualizado exitosamente.',
            'data' => new InvPrecioProductoResource($precio),
        ]);
    }

    /**
     * Elimina el precio (soft delete).
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
     */
    public function restore(int $id): JsonResponse
    {
        $precio = InvPrecioProducto::onlyTrashed()->findOrFail($id);
        $precio->restore();

        return response()->json([
            'message' => 'Precio restaurado exitosamente.',
            'data' => new InvPrecioProductoResource($precio),
        ]);
    }

    /**
     * Elimina permanentemente el precio.
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
                'last_page' => $precios->lastPage(),
                'per_page' => $precios->perPage(),
                'total' => $precios->total(),
                'from' => $precios->firstItem(),
                'to' => $precios->lastItem(),
            ],
        ]);
    }

    /**
     * Lista todos los precios vigentes de un producto dado.
     * Útil para que el frontend muestre el precio según la sede del estudiante.
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

    /**
     * Sincroniza masivamente los precios de una lista de inventario.
     *
     * Hace upsert de los ítems enviados y soft-delete de los precios de la lista
     * que no estén en el payload. La lista debe estar en estado "En Proceso".
     *
     * Comportamiento por tipo de producto:
     *  - simple / kit: se asigna el precio directamente al producto.
     *  - grupo: el precio se propaga a todas sus variantes activas (tipo=simple con producto_padre_id=grupo.id).
     */
    public function sincronizar(SincronizarInvPreciosRequest $request, LpListaPrecio $listaPrecio): JsonResponse
    {
        abort_if($listaPrecio->origen !== 0, 404);

        if ($listaPrecio->status !== LpListaPrecio::STATUS_EN_PROCESO) {
            return response()->json([
                'message' => 'Solo se pueden editar precios en listas con estado "En Proceso".',
            ], 422);
        }

        DB::beginTransaction();

        // Expandir grupos a sus variantes; simple/kit pasan directo
        $idsAConservar = collect();
        $itemsExpandidos = collect();

        foreach ($request->input('items') as $item) {
            $producto = InvProducto::find($item['producto_id']);

            if ($producto->tipo === 'grupo') {
                foreach ($producto->variantes()->where('status', 1)->get() as $variante) {
                    $itemsExpandidos->put($variante->id, [
                        'precio' => $item['precio'],
                        'observaciones' => $item['observaciones'] ?? null,
                    ]);
                    $idsAConservar->push($variante->id);
                }
            } else {
                $itemsExpandidos->put($item['producto_id'], [
                    'precio' => $item['precio'],
                    'observaciones' => $item['observaciones'] ?? null,
                ]);
                $idsAConservar->push($item['producto_id']);
            }
        }

        // Soft-delete de precios no incluidos en el payload expandido
        InvPrecioProducto::where('lista_precio_id', $listaPrecio->id)
            ->whereNull('deleted_at')
            ->whereNotIn('producto_id', $idsAConservar)
            ->each(fn ($p) => $p->delete());

        // Upsert de cada ítem expandido
        foreach ($itemsExpandidos as $productoId => $datos) {
            InvPrecioProducto::withTrashed()->updateOrCreate(
                ['lista_precio_id' => $listaPrecio->id, 'producto_id' => $productoId],
                [
                    'precio' => $datos['precio'],
                    'observaciones' => $datos['observaciones'],
                    'deleted_at' => null,
                ]
            );
        }

        DB::commit();

        $precios = InvPrecioProducto::where('lista_precio_id', $listaPrecio->id)
            ->with('producto')
            ->get();

        return response()->json([
            'message' => 'Precios sincronizados exitosamente.',
            'data' => InvPrecioProductoResource::collection($precios),
        ]);
    }
}
