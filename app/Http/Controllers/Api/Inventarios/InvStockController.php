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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Controlador para la consulta y administración del stock de inventario.
 *
 * Expone el stock actual por almacén y producto, indicadores de bajo stock
 * e importación masiva de stock inicial desde XLSX.
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
     * Devuelve la plantilla XLSX para la carga masiva de stock inicial.
     *
     * Hoja 1 «Plantilla»: columnas codigo_producto y cantidad con filas de ejemplo.
     * Hoja 2 «Productos disponibles»: catálogo completo de productos simples activos
     * para que el usuario pueda identificar el código correcto de cada producto.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function plantilla()
    {
        $spreadsheet = new Spreadsheet();

        // ── Hoja 1: plantilla de carga ────────────────────────────────────────
        $hoja1 = $spreadsheet->getActiveSheet();
        $hoja1->setTitle('Plantilla');

        $hoja1->setCellValue('A1', 'codigo_producto');
        $hoja1->setCellValue('B1', 'cantidad');

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $hoja1->getStyle('A1:B1')->applyFromArray($headerStyle);
        $hoja1->getColumnDimension('A')->setWidth(22);
        $hoja1->getColumnDimension('B')->setWidth(14);

        // Filas de ejemplo
        $hoja1->setCellValue('A2', 'PROD-001');
        $hoja1->setCellValue('B2', 50);
        $hoja1->setCellValue('A3', 'PROD-002');
        $hoja1->setCellValue('B3', 30);

        $hoja1->getStyle('B2:B3')->getNumberFormat()
            ->setFormatCode('#,##0');

        // Proteger las columnas de encabezado de ejemplo visual (solo estilo)
        $hoja1->getStyle('A2:B3')->getFont()->getColor()->setRGB('808080');

        // ── Hoja 2: catálogo de productos simples activos ─────────────────────
        $hoja2 = $spreadsheet->createSheet();
        $hoja2->setTitle('Productos disponibles');

        $hoja2->setCellValue('A1', 'Código');
        $hoja2->setCellValue('B1', 'Nombre');
        $hoja2->setCellValue('C1', 'Categoría');
        $hoja2->setCellValue('D1', 'Unidad de medida');
        $hoja2->getStyle('A1:D1')->applyFromArray($headerStyle);
        $hoja2->getColumnDimension('A')->setWidth(18);
        $hoja2->getColumnDimension('B')->setWidth(36);
        $hoja2->getColumnDimension('C')->setWidth(22);
        $hoja2->getColumnDimension('D')->setWidth(22);

        $productos = InvProducto::where('status', 1)
            ->where('tipo', 'simple')
            ->with(['categoria:id,nombre', 'unidadMedida:id,nombre'])
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'categoria_id', 'unidad_medida_id']);

        $fila = 2;
        foreach ($productos as $producto) {
            $hoja2->setCellValue("A{$fila}", $producto->codigo);
            $hoja2->setCellValue("B{$fila}", $producto->nombre);
            $hoja2->setCellValue("C{$fila}", $producto->categoria?->nombre ?? '—');
            $hoja2->setCellValue("D{$fila}", $producto->unidadMedida?->nombre ?? '—');
            $fila++;
        }

        // Volver a la primera hoja al abrir el archivo
        $spreadsheet->setActiveSheetIndex(0);

        $headers = [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="plantilla_stock_inicial.xlsx"',
            'Cache-Control'       => 'max-age=0',
        ];

        $callback = function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Procesa la carga masiva de stock inicial para un almacén desde XLSX.
     *
     * Lee la primera hoja del archivo. Columnas requeridas: codigo_producto, cantidad.
     * Solo aplica a productos de tipo simple existentes y activos.
     * El almacén de destino se recibe como campo de formulario (almacen_id),
     * no dentro del archivo.
     *
     * @param ImportarInvStockRequest $request
     * @return JsonResponse
     */
    public function importar(ImportarInvStockRequest $request): JsonResponse
    {
        $archivo   = $request->file('archivo');
        $almacenId = (int) $request->almacen_id;

        try {
            $spreadsheet = IOFactory::load($archivo->getRealPath());
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo leer el archivo XLSX: ' . $e->getMessage(),
            ], 422);
        }

        $hoja        = $spreadsheet->getActiveSheet();
        $filas       = $hoja->toArray(null, true, true, false);
        $columnasReq = ['codigo_producto', 'cantidad'];

        if (empty($filas)) {
            return response()->json(['message' => 'El archivo XLSX está vacío.'], 422);
        }

        // La primera fila es el encabezado
        $encabezados = array_map(fn ($v) => strtolower(trim((string) $v)), $filas[0]);
        $columnasFalt = array_diff($columnasReq, $encabezados);

        if (!empty($columnasFalt)) {
            return response()->json([
                'message' => 'El archivo XLSX no tiene el formato correcto. Columnas faltantes: ' . implode(', ', $columnasFalt),
            ], 422);
        }

        $colIdx   = array_flip($encabezados);
        $resumen  = ['procesadas' => 0, 'omitidas' => 0, 'errores' => []];
        $productos = InvProducto::where('status', 1)
            ->where('tipo', 'simple')
            ->pluck('id', 'codigo');

        DB::beginTransaction();
        try {
            foreach (array_slice($filas, 1) as $idx => $fila) {
                $numeroFila = $idx + 2;

                $codigo   = trim((string) ($fila[$colIdx['codigo_producto']] ?? ''));
                $cantidad = $fila[$colIdx['cantidad']] ?? null;

                if (empty($codigo)) {
                    $resumen['errores'][] = "Fila {$numeroFila}: el código del producto es obligatorio.";
                    $resumen['omitidas']++;
                    continue;
                }

                $productoId = $productos->get($codigo);
                if (!$productoId) {
                    $resumen['errores'][] = "Fila {$numeroFila}: producto '{$codigo}' no encontrado o no es de tipo simple.";
                    $resumen['omitidas']++;
                    continue;
                }

                $cantidadInt = (int) $cantidad;
                if ($cantidadInt <= 0) {
                    $resumen['errores'][] = "Fila {$numeroFila}: la cantidad debe ser un número mayor a 0.";
                    $resumen['omitidas']++;
                    continue;
                }

                $stock = InvStock::firstOrCreate(
                    ['almacen_id' => $almacenId, 'producto_id' => $productoId],
                    ['cantidad_total' => 0, 'cantidad_reservada' => 0, 'cantidad_disponible' => 0]
                );
                $stock->cantidad_total      += $cantidadInt;
                $stock->cantidad_disponible += $cantidadInt;
                $stock->ultimo_movimiento_at = now();
                $stock->save();

                $resumen['procesadas']++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => "Carga de stock completada. Procesadas: {$resumen['procesadas']}, Omitidas: {$resumen['omitidas']}.",
            'data'    => $resumen,
        ]);
    }
}
