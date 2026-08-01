<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\CompletarInvEntregaKitRequest;
use App\Http\Resources\Api\Inventarios\InvEntregaKitResource;
use App\Http\Resources\Api\Inventarios\InvEntregaSimpleResource;
use App\Http\Resources\Api\Inventarios\InvNecesidadCompraResource;
use App\Http\Resources\Api\Inventarios\InvPedidoResource;
use App\Models\Inventarios\InvEntregaKit;
use App\Models\Inventarios\InvEntregaSimple;
use App\Models\Inventarios\InvNecesidadCompra;
use App\Models\Inventarios\InvPedido;
use App\Services\Inventarios\InvDespachoService;
use App\Services\Inventarios\InvEntregaKitService;
use App\Services\Inventarios\InvEntregaSimpleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para gestionar las entregas de pedidos de inventario.
 *
 * Permite ver las entregas pendientes y completarlas manualmente cuando
 * el stock no estaba disponible al momento del despacho inicial.
 *
 * @package App\Http\Controllers\Api\Inventarios
 */
class InvEntregaController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inv_entregas')->only(['pendientes', 'necesidades']);
        $this->middleware('permission:inv_entregasCompletar')->only(['completarSimple', 'completarKit']);
    }

    /**
     * Lista los pedidos con entregas pendientes (status pagado o entregando).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pendientes(Request $request): JsonResponse
    {
        $pedidos = InvPedido::pendientesEntrega()
            ->when(
                $request->filled('almacen_id'),
                fn ($q) => $q->where('almacen_id', $request->almacen_id)
            )
            ->with([
                'estudiante',
                'almacen',
                'items.producto',
                'items.entregaSimple',
                'items.entregaKit.componentes.productoEntregado',
            ])
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
     * Completa manualmente la entrega de un ítem simple que estaba pendiente por falta de stock.
     *
     * @param int     $entregaId  ID de la InvEntregaSimple pendiente
     * @param Request $request
     * @return JsonResponse
     */
    public function completarSimple(int $entregaId, Request $request): JsonResponse
    {
        $entrega = InvEntregaSimple::findOrFail($entregaId);

        if ($entrega->status === InvEntregaSimple::STATUS_ENTREGADO) {
            return response()->json(['message' => 'La entrega ya fue completada.'], 422);
        }

        $resultado = DB::transaction(function () use ($entrega, $request) {
            $item   = $entrega->pedidoItem()->with('pedido')->firstOrFail();
            $cajeroId = $request->user()->id;

            $entregaActualizada = InvEntregaSimpleService::entregar($item, $cajeroId);

            if ($entregaActualizada->status === InvEntregaSimple::STATUS_ENTREGADO) {
                InvDespachoService::actualizarStatusPedido($item->pedido);
            }

            return $entregaActualizada->fresh(['usuario']);
        });

        return response()->json([
            'message' => $resultado->status === InvEntregaSimple::STATUS_ENTREGADO
                ? 'Entrega completada exitosamente.'
                : 'Stock insuficiente — la necesidad de compra se mantiene activa.',
            'data'    => new InvEntregaSimpleResource($resultado),
        ]);
    }

    /**
     * Completa manualmente la entrega de componentes de un kit pendiente.
     * El cajero elige las variantes de componentes de tipo grupo.
     *
     * @param CompletarInvEntregaKitRequest $request
     * @param int                           $entregaKitId  ID de la InvEntregaKit pendiente
     * @return JsonResponse
     */
    public function completarKit(CompletarInvEntregaKitRequest $request, int $entregaKitId): JsonResponse
    {
        $entregaKit = InvEntregaKit::findOrFail($entregaKitId);

        if ($entregaKit->status === InvEntregaKit::STATUS_COMPLETO) {
            return response()->json(['message' => 'La entrega del kit ya fue completada.'], 422);
        }

        $resultado = DB::transaction(function () use ($entregaKit, $request) {
            $item     = $entregaKit->pedidoItem()->with('pedido')->firstOrFail();
            $cajeroId = $request->user()->id;

            $variantes = collect($request->componentes)->keyBy('kit_componente_id')->toArray();

            $entregaActualizada = InvEntregaKitService::iniciarEntrega($item, $cajeroId, $variantes);

            InvDespachoService::actualizarStatusPedido($item->pedido);

            return $entregaActualizada->fresh(['componentes.productoEntregado', 'usuario']);
        });

        return response()->json([
            'message' => $resultado->status === InvEntregaKit::STATUS_COMPLETO
                ? 'Kit entregado completamente.'
                : 'Entrega parcial registrada — algunos componentes siguen pendientes.',
            'data'    => new InvEntregaKitResource($resultado),
        ]);
    }

    /**
     * Lista las necesidades de compra pendientes del módulo de inventarios.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function necesidades(Request $request): JsonResponse
    {
        $necesidades = InvNecesidadCompra::pendientes()
            ->when(
                $request->filled('almacen_id'),
                fn ($q) => $q->where('almacen_id', $request->almacen_id)
            )
            ->when(
                $request->filled('producto_id'),
                fn ($q) => $q->where('producto_id', $request->producto_id)
            )
            ->with(['producto', 'almacen', 'estudiante'])
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvNecesidadCompraResource::collection($necesidades),
            'meta' => [
                'current_page' => $necesidades->currentPage(),
                'last_page'    => $necesidades->lastPage(),
                'per_page'     => $necesidades->perPage(),
                'total'        => $necesidades->total(),
                'from'         => $necesidades->firstItem(),
                'to'           => $necesidades->lastItem(),
            ],
        ]);
    }
}
