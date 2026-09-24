<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\CompletarInvEntregaKitRequest;
use App\Http\Requests\Api\Inventarios\CompletarInvEntregaSimpleRequest;
use App\Http\Requests\Api\Inventarios\EntregarComponentesKitRequest;
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
use App\Services\Inventarios\InvEntregaPendienteService;
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
        $this->middleware('permission:inv_entregasCompletar')->only(['completarSimple', 'completarKit', 'entregarComponentes']);
    }

    /**
     * Lista los pedidos con entregas pendientes (status pagado o entregando).
     *
     * Cada ítem incluye su registro de entrega — existe siempre desde que el pedido
     * queda pagado — enriquecido con el nombre y tipo de cada componente de kit, el
     * stock en el almacén del pedido y las variantes elegibles.
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

        // Nombre y tipo de cada componente, stock en el almacén del pedido y
        // variantes elegibles: datos que la pantalla necesita y que no están en
        // las tablas de entrega.
        InvEntregaPendienteService::enriquecer($pedidos->getCollection());

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
     * Entrega un ítem simple pendiente, total o parcialmente.
     *
     * Sin `cantidad` entrega todo lo que el stock permita; con `cantidad` entrega
     * como máximo esa cifra. Si el stock no cubre todo, la entrega queda en
     * 'parcial' y el faltante mantiene viva su necesidad de compra.
     *
     * Si el ítem está marcado con `entrega_completa`, no se descarga nada mientras
     * el stock no cubra toda la cantidad pendiente, salvo `forzar_parcial: true`.
     *
     * @param CompletarInvEntregaSimpleRequest $request
     * @param int                              $entregaId  ID de la InvEntregaSimple pendiente
     * @return JsonResponse
     */
    public function completarSimple(CompletarInvEntregaSimpleRequest $request, int $entregaId): JsonResponse
    {
        $entrega = InvEntregaSimple::findOrFail($entregaId);

        if ($entrega->status === InvEntregaSimple::STATUS_ENTREGADO) {
            return response()->json(['message' => 'La entrega ya fue completada.'], 422);
        }

        $resultado = DB::transaction(function () use ($entrega, $request) {
            $item     = $entrega->pedidoItem()->with('pedido')->firstOrFail();
            $cajeroId = $request->user()->id;

            $entregaActualizada = InvEntregaSimpleService::entregar(
                $item,
                $cajeroId,
                $request->filled('cantidad') ? (int) $request->cantidad : null,
                $request->boolean('forzar_parcial')
            );

            InvDespachoService::actualizarStatusPedido($item->pedido);

            return $entregaActualizada->fresh(['usuario']);
        });

        $item = $resultado->pedidoItem;

        return response()->json([
            'message' => match (true) {
                $resultado->status === InvEntregaSimple::STATUS_ENTREGADO =>
                    'Entrega completada exitosamente.',
                $resultado->status === InvEntregaSimple::STATUS_PARCIAL =>
                    'Entrega parcial registrada — el faltante sigue pendiente.',
                $item && $item->entrega_completa =>
                    'El ítem exige entrega completa y el stock no alcanza: no se descargó inventario.',
                default =>
                    'Stock insuficiente — la necesidad de compra se mantiene activa.',
            },
            'data'    => new InvEntregaSimpleResource($resultado),
        ]);
    }

    /**
     * Entrega parcial dirigida: despacha solo los componentes de kit indicados.
     *
     * A diferencia de completarKit(), que recorre todo el kit, aquí el cajero elige
     * exactamente qué componentes entrega ahora y en qué cantidad. Los componentes
     * no listados quedan intactos para una entrega posterior. Es el flujo para kits
     * cuyos productos se retiran en varias visitas o llegan a bodega por partes.
     *
     * Si el ítem está marcado con `entrega_completa`, la entrega parcial se rechaza
     * sin descargar inventario, salvo que se envíe `forzar_parcial: true`.
     *
     * @param EntregarComponentesKitRequest $request
     * @param int                           $entregaKitId  ID de la InvEntregaKit
     * @return JsonResponse
     */
    public function entregarComponentes(EntregarComponentesKitRequest $request, int $entregaKitId): JsonResponse
    {
        $entregaKit = InvEntregaKit::findOrFail($entregaKitId);

        if ($entregaKit->status === InvEntregaKit::STATUS_COMPLETO) {
            return response()->json(['message' => 'La entrega del kit ya fue completada.'], 422);
        }

        $resultado = DB::transaction(function () use ($entregaKit, $request) {
            $cajeroId = $request->user()->id;

            $entregaActualizada = InvEntregaKitService::entregarComponentes(
                $entregaKit,
                $cajeroId,
                $request->componentes,
                $request->boolean('forzar_parcial')
            );

            $item = $entregaKit->pedidoItem()->with('pedido')->firstOrFail();
            InvDespachoService::actualizarStatusPedido($item->pedido);

            return $entregaActualizada->fresh(['componentes.productoEntregado', 'usuario']);
        });

        $item = $resultado->pedidoItem;

        return response()->json([
            'message' => match (true) {
                $resultado->status === InvEntregaKit::STATUS_COMPLETO =>
                    'Kit entregado completamente.',
                $item && $item->entrega_completa && $resultado->status === InvEntregaKit::STATUS_PENDIENTE =>
                    'El kit exige entrega completa y falta stock de algún componente: no se descargó inventario.',
                default =>
                    'Entrega parcial registrada — quedan componentes pendientes.',
            },
            'data'    => new InvEntregaKitResource($resultado),
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
     * Incluye el `pedido_id` resuelto desde el registro de entrega asociado.
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

        InvEntregaPendienteService::asignarPedidoId($necesidades->getCollection());

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
