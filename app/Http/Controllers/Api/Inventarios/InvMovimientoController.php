<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\AnularInvMovimientoRequest;
use App\Http\Requests\Api\Inventarios\StoreInvMovimientoRequest;
use App\Http\Resources\Api\Inventarios\InvDocumentoMovimientoResource;
use App\Models\Inventarios\InvAlmacen;
use App\Models\Inventarios\InvDocumentoMovimiento;
use App\Models\Inventarios\InvMovimiento;
use App\Services\Inventarios\InvStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para el registro y gestión de movimientos de inventario.
 *
 * Cada acción (entrada, salida, traslado, ajuste, devolución) genera un documento
 * cabecera (InvDocumentoMovimiento) con sus líneas (InvMovimiento) y actualiza
 * el stock atómicamente dentro de una transacción.
 *
 * @package App\Http\Controllers\Api\Inventarios
 */
class InvMovimientoController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inv_movimientos')->only(['index', 'show', 'filters']);
        $this->middleware('permission:inv_movimientosRegistrar')->only(['store']);
        $this->middleware('permission:inv_movimientosAnular')->only(['anular']);
    }

    /**
     * Muestra los documentos de movimiento paginados con filtros.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['tipo_documento', 'almacen_id', 'status', 'fecha_inicio', 'fecha_fin']);

        $documentos = InvDocumentoMovimiento::withFilters($filters)
            ->with(['almacen', 'almacenDestino', 'proveedor', 'usuario', 'movimientos.producto'])
            ->withCount('movimientos')
            ->orderBy('id', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvDocumentoMovimientoResource::collection($documentos),
            'meta' => [
                'current_page' => $documentos->currentPage(),
                'last_page'    => $documentos->lastPage(),
                'per_page'     => $documentos->perPage(),
                'total'        => $documentos->total(),
                'from'         => $documentos->firstItem(),
                'to'           => $documentos->lastItem(),
            ],
        ]);
    }

    /**
     * Muestra el detalle de un documento de movimiento con todas sus líneas.
     *
     * @param InvDocumentoMovimiento $movimiento
     * @return JsonResponse
     */
    public function show(InvDocumentoMovimiento $movimiento): JsonResponse
    {
        $movimiento->load([
            'almacen',
            'almacenDestino',
            'proveedor',
            'usuario',
            'movimientos.producto',
            'movimientos.almacen',
            'movimientos.almacenDestino',
        ]);

        return response()->json([
            'data' => new InvDocumentoMovimientoResource($movimiento),
        ]);
    }

    /**
     * Registra un nuevo documento de movimiento con sus líneas y actualiza el stock.
     *
     * Ejecuta todo dentro de una transacción para garantizar consistencia.
     * El documento queda confirmado inmediatamente; no existe estado borrador en este flujo.
     *
     * @param StoreInvMovimientoRequest $request
     * @return JsonResponse
     */
    public function store(StoreInvMovimientoRequest $request): JsonResponse
    {
        $tipo     = $request->tipo_documento;
        $numero   = InvDocumentoMovimiento::generarNumero($tipo);

        try {
            $documento = DB::transaction(function () use ($request, $tipo, $numero) {
                $documento = InvDocumentoMovimiento::create([
                    'numero_documento'   => $numero,
                    'tipo_documento'     => $tipo,
                    'almacen_id'         => $request->almacen_id,
                    'almacen_destino_id' => $request->almacen_destino_id,
                    'proveedor_id'       => $request->proveedor_id,
                    'motivo'             => $request->motivo,
                    'status'             => InvDocumentoMovimiento::STATUS_CONFIRMADO,
                    'user_id'            => $request->user()->id,
                ]);

                foreach ($request->lineas as $linea) {
                    $tipoMovimiento = $this->resolverTipoMovimiento($tipo, $linea);

                    InvMovimiento::create([
                        'documento_id'       => $documento->id,
                        'almacen_id'         => $request->almacen_id,
                        'almacen_destino_id' => $request->almacen_destino_id,
                        'producto_id'        => $linea['producto_id'],
                        'tipo_movimiento'    => $tipoMovimiento,
                        'cantidad'           => $linea['cantidad'],
                        'precio_costo'       => $linea['precio_costo'] ?? null,
                        'user_id'            => $request->user()->id,
                    ]);

                    $this->aplicarMovimientoStock(
                        $tipo,
                        $tipoMovimiento,
                        (int) $request->almacen_id,
                        $request->almacen_destino_id ? (int) $request->almacen_destino_id : null,
                        (int) $linea['producto_id'],
                        (int) $linea['cantidad']
                    );
                }

                return $documento;
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $documento->load(['almacen', 'almacenDestino', 'proveedor', 'usuario', 'movimientos.producto']);

        return response()->json([
            'message' => 'Movimiento registrado exitosamente.',
            'data'    => new InvDocumentoMovimientoResource($documento),
        ], 201);
    }

    /**
     * Anula un documento de movimiento confirmado y revierte el stock.
     *
     * Solo se pueden anular documentos en estado 'confirmado'.
     *
     * @param AnularInvMovimientoRequest $request
     * @param InvDocumentoMovimiento $movimiento
     * @return JsonResponse
     */
    public function anular(AnularInvMovimientoRequest $request, InvDocumentoMovimiento $movimiento): JsonResponse
    {
        if ($movimiento->status !== InvDocumentoMovimiento::STATUS_CONFIRMADO) {
            return response()->json([
                'message' => 'Solo se pueden anular documentos en estado confirmado.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($request, $movimiento) {
                foreach ($movimiento->movimientos as $linea) {
                    $this->revertirMovimientoStock($linea);
                }

                $movimiento->update([
                    'status'           => InvDocumentoMovimiento::STATUS_ANULADO,
                    'motivo_anulacion' => $request->motivo_anulacion,
                ]);
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Documento anulado exitosamente.',
            'data'    => new InvDocumentoMovimientoResource($movimiento->fresh()),
        ]);
    }

    /**
     * Obtiene las opciones disponibles para los filtros de movimientos.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'tipos'     => [
                    ['value' => InvDocumentoMovimiento::TIPO_ENTRADA,    'label' => 'Entrada'],
                    ['value' => InvDocumentoMovimiento::TIPO_SALIDA,     'label' => 'Salida'],
                    ['value' => InvDocumentoMovimiento::TIPO_TRASLADO,   'label' => 'Traslado'],
                    ['value' => InvDocumentoMovimiento::TIPO_AJUSTE,     'label' => 'Ajuste'],
                    ['value' => InvDocumentoMovimiento::TIPO_DEVOLUCION, 'label' => 'Devolución'],
                ],
                'estados'   => [
                    ['value' => InvDocumentoMovimiento::STATUS_CONFIRMADO, 'label' => 'Confirmado'],
                    ['value' => InvDocumentoMovimiento::STATUS_ANULADO,    'label' => 'Anulado'],
                ],
                'almacenes' => InvAlmacen::where('status', 1)->orderBy('nombre')->get(['id', 'nombre']),
            ],
        ]);
    }

    /**
     * Resuelve el tipo_movimiento de la línea según el tipo_documento.
     *
     * @param string $tipoDoc
     * @param array  $linea
     * @return string
     */
    private function resolverTipoMovimiento(string $tipoDoc, array $linea): string
    {
        return match ($tipoDoc) {
            InvDocumentoMovimiento::TIPO_ENTRADA    => 'entrada',
            InvDocumentoMovimiento::TIPO_SALIDA     => 'salida',
            InvDocumentoMovimiento::TIPO_TRASLADO   => 'traslado',
            InvDocumentoMovimiento::TIPO_DEVOLUCION => 'devolucion',
            InvDocumentoMovimiento::TIPO_AJUSTE     => $linea['tipo_ajuste'] ?? 'ajuste_positivo',
            default                                 => 'entrada',
        };
    }

    /**
     * Aplica la operación de stock correspondiente al tipo de movimiento.
     *
     * @param string   $tipoDoc
     * @param string   $tipoMovimiento
     * @param int      $almacenId
     * @param int|null $almacenDestinoId
     * @param int      $productoId
     * @param int      $cantidad
     * @return void
     */
    private function aplicarMovimientoStock(
        string $tipoDoc,
        string $tipoMovimiento,
        int $almacenId,
        ?int $almacenDestinoId,
        int $productoId,
        int $cantidad
    ): void {
        match ($tipoMovimiento) {
            'entrada'          => InvStockService::incrementar($almacenId, $productoId, $cantidad),
            'salida'           => InvStockService::decrementar($almacenId, $productoId, $cantidad),
            'traslado'         => InvStockService::trasladar($almacenId, $almacenDestinoId, $productoId, $cantidad),
            'ajuste_positivo'  => InvStockService::incrementar($almacenId, $productoId, $cantidad),
            'ajuste_negativo'  => InvStockService::decrementar($almacenId, $productoId, $cantidad),
            'devolucion'       => InvStockService::incrementar($almacenId, $productoId, $cantidad),
            default            => null,
        };
    }

    /**
     * Revierte el stock de una línea de movimiento para anular el documento.
     *
     * @param InvMovimiento $linea
     * @return void
     */
    private function revertirMovimientoStock(InvMovimiento $linea): void
    {
        match ($linea->tipo_movimiento) {
            'entrada'         => InvStockService::decrementar($linea->almacen_id, $linea->producto_id, $linea->cantidad),
            'salida'          => InvStockService::incrementar($linea->almacen_id, $linea->producto_id, $linea->cantidad),
            'traslado'        => InvStockService::trasladar(
                $linea->almacen_destino_id,
                $linea->almacen_id,
                $linea->producto_id,
                $linea->cantidad
            ),
            'ajuste_positivo' => InvStockService::decrementar($linea->almacen_id, $linea->producto_id, $linea->cantidad),
            'ajuste_negativo' => InvStockService::incrementar($linea->almacen_id, $linea->producto_id, $linea->cantidad),
            'devolucion'      => InvStockService::decrementar($linea->almacen_id, $linea->producto_id, $linea->cantidad),
            default           => null,
        };
    }
}
