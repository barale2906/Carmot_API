<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\ImportarInvStockRequest;
use App\Http\Resources\Api\Inventarios\InvStockResource;
use App\Models\Inventarios\InvAlmacen;
use App\Models\Inventarios\InvProducto;
use App\Models\Inventarios\InvStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para la consulta y administración del stock de inventario.
 *
 * Expone el stock actual por almacén y producto, indicadores de bajo stock
 * e importación masiva de stock inicial desde CSV.
 *
 * @package App\Http\Controllers\Api\Inventarios
 */
class InvStockController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inv_stock')->only(['index', 'show', 'statistics', 'filters']);
        $this->middleware('permission:inv_stockImportar')->only(['importar', 'plantilla']);
    }

    /**
     * Muestra el stock paginado con filtros por almacén, producto y bajo stock.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'almacen_id', 'producto_id', 'bajo_stock']);

        $stock = InvStock::withFilters($filters)
            ->with(['almacen', 'producto'])
            ->orderBy('updated_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => InvStockResource::collection($stock),
            'meta' => [
                'current_page' => $stock->currentPage(),
                'last_page'    => $stock->lastPage(),
                'per_page'     => $stock->perPage(),
                'total'        => $stock->total(),
                'from'         => $stock->firstItem(),
                'to'           => $stock->lastItem(),
            ],
        ]);
    }

    /**
     * Muestra el detalle de stock de un producto en un almacén específico.
     *
     * @param InvStock $stock
     * @return JsonResponse
     */
    public function show(InvStock $stock): JsonResponse
    {
        $stock->load(['almacen', 'producto']);

        return response()->json([
            'data' => new InvStockResource($stock),
        ]);
    }

    /**
     * Obtiene las opciones de filtros disponibles para la pantalla de stock.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'almacenes' => InvAlmacen::where('status', 1)
                    ->orderBy('nombre')
                    ->get(['id', 'nombre']),
                'productos'  => InvProducto::where('status', 1)
                    ->whereIn('tipo', ['simple'])
                    ->orderBy('nombre')
                    ->get(['id', 'codigo', 'nombre']),
            ],
        ]);
    }

    /**
     * Obtiene estadísticas globales del stock en inventario.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_registros'         => InvStock::count(),
            'total_unidades_fisicas'  => (int) InvStock::sum('cantidad_total'),
            'total_unidades_disp'     => (int) InvStock::sum('cantidad_disponible'),
            'total_unidades_reserv'   => (int) InvStock::sum('cantidad_reservada'),
            'productos_bajo_stock'    => InvStock::bajoStock()->count(),
            'almacenes_con_stock'     => InvStock::distinct('almacen_id')->count('almacen_id'),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Devuelve la plantilla CSV para la carga masiva de stock inicial.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function plantilla()
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_stock_inicial.csv"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['codigo_producto', 'cantidad']);
            fputcsv($handle, ['CAM-001', '50']);
            fputcsv($handle, ['CAM-002', '30']);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Procesa la carga masiva de stock inicial para un almacén desde CSV.
     *
     * Columnas requeridas: codigo_producto, cantidad.
     * Solo aplica a productos de tipo simple existentes y activos.
     *
     * @param ImportarInvStockRequest $request
     * @return JsonResponse
     */
    public function importar(ImportarInvStockRequest $request): JsonResponse
    {
        $archivo    = $request->file('archivo');
        $almacenId  = (int) $request->almacen_id;
        $handle     = fopen($archivo->getRealPath(), 'r');

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $encabezados = array_map('strtolower', array_map('trim', fgetcsv($handle) ?: []));
        $columnasReq = ['codigo_producto', 'cantidad'];
        $columnasFalt = array_diff($columnasReq, $encabezados);

        if (!empty($columnasFalt)) {
            fclose($handle);
            return response()->json([
                'message' => 'El archivo CSV no tiene el formato correcto. Columnas faltantes: ' . implode(', ', $columnasFalt),
            ], 422);
        }

        $resumen  = ['procesadas' => 0, 'omitidas' => 0, 'errores' => []];
        $fila     = 1;
        $productos = InvProducto::where('status', 1)
            ->where('tipo', 'simple')
            ->pluck('id', 'codigo');

        DB::beginTransaction();
        try {
            while (($filaData = fgetcsv($handle)) !== false) {
                $fila++;
                if (count($filaData) < count($columnasReq)) {
                    $resumen['errores'][] = "Fila {$fila}: datos insuficientes.";
                    $resumen['omitidas']++;
                    continue;
                }

                $datos = array_combine($encabezados, array_map('trim', $filaData));

                $codigo = $datos['codigo_producto'] ?? '';
                if (empty($codigo)) {
                    $resumen['errores'][] = "Fila {$fila}: el código del producto es obligatorio.";
                    $resumen['omitidas']++;
                    continue;
                }

                $productoId = $productos->get($codigo);
                if (!$productoId) {
                    $resumen['errores'][] = "Fila {$fila}: producto '{$codigo}' no encontrado o no es de tipo simple.";
                    $resumen['omitidas']++;
                    continue;
                }

                $cantidad = (int) ($datos['cantidad'] ?? 0);
                if ($cantidad <= 0) {
                    $resumen['errores'][] = "Fila {$fila}: la cantidad debe ser mayor a 0.";
                    $resumen['omitidas']++;
                    continue;
                }

                $stock = InvStock::firstOrCreate(
                    ['almacen_id' => $almacenId, 'producto_id' => $productoId],
                    ['cantidad_total' => 0, 'cantidad_reservada' => 0, 'cantidad_disponible' => 0]
                );
                $stock->cantidad_total      += $cantidad;
                $stock->cantidad_disponible += $cantidad;
                $stock->ultimo_movimiento_at = now();
                $stock->save();

                $resumen['procesadas']++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            return response()->json([
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 500);
        }

        fclose($handle);

        return response()->json([
            'message' => "Carga de stock completada. Procesadas: {$resumen['procesadas']}, Omitidas: {$resumen['omitidas']}.",
            'data'    => $resumen,
        ]);
    }
}
