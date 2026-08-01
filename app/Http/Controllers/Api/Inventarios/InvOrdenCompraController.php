<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\RecibirInvOrdenCompraRequest;
use App\Http\Requests\Api\Inventarios\StoreInvOrdenCompraRequest;
use App\Http\Requests\Api\Inventarios\UpdateInvOrdenCompraRequest;
use App\Http\Resources\Api\Inventarios\InvOrdenCompraResource;
use App\Models\Inventarios\InvOrdenCompra;
use App\Models\Inventarios\InvOrdenCompraItem;
use App\Services\Inventarios\InvRecepcionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para la gestión de órdenes de compra de inventario.
 *
 * Ciclo de vida: borrador → enviada → recibida_parcial → recibida | cancelada.
 * La recepción llama a InvRecepcionService, que genera el movimiento de entrada
 * y actualiza el stock. Órdenes canceladas no pueden recibirse.
 *
 * @package App\Http\Controllers\Api\Inventarios
 */
class InvOrdenCompraController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inv_oc')->only(['index', 'show', 'pendientesRecepcion']);
        $this->middleware('permission:inv_ocCrear')->only(['store']);
        $this->middleware('permission:inv_ocEditar')->only(['update']);
        $this->middleware('permission:inv_ocEnviar')->only(['enviar']);
        $this->middleware('permission:inv_ocRecibir')->only(['recibir']);
        $this->middleware('permission:inv_ocCancelar')->only(['cancelar']);
    }

    /**
     * Lista paginada de órdenes de compra con filtros.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'proveedor_id', 'almacen_id']);

        $ordenes = InvOrdenCompra::withFilters($filters)
            ->with(['proveedor', 'almacen', 'responsable'])
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvOrdenCompraResource::collection($ordenes),
            'meta' => [
                'current_page' => $ordenes->currentPage(),
                'last_page'    => $ordenes->lastPage(),
                'per_page'     => $ordenes->perPage(),
                'total'        => $ordenes->total(),
                'from'         => $ordenes->firstItem(),
                'to'           => $ordenes->lastItem(),
            ],
        ]);
    }

    /**
     * Crea una nueva orden de compra en estado borrador.
     *
     * @param StoreInvOrdenCompraRequest $request
     * @return JsonResponse
     */
    public function store(StoreInvOrdenCompraRequest $request): JsonResponse
    {
        $orden = DB::transaction(function () use ($request) {
            $orden = InvOrdenCompra::create([
                'proveedor_id'   => $request->proveedor_id,
                'almacen_id'     => $request->almacen_id,
                'responsable_id' => $request->user()->id,
                'status'         => InvOrdenCompra::STATUS_BORRADOR,
                'observaciones'  => $request->observaciones,
                'fecha_esperada' => $request->fecha_esperada,
                'subtotal'       => 0,
                'total'          => 0,
            ]);

            $subtotal = 0;
            foreach ($request->items as $item) {
                $precio   = (float) ($item['precio_costo_unitario'] ?? 0);
                $cantidad = (int) $item['cantidad_solicitada'];
                $sub      = round($precio * $cantidad, 2);
                $subtotal += $sub;

                InvOrdenCompraItem::create([
                    'orden_id'              => $orden->id,
                    'producto_id'           => $item['producto_id'],
                    'cantidad_solicitada'   => $cantidad,
                    'precio_costo_unitario' => $precio,
                    'subtotal'              => $sub,
                    'cantidad_recibida'     => 0,
                ]);
            }

            $orden->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            return $orden->load(['proveedor', 'almacen', 'responsable', 'items.producto']);
        });

        return response()->json([
            'message' => 'Orden de compra creada exitosamente.',
            'data'    => new InvOrdenCompraResource($orden),
        ], 201);
    }

    /**
     * Muestra el detalle completo de una orden de compra.
     *
     * @param InvOrdenCompra $ordenCompra
     * @return JsonResponse
     */
    public function show(InvOrdenCompra $ordenCompra): JsonResponse
    {
        $ordenCompra->load(['proveedor', 'almacen', 'responsable', 'items.producto']);

        return response()->json([
            'data' => new InvOrdenCompraResource($ordenCompra),
        ]);
    }

    /**
     * Actualiza una orden en estado borrador.
     * Solo se puede editar si la orden aún no ha sido enviada.
     *
     * @param UpdateInvOrdenCompraRequest $request
     * @param InvOrdenCompra              $ordenCompra
     * @return JsonResponse
     */
    public function update(UpdateInvOrdenCompraRequest $request, InvOrdenCompra $ordenCompra): JsonResponse
    {
        if (!$ordenCompra->esEditable()) {
            return response()->json([
                'message' => 'Solo se pueden editar órdenes en estado borrador.',
            ], 422);
        }

        $orden = DB::transaction(function () use ($request, $ordenCompra) {
            $ordenCompra->update($request->only(['proveedor_id', 'almacen_id', 'observaciones', 'fecha_esperada']));

            if ($request->has('items')) {
                $ordenCompra->items()->delete();
                $subtotal = 0;

                foreach ($request->items as $item) {
                    $precio   = (float) ($item['precio_costo_unitario'] ?? 0);
                    $cantidad = (int) $item['cantidad_solicitada'];
                    $sub      = round($precio * $cantidad, 2);
                    $subtotal += $sub;

                    InvOrdenCompraItem::create([
                        'orden_id'              => $ordenCompra->id,
                        'producto_id'           => $item['producto_id'],
                        'cantidad_solicitada'   => $cantidad,
                        'precio_costo_unitario' => $precio,
                        'subtotal'              => $sub,
                        'cantidad_recibida'     => 0,
                    ]);
                }

                $ordenCompra->update(['subtotal' => $subtotal, 'total' => $subtotal]);
            }

            return $ordenCompra->load(['proveedor', 'almacen', 'responsable', 'items.producto']);
        });

        return response()->json([
            'message' => 'Orden de compra actualizada exitosamente.',
            'data'    => new InvOrdenCompraResource($orden),
        ]);
    }

    /**
     * Envía la orden al proveedor (borrador → enviada).
     * Una vez enviada ya no puede editarse.
     *
     * @param InvOrdenCompra $ordenCompra
     * @return JsonResponse
     */
    public function enviar(InvOrdenCompra $ordenCompra): JsonResponse
    {
        if ($ordenCompra->status !== InvOrdenCompra::STATUS_BORRADOR) {
            return response()->json([
                'message' => 'Solo se pueden enviar órdenes en estado borrador.',
            ], 422);
        }

        if ($ordenCompra->items()->count() === 0) {
            return response()->json([
                'message' => 'No se puede enviar una orden sin ítems.',
            ], 422);
        }

        $ordenCompra->update(['status' => InvOrdenCompra::STATUS_ENVIADA]);

        return response()->json([
            'message' => 'Orden enviada al proveedor.',
            'data'    => new InvOrdenCompraResource($ordenCompra->load(['proveedor', 'almacen'])),
        ]);
    }

    /**
     * Registra la recepción total o parcial de ítems de una orden enviada.
     * Genera documento de entrada de stock y actualiza las existencias.
     *
     * @param RecibirInvOrdenCompraRequest $request
     * @param InvOrdenCompra               $ordenCompra
     * @return JsonResponse
     */
    public function recibir(RecibirInvOrdenCompraRequest $request, InvOrdenCompra $ordenCompra): JsonResponse
    {
        try {
            $documento = InvRecepcionService::recibirItems(
                $ordenCompra,
                $request->items,
                $request->user()->id
            );

            $ordenCompra->refresh()->load(['proveedor', 'almacen', 'items.producto']);

            return response()->json([
                'message'        => 'Recepción registrada exitosamente.',
                'data'           => new InvOrdenCompraResource($ordenCompra),
                'documento_id'   => $documento->id,
                'numero_documento' => $documento->numero_documento,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancela una orden de compra en estado borrador o enviada.
     * No se pueden cancelar órdenes que ya tienen recepciones.
     *
     * @param InvOrdenCompra $ordenCompra
     * @return JsonResponse
     */
    public function cancelar(InvOrdenCompra $ordenCompra): JsonResponse
    {
        if (!in_array($ordenCompra->status, [
            InvOrdenCompra::STATUS_BORRADOR,
            InvOrdenCompra::STATUS_ENVIADA,
        ])) {
            return response()->json([
                'message' => 'Solo se pueden cancelar órdenes en borrador o enviadas.',
            ], 422);
        }

        $ordenCompra->update(['status' => InvOrdenCompra::STATUS_CANCELADA]);

        return response()->json([
            'message' => 'Orden de compra cancelada.',
            'data'    => new InvOrdenCompraResource($ordenCompra),
        ]);
    }

    /**
     * Lista órdenes pendientes de recepción (enviadas o parcialmente recibidas).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pendientesRecepcion(Request $request): JsonResponse
    {
        $ordenes = InvOrdenCompra::pendientesRecepcion()
            ->when(
                $request->filled('almacen_id'),
                fn ($q) => $q->where('almacen_id', $request->almacen_id)
            )
            ->with(['proveedor', 'almacen', 'items.producto'])
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvOrdenCompraResource::collection($ordenes),
            'meta' => [
                'current_page' => $ordenes->currentPage(),
                'last_page'    => $ordenes->lastPage(),
                'per_page'     => $ordenes->perPage(),
                'total'        => $ordenes->total(),
                'from'         => $ordenes->firstItem(),
                'to'           => $ordenes->lastItem(),
            ],
        ]);
    }
}
