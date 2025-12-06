<?php

namespace App\Http\Controllers\Api\Financiero\Lp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\Lp\StoreLpPrecioProductoRequest;
use App\Http\Requests\Api\Financiero\Lp\UpdateLpPrecioProductoRequest;
use App\Http\Resources\Api\Financiero\Lp\LpPrecioProductoResource;
use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Services\Financiero\LpPrecioProductoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador LpPrecioProductoController
 *
 * Gestiona las operaciones CRUD para los precios de productos en las listas de precios.
 * Permite crear, listar, mostrar, actualizar y eliminar precios de productos.
 * Incluye método especial para obtener el precio vigente de un producto.
 *
 * @package App\Http\Controllers\Api\Financiero\Lp
 */
class LpPrecioProductoController extends Controller
{
    /**
     * Servicio para lógica de negocio de precios de productos.
     *
     * @var LpPrecioProductoService
     */
    protected $precioProductoService;

    /**
     * Constructor del controlador.
     * Configura los middlewares de autenticación y permisos.
     */
    public function __construct(LpPrecioProductoService $precioProductoService)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_lp_precios_producto')->only(['index', 'show', 'obtenerPrecio']);
        $this->middleware('permission:fin_lp_precioProductoCrear')->only(['store']);
        $this->middleware('permission:fin_lp_precioProductoEditar')->only(['update']);
        $this->middleware('permission:fin_lp_precioProductoInactivar')->only(['destroy']);

        $this->precioProductoService = $precioProductoService;
    }

    /**
     * Muestra una lista de precios de productos.
     * Permite filtrar por lista_precio_id y producto_id.
     *
     * @param Request $request Solicitud HTTP con parámetros de filtrado y paginación
     * @return JsonResponse Respuesta JSON con la lista paginada de precios de productos
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Preparar filtros
            $filters = $request->only([
                'lista_precio_id',
                'producto_id',
                'include_trashed',
                'only_trashed'
            ]);

            // Preparar relaciones
            $relations = $request->has('with')
                ? explode(',', $request->with)
                : ['producto', 'listaPrecio'];

            // Construir query
            $query = LpPrecioProducto::query();

            // Aplicar filtros
            if ($request->filled('lista_precio_id')) {
                $query->where('lista_precio_id', $request->integer('lista_precio_id'));
            }

            if ($request->filled('producto_id')) {
                $query->where('producto_id', $request->integer('producto_id'));
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
            $preciosProductos = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'data' => LpPrecioProductoResource::collection($preciosProductos),
                'meta' => [
                    'current_page' => $preciosProductos->currentPage(),
                    'last_page' => $preciosProductos->lastPage(),
                    'per_page' => $preciosProductos->perPage(),
                    'total' => $preciosProductos->total(),
                    'from' => $preciosProductos->firstItem(),
                    'to' => $preciosProductos->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los precios de productos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Almacena un nuevo precio de producto en la base de datos.
     * El valor de la cuota se calcula automáticamente mediante el evento saving del modelo.
     *
     * @param StoreLpPrecioProductoRequest $request Datos validados del precio de producto
     * @return JsonResponse Respuesta JSON con el precio de producto creado
     */
    public function store(StoreLpPrecioProductoRequest $request): JsonResponse
    {
        try {
            $precioProducto = LpPrecioProducto::create($request->validated());

            // Cargar relaciones por defecto
            $precioProducto->load(['producto', 'listaPrecio']);

            return response()->json([
                'message' => 'Precio de producto creado exitosamente.',
                'data' => new LpPrecioProductoResource($precioProducto),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el precio de producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra el precio de producto especificado.
     *
     * @param Request $request Solicitud HTTP con parámetros opcionales
     * @param LpPrecioProducto $lpPrecioProducto Precio de producto a mostrar
     * @return JsonResponse Respuesta JSON con los datos del precio de producto
     */
    public function show(Request $request, LpPrecioProducto $lpPrecioProducto): JsonResponse
    {
        try {
            // Preparar relaciones
            $relations = $request->has('with')
                ? explode(',', $request->with)
                : ['producto', 'listaPrecio'];

            // Cargar relaciones
            $lpPrecioProducto->load($relations);

            return response()->json([
                'data' => new LpPrecioProductoResource($lpPrecioProducto),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el precio de producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza el precio de producto especificado en la base de datos.
     * El valor de la cuota se recalcula automáticamente mediante el evento saving del modelo.
     *
     * @param UpdateLpPrecioProductoRequest $request Datos validados para actualizar
     * @param LpPrecioProducto $lpPrecioProducto Precio de producto a actualizar
     * @return JsonResponse Respuesta JSON con el precio de producto actualizado
     */
    public function update(UpdateLpPrecioProductoRequest $request, LpPrecioProducto $lpPrecioProducto): JsonResponse
    {
        try {
            $lpPrecioProducto->update($request->validated());

            // Cargar relaciones por defecto
            $lpPrecioProducto->load(['producto', 'listaPrecio']);

            return response()->json([
                'message' => 'Precio de producto actualizado exitosamente.',
                'data' => new LpPrecioProductoResource($lpPrecioProducto->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el precio de producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina (soft delete) el precio de producto especificado.
     *
     * @param LpPrecioProducto $lpPrecioProducto Precio de producto a eliminar
     * @return JsonResponse Respuesta JSON de confirmación
     */
    public function destroy(LpPrecioProducto $lpPrecioProducto): JsonResponse
    {
        try {
            $lpPrecioProducto->delete();

            return response()->json([
                'message' => 'Precio de producto eliminado exitosamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el precio de producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene el precio vigente de un producto para una población específica.
     * Utiliza el servicio LpPrecioProductoService para buscar la lista de precios activa.
     *
     * @param Request $request Solicitud HTTP con producto_id, poblacion_id y fecha opcional
     * @return JsonResponse Respuesta JSON con el precio del producto o mensaje si no existe
     */
    public function obtenerPrecio(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'producto_id' => 'required|integer|exists:lp_productos,id',
                'poblacion_id' => 'required|integer|exists:poblacions,id',
                'fecha' => 'sometimes|date',
            ]);

            $productoId = $request->integer('producto_id');
            $poblacionId = $request->integer('poblacion_id');
            $fecha = $request->filled('fecha')
                ? Carbon::parse($request->string('fecha'))
                : null;

            $precio = $this->precioProductoService->obtenerPrecio($productoId, $poblacionId, $fecha);

            if (!$precio) {
                return response()->json([
                    'message' => 'No se encontró una lista de precios vigente para el producto y población especificados.',
                    'data' => null,
                ], 404);
            }

            // Cargar relaciones
            $precio->load(['producto.tipoProducto', 'listaPrecio.poblaciones']);

            return response()->json([
                'message' => 'Precio obtenido exitosamente.',
                'data' => new LpPrecioProductoResource($precio),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el precio del producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
