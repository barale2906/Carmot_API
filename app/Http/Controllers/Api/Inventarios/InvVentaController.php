<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\AbonarInvPedidoRequest;
use App\Http\Requests\Api\Inventarios\StoreInvVentaRequest;
use App\Http\Resources\Api\Inventarios\InvPedidoResource;
use App\Models\Inventarios\InvPedido;
use App\Services\Inventarios\InvVentaService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador para la creación de ventas de inventario y abonos a pedidos.
 *
 * Crea el pedido con el primer abono (o pago total) y permite registrar
 * abonos posteriores hasta saldar. Cuando el saldo llega a 0, dispara el
 * despacho automáticamente.
 *
 * @package App\Http\Controllers\Api\Inventarios
 */
class InvVentaController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inv_ventasCrear')->only(['store']);
        $this->middleware('permission:inv_ventasAbonar')->only(['abonar']);
    }

    /**
     * Crea un nuevo pedido de inventario con el primer abono o pago total.
     *
     * Resuelve el precio de cada producto desde la lista vigente para la sede.
     * Si el abono cubre el total, dispara el despacho automáticamente.
     *
     * @param StoreInvVentaRequest $request
     * @return JsonResponse
     */
    public function store(StoreInvVentaRequest $request): JsonResponse
    {
        try {
            $resultado = InvVentaService::crearPedido(array_merge(
                $request->only([
                    'estudiante_id',
                    'sede_id',
                    'almacen_id',
                    'items',
                    'monto_abono',
                    'medios_pago',
                    'observaciones',
                    'variantes_kit',
                ]),
                ['cajero_id' => $request->user()->id]
            ));

            return response()->json([
                'message' => 'Pedido creado exitosamente.',
                'data'    => new InvPedidoResource($resultado['pedido']),
                'recibo'  => [
                    'id'            => $resultado['recibo']->id,
                    'numero_recibo' => $resultado['recibo']->numero_recibo,
                    'valor_total'   => $resultado['recibo']->valor_total,
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Registra un abono a un pedido activo.
     *
     * Si el abono cubre el saldo restante, dispara el despacho automáticamente.
     *
     * @param AbonarInvPedidoRequest $request
     * @param InvPedido              $pedido
     * @return JsonResponse
     */
    public function abonar(AbonarInvPedidoRequest $request, InvPedido $pedido): JsonResponse
    {
        try {
            $resultado = InvVentaService::abonarPedido($pedido, array_merge(
                $request->only(['monto_abono', 'medios_pago', 'variantes_kit']),
                ['cajero_id' => $request->user()->id]
            ));

            return response()->json([
                'message' => 'Abono registrado exitosamente.',
                'data'    => new InvPedidoResource($resultado['pedido']),
                'recibo'  => [
                    'id'            => $resultado['recibo']->id,
                    'numero_recibo' => $resultado['recibo']->numero_recibo,
                    'valor_total'   => $resultado['recibo']->valor_total,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
