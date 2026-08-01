<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Inventarios\InvPedidoResource;
use App\Models\Inventarios\InvPedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para consulta y gestión de pedidos de inventario.
 *
 * Los pedidos se crean vía InvVentaController; aquí se consultan,
 * filtran y pueden cancelarse si aún están activos.
 *
 * @package App\Http\Controllers\Api\Inventarios
 */
class InvPedidoController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inv_pedidos')->only(['index', 'show', 'porEstudiante']);
        $this->middleware('permission:inv_pedidosCancelar')->only(['cancelar']);
    }

    /**
     * Lista paginada de pedidos con filtros de status, sede, almacén y estudiante.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'sede_id', 'estudiante_id', 'almacen_id']);

        $pedidos = InvPedido::withFilters($filters)
            ->with(['estudiante', 'sede', 'almacen', 'cajero'])
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvPedidoResource::collection($pedidos),
            'meta' => [
                'current_page' => $pedidos->currentPage(),
                'last_page'    => $pedidos->lastPage(),
                'per_page'     => $pedidos->perPage(),
                'total'        => $pedidos->total(),
                'from'         => $pedidos->firstItem(),
                'to'           => $pedidos->lastItem(),
            ],
        ]);
    }

    /**
     * Muestra el detalle completo de un pedido, incluyendo sus ítems y entregas.
     *
     * @param InvPedido $pedido
     * @return JsonResponse
     */
    public function show(InvPedido $pedido): JsonResponse
    {
        $pedido->load([
            'estudiante',
            'sede',
            'almacen',
            'cajero',
            'items.producto',
            'items.entregaSimple',
            'items.entregaKit.componentes.productoEntregado',
            'reciboLinks',
        ]);

        return response()->json([
            'data' => new InvPedidoResource($pedido),
        ]);
    }

    /**
     * Lista los pedidos activos de un estudiante.
     * Devuelve los que aún tienen saldo pendiente.
     *
     * @param int     $estudianteId
     * @param Request $request
     * @return JsonResponse
     */
    public function porEstudiante(int $estudianteId, Request $request): JsonResponse
    {
        $pedidos = InvPedido::where('estudiante_id', $estudianteId)
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->status)
            )
            ->with(['almacen', 'items.producto'])
            ->latest()
            ->get();

        return response()->json([
            'data' => InvPedidoResource::collection($pedidos),
            'meta' => ['total' => $pedidos->count()],
        ]);
    }

    /**
     * Cancela un pedido activo.
     * Solo se puede cancelar si el status es 'activo' (saldo pendiente).
     *
     * @param InvPedido $pedido
     * @return JsonResponse
     */
    public function cancelar(InvPedido $pedido): JsonResponse
    {
        if ($pedido->status !== InvPedido::STATUS_ACTIVO) {
            return response()->json([
                'message' => 'Solo se pueden cancelar pedidos activos.',
            ], 422);
        }

        $pedido->update(['status' => InvPedido::STATUS_CANCELADO]);

        return response()->json([
            'message' => 'Pedido cancelado exitosamente.',
            'data'    => new InvPedidoResource($pedido),
        ]);
    }
}
