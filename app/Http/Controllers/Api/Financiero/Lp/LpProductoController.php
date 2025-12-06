<?php

namespace App\Http\Controllers\Api\Financiero\Lp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\Lp\StoreLpProductoRequest;
use App\Http\Requests\Api\Financiero\Lp\UpdateLpProductoRequest;
use App\Http\Resources\Api\Financiero\Lp\LpProductoResource;
use App\Models\Financiero\Lp\LpProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador LpProductoController
 *
 * Gestiona las operaciones CRUD para los productos del catálogo de listas de precios.
 * Permite crear, listar, mostrar, actualizar y eliminar productos.
 * Incluye filtros especiales por tipo de producto, referencia y financiabilidad.
 *
 * @package App\Http\Controllers\Api\Financiero\Lp
 */
class LpProductoController extends Controller
{
    /**
     * Constructor del controlador.
     * Configura los middlewares de autenticación y permisos.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_lp_productos')->only(['index', 'show']);
        $this->middleware('permission:fin_lp_productoCrear')->only(['store']);
        $this->middleware('permission:fin_lp_productoEditar')->only(['update']);
        $this->middleware('permission:fin_lp_productoInactivar')->only(['destroy']);
    }

    /**
     * Muestra una lista de productos.
     * Permite filtrar por tipo_producto_id, referencia_tipo y es_financiable.
     *
     * @param Request $request Solicitud HTTP con parámetros de filtrado y paginación
     * @return JsonResponse Respuesta JSON con la lista paginada de productos
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Preparar filtros
            $filters = $request->only([
                'search',
                'status',
                'tipo_producto_id',
                'referencia_tipo',
                'codigo',
                'include_trashed',
                'only_trashed'
            ]);

            // Preparar relaciones
            $relations = $request->has('with')
                ? explode(',', $request->with)
                : ['tipoProducto'];

            // Construir query
            $query = LpProducto::query();

            // Aplicar búsqueda si existe
            if ($request->filled('search')) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('codigo', 'like', "%{$search}%")
                      ->orWhere('descripcion', 'like', "%{$search}%");
                });
            }

            // Aplicar filtros
            if ($request->filled('status')) {
                $query->where('status', $request->integer('status'));
            }

            if ($request->filled('tipo_producto_id')) {
                $query->where('tipo_producto_id', $request->integer('tipo_producto_id'));
            }

            if ($request->filled('referencia_tipo')) {
                $query->where('referencia_tipo', $request->string('referencia_tipo'));
            }

            if ($request->filled('codigo')) {
                $query->where('codigo', $request->string('codigo'));
            }

            // Filtro por es_financiable (a través de tipo de producto)
            if ($request->filled('es_financiable')) {
                $esFinanciable = $request->boolean('es_financiable');
                $query->whereHas('tipoProducto', function ($q) use ($esFinanciable) {
                    $q->where('es_financiable', $esFinanciable);
                });
            }

            if ($request->boolean('include_trashed', false)) {
                $query->withTrashed();
            }

            if ($request->boolean('only_trashed', false)) {
                $query->onlyTrashed();
            }

            // Aplicar relaciones
            if (!empty($relations)) {
                $query->with($relations);
            }

            // Aplicar ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginar
            $productos = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'data' => LpProductoResource::collection($productos),
                'meta' => [
                    'current_page' => $productos->currentPage(),
                    'last_page' => $productos->lastPage(),
                    'per_page' => $productos->perPage(),
                    'total' => $productos->total(),
                    'from' => $productos->firstItem(),
                    'to' => $productos->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los productos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Almacena un nuevo producto en la base de datos.
     *
     * @param StoreLpProductoRequest $request Datos validados del producto
     * @return JsonResponse Respuesta JSON con el producto creado
     */
    public function store(StoreLpProductoRequest $request): JsonResponse
    {
        try {
            $producto = LpProducto::create($request->validated());

            // Cargar relaciones por defecto
            $producto->load(['tipoProducto']);

            return response()->json([
                'message' => 'Producto creado exitosamente.',
                'data' => new LpProductoResource($producto),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra el producto especificado.
     *
     * @param Request $request Solicitud HTTP con parámetros opcionales
     * @param LpProducto $lpProducto Producto a mostrar
     * @return JsonResponse Respuesta JSON con los datos del producto
     */
    public function show(Request $request, LpProducto $lpProducto): JsonResponse
    {
        try {
            // Preparar relaciones
            $relations = $request->has('with')
                ? explode(',', $request->with)
                : ['tipoProducto'];

            // Cargar relaciones
            $lpProducto->load($relations);

            return response()->json([
                'data' => new LpProductoResource($lpProducto),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza el producto especificado en la base de datos.
     *
     * @param UpdateLpProductoRequest $request Datos validados para actualizar
     * @param LpProducto $lpProducto Producto a actualizar
     * @return JsonResponse Respuesta JSON con el producto actualizado
     */
    public function update(UpdateLpProductoRequest $request, LpProducto $lpProducto): JsonResponse
    {
        try {
            $lpProducto->update($request->validated());

            // Cargar relaciones por defecto
            $lpProducto->load(['tipoProducto']);

            return response()->json([
                'message' => 'Producto actualizado exitosamente.',
                'data' => new LpProductoResource($lpProducto->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina (soft delete) el producto especificado.
     *
     * @param LpProducto $lpProducto Producto a eliminar
     * @return JsonResponse Respuesta JSON de confirmación
     */
    public function destroy(LpProducto $lpProducto): JsonResponse
    {
        try {
            $lpProducto->delete();

            return response()->json([
                'message' => 'Producto eliminado exitosamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
