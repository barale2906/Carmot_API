<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\CloneInvListaPrecioRequest;
use App\Http\Requests\Api\Inventarios\StoreInvListaPrecioRequest;
use App\Http\Requests\Api\Inventarios\UpdateInvListaPrecioRequest;
use App\Http\Resources\Api\Inventarios\InvListaPrecioResource;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Inventarios\InvPrecioProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para la gestión de listas de precios del módulo de inventarios.
 *
 * Administra listas de precios con origen=0 (inventarios), que definen los precios
 * de venta de productos para un período de vigencia y una o más poblaciones.
 * Sigue el flujo de estados: En Proceso → Aprobada → Activa / Inactiva.
 *
 * @package App\Http\Controllers\Api\Inventarios
 */
class InvListaPrecioController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inv_listas')->only(['index', 'show']);
        $this->middleware('permission:inv_listasCrear')->only(['store']);
        $this->middleware('permission:inv_listasEditar')->only(['update']);
        $this->middleware('permission:inv_listasInactivar')->only(['destroy', 'inactivar']);
        $this->middleware('permission:inv_listasAprobar')->only(['aprobar', 'activar']);
        $this->middleware('permission:inv_listasClonar')->only(['clonar']);
    }

    /**
     * Lista paginada de listas de precios de inventario con filtros opcionales.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $listas = LpListaPrecio::where('origen', 0)
            ->with('poblaciones')
            ->withCount('preciosInventario')
            ->when(
                $request->filled('search'),
                fn ($q) => $q->where('nombre', 'like', "%{$request->search}%")
            )
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->integer('status'))
            )
            ->when(
                $request->filled('poblacion_id'),
                fn ($q) => $q->whereHas(
                    'poblaciones',
                    fn ($pq) => $pq->where('poblacions.id', $request->integer('poblacion_id'))
                )
            )
            ->when(
                $request->boolean('vigentes'),
                fn ($q) => $q->vigentes()
            )
            ->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_direction', 'desc'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvListaPrecioResource::collection($listas),
            'meta' => [
                'current_page' => $listas->currentPage(),
                'last_page'    => $listas->lastPage(),
                'per_page'     => $listas->perPage(),
                'total'        => $listas->total(),
                'from'         => $listas->firstItem(),
                'to'           => $listas->lastItem(),
            ],
        ]);
    }

    /**
     * Crea una nueva lista de precios de inventario (origen=0, status=En Proceso).
     *
     * @param StoreInvListaPrecioRequest $request
     * @return JsonResponse
     */
    public function store(StoreInvListaPrecioRequest $request): JsonResponse
    {
        DB::beginTransaction();

        $lista = LpListaPrecio::create(array_merge(
            $request->only(['nombre', 'fecha_inicio', 'fecha_fin', 'descripcion']),
            [
                'origen' => 0,
                'status' => $request->integer('status', LpListaPrecio::STATUS_EN_PROCESO),
            ]
        ));

        $lista->poblaciones()->attach($request->input('poblaciones', []));

        DB::commit();

        $lista->load('poblaciones');
        $lista->loadCount('preciosInventario');

        return response()->json([
            'message' => 'Lista de precios creada exitosamente.',
            'data'    => new InvListaPrecioResource($lista),
        ], 201);
    }

    /**
     * Muestra el detalle de una lista de precios de inventario con sus precios y poblaciones.
     *
     * @param LpListaPrecio $listaPrecio
     * @return JsonResponse
     */
    public function show(LpListaPrecio $listaPrecio): JsonResponse
    {
        abort_if($listaPrecio->origen !== 0, 404);

        $listaPrecio->load(['poblaciones', 'preciosInventario.producto']);

        return response()->json([
            'data' => new InvListaPrecioResource($listaPrecio),
        ]);
    }

    /**
     * Actualiza los datos descriptivos y las poblaciones de una lista de inventario.
     *
     * Solo se permite modificar listas en estado En Proceso.
     *
     * @param UpdateInvListaPrecioRequest $request
     * @param LpListaPrecio              $listaPrecio
     * @return JsonResponse
     */
    public function update(UpdateInvListaPrecioRequest $request, LpListaPrecio $listaPrecio): JsonResponse
    {
        abort_if($listaPrecio->origen !== 0, 404);

        if ($listaPrecio->status !== LpListaPrecio::STATUS_EN_PROCESO) {
            return response()->json([
                'message' => 'Solo se pueden editar listas en estado "En Proceso".',
            ], 422);
        }

        DB::beginTransaction();

        $listaPrecio->update($request->only(['nombre', 'fecha_inicio', 'fecha_fin', 'descripcion']));

        if ($request->has('poblaciones')) {
            $listaPrecio->poblaciones()->sync($request->input('poblaciones', []));
        }

        DB::commit();

        $listaPrecio->load('poblaciones');
        $listaPrecio->loadCount('preciosInventario');

        return response()->json([
            'message' => 'Lista de precios actualizada exitosamente.',
            'data'    => new InvListaPrecioResource($listaPrecio->fresh()->load('poblaciones')),
        ]);
    }

    /**
     * Elimina lógicamente una lista de precios de inventario.
     *
     * @param LpListaPrecio $listaPrecio
     * @return JsonResponse
     */
    public function destroy(LpListaPrecio $listaPrecio): JsonResponse
    {
        abort_if($listaPrecio->origen !== 0, 404);

        $listaPrecio->delete();

        return response()->json([
            'message' => 'Lista de precios eliminada exitosamente.',
        ]);
    }

    /**
     * Aprueba una lista de precios pasando de En Proceso → Aprobada.
     *
     * @param LpListaPrecio $listaPrecio
     * @return JsonResponse
     */
    public function aprobar(LpListaPrecio $listaPrecio): JsonResponse
    {
        abort_if($listaPrecio->origen !== 0, 404);

        if ($listaPrecio->status !== LpListaPrecio::STATUS_EN_PROCESO) {
            return response()->json([
                'message' => 'Solo se pueden aprobar listas en estado "En Proceso".',
            ], 422);
        }

        $listaPrecio->update(['status' => LpListaPrecio::STATUS_APROBADA]);

        return response()->json([
            'message' => 'Lista de precios aprobada exitosamente.',
            'data'    => new InvListaPrecioResource($listaPrecio->fresh()->load('poblaciones')),
        ]);
    }

    /**
     * Activa una lista de precios pasando de Aprobada → Activa.
     *
     * @param LpListaPrecio $listaPrecio
     * @return JsonResponse
     */
    public function activar(LpListaPrecio $listaPrecio): JsonResponse
    {
        abort_if($listaPrecio->origen !== 0, 404);

        if ($listaPrecio->status !== LpListaPrecio::STATUS_APROBADA) {
            return response()->json([
                'message' => 'Solo se pueden activar listas en estado "Aprobada".',
            ], 422);
        }

        $listaPrecio->update(['status' => LpListaPrecio::STATUS_ACTIVA]);

        return response()->json([
            'message' => 'Lista de precios activada exitosamente.',
            'data'    => new InvListaPrecioResource($listaPrecio->fresh()->load('poblaciones')),
        ]);
    }

    /**
     * Inactiva una lista de precios (cualquier estado → Inactiva).
     *
     * @param LpListaPrecio $listaPrecio
     * @return JsonResponse
     */
    public function inactivar(LpListaPrecio $listaPrecio): JsonResponse
    {
        abort_if($listaPrecio->origen !== 0, 404);

        $listaPrecio->update(['status' => LpListaPrecio::STATUS_INACTIVA]);

        return response()->json([
            'message' => 'Lista de precios inactivada exitosamente.',
            'data'    => new InvListaPrecioResource($listaPrecio->fresh()->load('poblaciones')),
        ]);
    }

    /**
     * Clona una lista de precios de inventario con sus precios de productos.
     *
     * Crea la nueva lista en estado En Proceso y copia los InvPrecioProducto
     * vigentes de la lista origen si copiar_precios=true (por defecto).
     *
     * @param CloneInvListaPrecioRequest $request
     * @param LpListaPrecio             $listaPrecio
     * @return JsonResponse
     */
    public function clonar(CloneInvListaPrecioRequest $request, LpListaPrecio $listaPrecio): JsonResponse
    {
        abort_if($listaPrecio->origen !== 0, 404);

        DB::beginTransaction();

        $nueva = LpListaPrecio::create([
            'nombre'       => $request->string('nombre'),
            'fecha_inicio' => $request->date('fecha_inicio'),
            'fecha_fin'    => $request->date('fecha_fin'),
            'descripcion'  => $request->input('descripcion', $listaPrecio->descripcion),
            'origen'       => 0,
            'status'       => LpListaPrecio::STATUS_EN_PROCESO,
        ]);

        $poblaciones = $request->has('poblaciones')
            ? $request->input('poblaciones')
            : $listaPrecio->poblaciones()->pluck('poblacions.id')->toArray();

        if (!empty($poblaciones)) {
            $nueva->poblaciones()->attach($poblaciones);
        }

        $preciosCopiados = 0;

        if ($request->boolean('copiar_precios', true)) {
            $listaPrecio->preciosInventario()->whereNull('deleted_at')->each(function ($precio) use ($nueva, &$preciosCopiados) {
                InvPrecioProducto::create([
                    'lista_precio_id' => $nueva->id,
                    'producto_id'     => $precio->producto_id,
                    'precio'          => $precio->precio,
                    'observaciones'   => $precio->observaciones,
                ]);
                $preciosCopiados++;
            });
        }

        DB::commit();

        $nueva->load('poblaciones');
        $nueva->loadCount('preciosInventario');

        return response()->json([
            'message'         => 'Lista de precios clonada exitosamente.',
            'precios_copiados' => $preciosCopiados,
            'data'            => new InvListaPrecioResource($nueva),
        ], 201);
    }
}
