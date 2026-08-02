<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\ImportarInvCatalogoRequest;
use App\Http\Requests\Api\Inventarios\StoreInvProductoRequest;
use App\Http\Requests\Api\Inventarios\UpdateInvProductoRequest;
use App\Http\Resources\Api\Inventarios\InvProductoResource;
use App\Models\Inventarios\InvCategoria;
use App\Models\Inventarios\InvProducto;
use App\Models\Inventarios\InvUnidadMedida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para la gestión del catálogo de productos del inventario.
 *
 * Administra el ciclo de vida de los productos (simple, kit, grupo):
 * CRUD, soft delete, restauración, eliminación permanente y carga masiva CSV.
 *
 * @package App\Http\Controllers\Api\Inventarios
 */
class InvProductoController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inv_productos')->only(['index', 'show', 'filters', 'activos', 'statistics', 'buscar']);
        $this->middleware('permission:inv_productosCrear')->only(['store']);
        $this->middleware('permission:inv_productosEditar')->only(['update']);
        $this->middleware('permission:inv_productosInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
        $this->middleware('permission:inv_productosImportar')->only(['importar', 'plantilla']);
    }

    /**
     * Muestra una lista paginada de productos con filtros y ordenamiento.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'tipo', 'categoria_id']);

        $productos = InvProducto::withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->with(['categoria', 'unidadMedida'])
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvProductoResource::collection($productos),
            'meta' => [
                'current_page' => $productos->currentPage(),
                'last_page'    => $productos->lastPage(),
                'per_page'     => $productos->perPage(),
                'total'        => $productos->total(),
                'from'         => $productos->firstItem(),
                'to'           => $productos->lastItem(),
            ],
        ]);
    }

    /**
     * Lista todos los productos activos sin paginación (para selectores).
     * Permite filtrar por tipo mediante query param.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function activos(Request $request): JsonResponse
    {
        $query = InvProducto::where('status', 1)->orderBy('nombre');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->get('tipo'));
        }

        $productos = $query->get();

        return response()->json([
            'data' => InvProductoResource::collection($productos),
            'meta' => [
                'total'       => $productos->count(),
                'scope'       => 'activos',
                'descripcion' => 'Productos activos y sin eliminación lógica.',
            ],
        ]);
    }

    /**
     * Busca productos por nombre o código para autocompletado.
     *
     * Solo devuelve resultados cuando el término tiene al menos 3 caracteres.
     * Limita a 20 resultados ordenados por coincidencia exacta primero.
     * Acepta filtro opcional por tipo (simple, kit, grupo).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function buscar(Request $request): JsonResponse
    {
        $request->validate([
            'q'    => ['required', 'string', 'min:3', 'max:100'],
            'tipo' => ['nullable', 'string', 'in:simple,kit,grupo'],
        ], [
            'q.required' => 'El término de búsqueda es obligatorio.',
            'q.min'      => 'El término de búsqueda debe tener al menos :min caracteres.',
            'q.max'      => 'El término de búsqueda no puede superar :max caracteres.',
            'tipo.in'    => 'El tipo debe ser simple, kit o grupo.',
        ]);

        $q = $request->get('q');

        $productos = InvProducto::where('status', 1)
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'like', '%' . $q . '%')
                      ->orWhere('codigo', 'like', '%' . $q . '%');
            })
            ->when($request->filled('tipo'), fn ($query) => $query->where('tipo', $request->get('tipo')))
            ->with(['categoria', 'unidadMedida'])
            ->orderByRaw(
                "CASE WHEN LOWER(codigo) = LOWER(?) THEN 0
                      WHEN LOWER(nombre) = LOWER(?) THEN 1
                      ELSE 2 END",
                [$q, $q]
            )
            ->orderBy('nombre')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => InvProductoResource::collection($productos),
            'meta' => [
                'total'       => $productos->count(),
                'scope'       => 'buscar',
                'descripcion' => 'Resultados de autocompletado por nombre o código.',
            ],
        ]);
    }

    /**
     * Almacena un nuevo producto en la base de datos.
     *
     * @param StoreInvProductoRequest $request
     * @return JsonResponse
     */
    public function store(StoreInvProductoRequest $request): JsonResponse
    {
        $imagenPath = null;
        if ($request->hasFile('imagen')) {
            $imagenPath = $request->file('imagen')->move(
                public_path('storage/inventarios/productos'),
                uniqid() . '.' . $request->file('imagen')->getClientOriginalExtension()
            );
            $imagenPath = 'storage/inventarios/productos/' . basename((string) $imagenPath);
        }

        $producto = InvProducto::create([
            'codigo'            => $request->codigo,
            'nombre'            => $request->nombre,
            'descripcion'       => $request->descripcion,
            'tipo'              => $request->tipo,
            'imagen'            => $imagenPath,
            'categoria_id'      => $request->categoria_id,
            'unidad_medida_id'  => $request->unidad_medida_id,
            'producto_padre_id' => $request->producto_padre_id,
            'status'            => $request->status ?? 1,
        ]);

        $producto->load(['categoria', 'unidadMedida']);

        return response()->json([
            'message' => 'Producto creado exitosamente.',
            'data'    => new InvProductoResource($producto),
        ], 201);
    }

    /**
     * Muestra el producto especificado con sus relaciones principales.
     *
     * @param InvProducto $producto
     * @return JsonResponse
     */
    public function show(InvProducto $producto): JsonResponse
    {
        $producto->load(['categoria', 'unidadMedida', 'productoPadre', 'kitComponentes.grupoProducto']);
        $producto->loadCount('variantes');

        return response()->json([
            'data' => new InvProductoResource($producto),
        ]);
    }

    /**
     * Actualiza el producto especificado en la base de datos.
     *
     * @param UpdateInvProductoRequest $request
     * @param InvProducto $producto
     * @return JsonResponse
     */
    public function update(UpdateInvProductoRequest $request, InvProducto $producto): JsonResponse
    {
        $datos = $request->only([
            'codigo', 'nombre', 'descripcion', 'tipo',
            'categoria_id', 'unidad_medida_id', 'producto_padre_id', 'status',
        ]);

        if ($request->hasFile('imagen')) {
            $imagenPath = $request->file('imagen')->move(
                public_path('storage/inventarios/productos'),
                uniqid() . '.' . $request->file('imagen')->getClientOriginalExtension()
            );
            $datos['imagen'] = 'storage/inventarios/productos/' . basename((string) $imagenPath);
        }

        $producto->update($datos);
        $producto->load(['categoria', 'unidadMedida']);

        return response()->json([
            'message' => 'Producto actualizado exitosamente.',
            'data'    => new InvProductoResource($producto),
        ]);
    }

    /**
     * Elimina el producto (soft delete). No permite eliminar si tiene pedidos.
     *
     * @param InvProducto $producto
     * @return JsonResponse
     */
    public function destroy(InvProducto $producto): JsonResponse
    {
        if ($producto->variantes()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el grupo porque tiene variantes asociadas.',
            ], 422);
        }

        $producto->delete();

        return response()->json([
            'message' => 'Producto eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un producto eliminado (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $producto = InvProducto::onlyTrashed()->findOrFail($id);
        $producto->restore();

        return response()->json([
            'message' => 'Producto restaurado exitosamente.',
            'data'    => new InvProductoResource($producto),
        ]);
    }

    /**
     * Elimina permanentemente un producto.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $producto = InvProducto::onlyTrashed()->findOrFail($id);

        if ($producto->variantes()->withTrashed()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el grupo porque tiene variantes asociadas.',
            ], 422);
        }

        $producto->forceDelete();

        return response()->json([
            'message' => 'Producto eliminado permanentemente.',
        ]);
    }

    /**
     * Obtiene solo los productos eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'tipo', 'categoria_id']);

        $productos = InvProducto::onlyTrashed()
            ->withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->with(['categoria', 'unidadMedida'])
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvProductoResource::collection($productos),
            'meta' => [
                'current_page' => $productos->currentPage(),
                'last_page'    => $productos->lastPage(),
                'per_page'     => $productos->perPage(),
                'total'        => $productos->total(),
                'from'         => $productos->firstItem(),
                'to'           => $productos->lastItem(),
            ],
        ]);
    }

    /**
     * Obtiene las opciones de filtros disponibles para el catálogo de productos.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status'     => InvProducto::getActiveStatusOptions(),
                'tipos'      => [
                    ['value' => 'simple', 'label' => 'Simple'],
                    ['value' => 'kit',    'label' => 'Kit'],
                    ['value' => 'grupo',  'label' => 'Grupo'],
                ],
                'categorias' => InvCategoria::where('status', 1)
                    ->orderBy('nombre')
                    ->get(['id', 'nombre']),
            ],
        ]);
    }

    /**
     * Obtiene estadísticas del catálogo de productos.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total'      => InvProducto::withTrashed()->count(),
                'activos'    => InvProducto::where('status', 1)->count(),
                'inactivos'  => InvProducto::where('status', 0)->count(),
                'eliminados' => InvProducto::onlyTrashed()->count(),
            ],
            'por_tipo' => [
                'simples' => InvProducto::where('tipo', 'simple')->count(),
                'kits'    => InvProducto::where('tipo', 'kit')->count(),
                'grupos'  => InvProducto::where('tipo', 'grupo')->count(),
            ],
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Devuelve la plantilla CSV con el formato esperado para la carga masiva del catálogo.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function plantilla()
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_catalogo_inventario.csv"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['codigo', 'nombre', 'tipo', 'categoria', 'unidad_medida', 'descripcion', 'status']);
            fputcsv($handle, ['CAM-001', 'Camisa Escolar', 'simple', 'Uniformes', 'Unidad', 'Camisa blanca manga larga', '1']);
            fputcsv($handle, ['KIT-UNIF-001', 'Kit Uniforme Completo', 'kit', 'Uniformes', 'Kit', 'Camisa + Pantalón + Botas', '1']);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Procesa la carga masiva del catálogo de productos desde un archivo CSV.
     *
     * Las columnas requeridas son: codigo, nombre, tipo.
     * Las columnas opcionales son: categoria, unidad_medida, descripcion, status.
     * Las filas con código duplicado (ignorando eliminadas) se omiten.
     *
     * @param ImportarInvCatalogoRequest $request
     * @return JsonResponse
     */
    public function importar(ImportarInvCatalogoRequest $request): JsonResponse
    {
        $archivo = $request->file('archivo');
        $handle  = fopen($archivo->getRealPath(), 'r');

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $encabezados    = array_map('strtolower', array_map('trim', fgetcsv($handle) ?: []));
        $columnasReq    = ['codigo', 'nombre', 'tipo'];
        $columnasFalt   = array_diff($columnasReq, $encabezados);

        if (!empty($columnasFalt)) {
            fclose($handle);
            return response()->json([
                'message' => 'El archivo CSV no tiene el formato correcto. Columnas faltantes: ' . implode(', ', $columnasFalt),
            ], 422);
        }

        $resumen = ['insertadas' => 0, 'omitidas' => 0, 'errores' => []];
        $fila    = 1;

        // Pre-cargar catálogos para evitar N+1 en el CSV
        $categorias  = InvCategoria::pluck('id', 'nombre');
        $unidades    = InvUnidadMedida::pluck('id', 'nombre');
        $tiposValidos = ['simple', 'kit', 'grupo'];

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

                if (empty($datos['codigo'])) {
                    $resumen['errores'][] = "Fila {$fila}: el código es obligatorio.";
                    $resumen['omitidas']++;
                    continue;
                }

                if (empty($datos['nombre'])) {
                    $resumen['errores'][] = "Fila {$fila}: el nombre es obligatorio.";
                    $resumen['omitidas']++;
                    continue;
                }

                $tipo = strtolower($datos['tipo'] ?? 'simple');
                if (!in_array($tipo, $tiposValidos)) {
                    $resumen['errores'][] = "Fila {$fila}: tipo inválido '{$datos['tipo']}'. Use: simple, kit, grupo.";
                    $resumen['omitidas']++;
                    continue;
                }

                $existe = InvProducto::whereNull('deleted_at')
                    ->whereRaw('LOWER(codigo) = ?', [strtolower($datos['codigo'])])
                    ->exists();

                if ($existe) {
                    $resumen['errores'][] = "Fila {$fila}: el código '{$datos['codigo']}' ya existe.";
                    $resumen['omitidas']++;
                    continue;
                }

                $categoriaId   = isset($datos['categoria']) && $datos['categoria']
                    ? $categorias->get($datos['categoria'])
                    : null;

                $unidadId = isset($datos['unidad_medida']) && $datos['unidad_medida']
                    ? $unidades->get($datos['unidad_medida'])
                    : null;

                $status = isset($datos['status']) && in_array((int) $datos['status'], [0, 1])
                    ? (int) $datos['status']
                    : 1;

                InvProducto::create([
                    'codigo'           => $datos['codigo'],
                    'nombre'           => $datos['nombre'],
                    'tipo'             => $tipo,
                    'descripcion'      => $datos['descripcion'] ?? null,
                    'categoria_id'     => $categoriaId,
                    'unidad_medida_id' => $unidadId,
                    'status'           => $status,
                ]);

                $resumen['insertadas']++;
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
            'message' => "Carga masiva completada. Insertadas: {$resumen['insertadas']}, Omitidas: {$resumen['omitidas']}.",
            'data'    => $resumen,
        ]);
    }
}
