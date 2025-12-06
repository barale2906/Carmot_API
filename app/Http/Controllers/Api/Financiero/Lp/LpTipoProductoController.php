<?php

namespace App\Http\Controllers\Api\Financiero\Lp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\Lp\StoreLpTipoProductoRequest;
use App\Http\Requests\Api\Financiero\Lp\UpdateLpTipoProductoRequest;
use App\Http\Resources\Api\Financiero\Lp\LpTipoProductoResource;
use App\Models\Financiero\Lp\LpTipoProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador LpTipoProductoController
 *
 * Gestiona las operaciones CRUD para los tipos de productos del sistema de listas de precios.
 * Permite crear, listar, mostrar, actualizar y eliminar tipos de productos.
 *
 * @package App\Http\Controllers\Api\Financiero\Lp
 */
class LpTipoProductoController extends Controller
{
    /**
     * Constructor del controlador.
     * Configura los middlewares de autenticación y permisos.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_lp_tipos_producto')->only(['index', 'show']);
        $this->middleware('permission:fin_lp_tipoProductoCrear')->only(['store']);
        $this->middleware('permission:fin_lp_tipoProductoEditar')->only(['update']);
        $this->middleware('permission:fin_lp_tipoProductoInactivar')->only(['destroy']);
    }

    /**
     * Muestra una lista de tipos de productos.
     * Permite filtrar, ordenar y cargar relaciones.
     *
     * @param Request $request Solicitud HTTP con parámetros de filtrado y paginación
     * @return JsonResponse Respuesta JSON con la lista paginada de tipos de productos
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Preparar filtros
            $filters = $request->only([
                'search',
                'status',
                'es_financiable',
                'codigo',
                'include_trashed',
                'only_trashed'
            ]);

            // Preparar relaciones
            $relations = $request->has('with')
                ? explode(',', $request->with)
                : [];

            // Construir query
            $query = LpTipoProducto::query();

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

            if ($request->filled('es_financiable')) {
                $query->where('es_financiable', $request->boolean('es_financiable'));
            }

            if ($request->filled('codigo')) {
                $query->where('codigo', $request->string('codigo'));
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
            $tiposProducto = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'data' => LpTipoProductoResource::collection($tiposProducto),
                'meta' => [
                    'current_page' => $tiposProducto->currentPage(),
                    'last_page' => $tiposProducto->lastPage(),
                    'per_page' => $tiposProducto->perPage(),
                    'total' => $tiposProducto->total(),
                    'from' => $tiposProducto->firstItem(),
                    'to' => $tiposProducto->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los tipos de productos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Almacena un nuevo tipo de producto en la base de datos.
     *
     * @param StoreLpTipoProductoRequest $request Datos validados del tipo de producto
     * @return JsonResponse Respuesta JSON con el tipo de producto creado
     */
    public function store(StoreLpTipoProductoRequest $request): JsonResponse
    {
        try {
            $tipoProducto = LpTipoProducto::create($request->validated());

            return response()->json([
                'message' => 'Tipo de producto creado exitosamente.',
                'data' => new LpTipoProductoResource($tipoProducto),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el tipo de producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra el tipo de producto especificado.
     *
     * @param Request $request Solicitud HTTP con parámetros opcionales
     * @param LpTipoProducto $lpTipoProducto Tipo de producto a mostrar
     * @return JsonResponse Respuesta JSON con los datos del tipo de producto
     */
    public function show(Request $request, LpTipoProducto $lpTipoProducto): JsonResponse
    {
        try {
            // Preparar relaciones
            $relations = $request->has('with')
                ? explode(',', $request->with)
                : [];

            // Cargar relaciones
            if (!empty($relations)) {
                $lpTipoProducto->load($relations);
            }

            return response()->json([
                'data' => new LpTipoProductoResource($lpTipoProducto),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el tipo de producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza el tipo de producto especificado en la base de datos.
     *
     * @param UpdateLpTipoProductoRequest $request Datos validados para actualizar
     * @param LpTipoProducto $lpTipoProducto Tipo de producto a actualizar
     * @return JsonResponse Respuesta JSON con el tipo de producto actualizado
     */
    public function update(UpdateLpTipoProductoRequest $request, LpTipoProducto $lpTipoProducto): JsonResponse
    {
        try {
            $lpTipoProducto->update($request->validated());

            return response()->json([
                'message' => 'Tipo de producto actualizado exitosamente.',
                'data' => new LpTipoProductoResource($lpTipoProducto->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el tipo de producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina (soft delete) el tipo de producto especificado.
     *
     * @param LpTipoProducto $lpTipoProducto Tipo de producto a eliminar
     * @return JsonResponse Respuesta JSON de confirmación
     */
    public function destroy(LpTipoProducto $lpTipoProducto): JsonResponse
    {
        try {
            $lpTipoProducto->delete();

            return response()->json([
                'message' => 'Tipo de producto eliminado exitosamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el tipo de producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
