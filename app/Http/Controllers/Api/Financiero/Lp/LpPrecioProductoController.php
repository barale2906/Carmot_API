<?php

namespace App\Http\Controllers\Api\Financiero\Lp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\Lp\StoreLpPrecioProductoRequest;
use App\Http\Requests\Api\Financiero\Lp\UpdateLpPrecioProductoRequest;
use App\Http\Resources\Api\Financiero\Lp\LpPrecioProductoResource;
use App\Http\Resources\Api\Financiero\Lp\LpProductoResource;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Models\Financiero\Lp\LpProducto;
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
        $this->middleware('permission:fin_lp_precios_producto')->only(['sinPrecioEnLista']);

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
     * Obtiene todos los precios vigentes para una referencia académica o un producto específico.
     *
     * Acepta dos modos de búsqueda (mutuamente excluyentes, se da prioridad a la referencia):
     *
     * MODO 1 — Por referencia académica (recomendado para el frontend):
     *   Parámetros: referencia_id + referencia_tipo + poblacion_id
     *   Retorna todos los precios de todos los productos LP vinculados a ese curso/módulo
     *   en la lista de precios vigente para la sede indicada.
     *
     * MODO 2 — Por producto LP (compatibilidad):
     *   Parámetros: producto_id + poblacion_id
     *   Retorna los precios del producto específico en la lista vigente.
     *
     * La respuesta es siempre un array, ya que puede haber múltiples opciones de precio
     * (ej. contado y financiado). El frontend presenta estas opciones al usuario.
     *
     * @param  Request      $request
     * @return JsonResponse Array de LpPrecioProductoResource con todas las opciones de precio.
     */
    public function obtenerPrecio(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'referencia_id'   => 'required_with:referencia_tipo|integer',
                'referencia_tipo' => 'required_with:referencia_id|string|in:curso,modulo',
                'producto_id'     => 'required_without:referencia_id|integer|exists:lp_productos,id',
                'poblacion_id'    => 'required|integer|exists:poblacions,id',
                'fecha'           => 'sometimes|date',
            ]);

            $poblacionId = $request->integer('poblacion_id');
            $fecha       = $request->filled('fecha')
                ? Carbon::parse($request->string('fecha'))
                : null;

            // Modo 1: búsqueda por referencia académica (curso o módulo)
            if ($request->filled('referencia_id') && $request->filled('referencia_tipo')) {
                $precios = $this->precioProductoService->obtenerPreciosPorReferencia(
                    $request->integer('referencia_id'),
                    $request->string('referencia_tipo')->toString(),
                    $poblacionId,
                    $fecha
                );
            } else {
                // Modo 2: búsqueda por producto LP (compatibilidad)
                $precios = $this->precioProductoService->obtenerPrecios(
                    $request->integer('producto_id'),
                    $poblacionId,
                    $fecha
                );
                $precios->each(fn ($p) => $p->load(['producto.tipoProducto', 'listaPrecio.poblaciones']));
            }

            if ($precios->isEmpty()) {
                return response()->json([
                    'message' => 'No se encontró una lista de precios vigente para los parámetros especificados.',
                    'total'   => 0,
                    'data'    => [],
                ], 404);
            }

            return response()->json([
                'message' => 'Precios obtenidos exitosamente.',
                'total'   => $precios->count(),
                'data'    => LpPrecioProductoResource::collection($precios),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los precios.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista los productos activos que aún NO tienen precio definido en una lista específica.
     *
     * Útil para el flujo de construcción de una lista nueva o para detectar
     * productos que se agregaron al catálogo después de crear la lista.
     * El resultado incluye las referencias académicas del producto para que el
     * frontend pueda mostrar a qué curso/módulo corresponde cada producto.
     *
     * Query params:
     * - lista_precio_id (int, requerido) ID de la lista de precios a consultar.
     * - search          (string, opcional) Filtra por nombre o código del producto.
     * - per_page        (int, opcional)   Registros por página. Default: 15.
     *
     * @param  Request  $request  Solicitud HTTP con los filtros.
     * @return JsonResponse       Lista paginada de LpProductoResource sin precio en la lista.
     */
    public function sinPrecioEnLista(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'lista_precio_id' => 'required|integer|exists:lp_listas_precios,id',
                'search'          => 'sometimes|string|max:150',
                'per_page'        => 'sometimes|integer|min:1|max:100',
            ]);

            $listaId = $request->integer('lista_precio_id');

            // Verificar que la lista existe
            $lista = LpListaPrecio::findOrFail($listaId);

            // Productos activos que no tienen precio en la lista indicada
            $query = LpProducto::where('status', 1)
                ->whereDoesntHave('precios', function ($q) use ($listaId) {
                    $q->where('lista_precio_id', $listaId);
                })
                ->with(['tipoProducto', 'referencias']);

            if ($request->filled('search')) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('codigo', 'like', "%{$search}%");
                });
            }

            $query->orderBy('nombre');

            $productos = $query->paginate($request->integer('per_page', 15));

            // Eager load de entidades académicas para las referencias
            $productos->each(function ($producto) {
                $producto->referencias->each(function ($ref) {
                    $ref->getReferenciaModelAttribute();
                });
            });

            return response()->json([
                'lista_precio_id' => $listaId,
                'lista_nombre'    => $lista->nombre,
                'data' => LpProductoResource::collection($productos),
                'meta' => [
                    'current_page' => $productos->currentPage(),
                    'last_page'    => $productos->lastPage(),
                    'per_page'     => $productos->perPage(),
                    'total'        => $productos->total(),
                    'from'         => $productos->firstItem(),
                    'to'           => $productos->lastItem(),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener productos sin precio.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
