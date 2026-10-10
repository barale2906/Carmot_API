<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\AnularInvPedidoRequest;
use App\Http\Resources\Api\Inventarios\InvPedidoResource;
use App\Models\Inventarios\InvPedido;
use App\Services\Inventarios\InvAnulacionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

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
        $this->middleware('permission:inv_pedidos')->only(['index', 'show', 'porEstudiante', 'ticketPdf']);
        $this->middleware('permission:inv_pedidosCancelar')->only(['cancelar']);
        $this->middleware('permission:inv_pedidosAnular')->only(['anular']);
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
            ->conAbonoPendienteAprobacion()
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
            'reciboLinks.reciboPago.mediosPago',
            'canceladoPor',
        ])->cargarAbonoPendienteAprobacion();

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
            ->conAbonoPendienteAprobacion()
            ->latest()
            ->get();

        return response()->json([
            'data' => InvPedidoResource::collection($pedidos),
            'meta' => ['total' => $pedidos->count()],
        ]);
    }

    /**
     * Cancela un pedido activo (aún sin pagar del todo, sin entregas).
     * Anula sus recibos de abono y registra el motivo.
     *
     * @param AnularInvPedidoRequest $request
     * @param InvPedido              $pedido
     * @return JsonResponse
     */
    public function cancelar(AnularInvPedidoRequest $request, InvPedido $pedido): JsonResponse
    {
        if ($pedido->status !== InvPedido::STATUS_ACTIVO) {
            return response()->json([
                'message' => 'Solo se pueden cancelar pedidos activos.',
            ], 422);
        }

        return $this->ejecutarAnulacion($request, $pedido, 'Pedido cancelado');
    }

    /**
     * Anula un pedido en cualquier estado.
     *
     * Reintegra al almacén todo lo entregado (completo o parcial), cancela las
     * necesidades de compra, anula los recibos del pedido y registra el motivo.
     * Se rechaza si algún recibo ya está cerrado en caja.
     *
     * @param AnularInvPedidoRequest $request
     * @param InvPedido              $pedido
     * @return JsonResponse
     */
    public function anular(AnularInvPedidoRequest $request, InvPedido $pedido): JsonResponse
    {
        return $this->ejecutarAnulacion($request, $pedido, 'Pedido anulado');
    }

    /**
     * Ejecuta la anulación común a cancelar y anular y arma la respuesta.
     *
     * @param AnularInvPedidoRequest $request
     * @param InvPedido              $pedido
     * @param string                 $accion  Prefijo del mensaje de éxito
     * @return JsonResponse
     */
    private function ejecutarAnulacion(AnularInvPedidoRequest $request, InvPedido $pedido, string $accion): JsonResponse
    {
        try {
            $recibosAnulados = InvAnulacionService::anularPedido(
                $pedido,
                $request->user()->id,
                $request->string('motivo')
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $recibosAnulados > 0
                ? "{$accion}. Se anularon {$recibosAnulados} recibo(s): devuelve el dinero al estudiante."
                : "{$accion}.",
            'data'    => new InvPedidoResource($pedido->fresh('canceladoPor')),
        ]);
    }

    /**
     * Genera el ticket de venta en PDF para un pedido.
     *
     * @param InvPedido $pedido
     * @return HttpResponse
     */
    public function ticketPdf(InvPedido $pedido): HttpResponse
    {
        $pedido->load([
            'estudiante',
            'sede',
            'almacen',
            'cajero',
            'items.producto',
            'reciboLinks',
        ]);

        $pdf = Pdf::loadView('pdf.inv_ticket', ['pedido' => $pedido])
            ->setPaper('a4', 'portrait');

        return $pdf->download("ticket-pedido-{$pedido->id}.pdf");
    }
}
