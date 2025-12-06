<?php

namespace App\Http\Controllers\Api\Financiero\Lp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\Lp\StoreLpListaPrecioRequest;
use App\Http\Requests\Api\Financiero\Lp\UpdateLpListaPrecioRequest;
use App\Http\Resources\Api\Financiero\Lp\LpListaPrecioResource;
use App\Models\Financiero\Lp\LpListaPrecio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador LpListaPrecioController
 *
 * Gestiona las operaciones CRUD para las listas de precios.
 * Permite crear, listar, mostrar, actualizar y eliminar listas de precios.
 * Incluye métodos especiales para aprobar, activar e inactivar listas.
 *
 * @package App\Http\Controllers\Api\Financiero\Lp
 */
class LpListaPrecioController extends Controller
{
    /**
     * Constructor del controlador.
     * Configura los middlewares de autenticación y permisos.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_lp_listas_precios')->only(['index', 'show']);
        $this->middleware('permission:fin_lp_listaPrecioCrear')->only(['store']);
        $this->middleware('permission:fin_lp_listaPrecioEditar')->only(['update']);
        $this->middleware('permission:fin_lp_listaPrecioInactivar')->only(['destroy', 'inactivar']);
        $this->middleware('permission:fin_lp_listaPrecioAprobar')->only(['aprobar', 'activar']);
    }

    /**
     * Muestra una lista de listas de precios.
     * Permite filtrar por status, fecha_inicio, fecha_fin y poblacion_id.
     *
     * @param Request $request Solicitud HTTP con parámetros de filtrado y paginación
     * @return JsonResponse Respuesta JSON con la lista paginada de listas de precios
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Preparar filtros
            $filters = $request->only([
                'search',
                'status',
                'fecha_inicio',
                'fecha_fin',
                'codigo',
                'include_trashed',
                'only_trashed'
            ]);

            // Preparar relaciones
            $relations = $request->has('with')
                ? explode(',', $request->with)
                : ['poblaciones'];

            // Construir query
            $query = LpListaPrecio::query();

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

            if ($request->filled('fecha_inicio')) {
                $query->where('fecha_inicio', '>=', $request->date('fecha_inicio'));
            }

            if ($request->filled('fecha_fin')) {
                $query->where('fecha_fin', '<=', $request->date('fecha_fin'));
            }

            if ($request->filled('codigo')) {
                $query->where('codigo', $request->string('codigo'));
            }

            // Filtro por poblacion_id
            if ($request->filled('poblacion_id')) {
                $poblacionId = $request->integer('poblacion_id');
                $query->whereHas('poblaciones', function ($q) use ($poblacionId) {
                    $q->where('poblacions.id', $poblacionId);
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
            $listasPrecios = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'data' => LpListaPrecioResource::collection($listasPrecios),
                'meta' => [
                    'current_page' => $listasPrecios->currentPage(),
                    'last_page' => $listasPrecios->lastPage(),
                    'per_page' => $listasPrecios->perPage(),
                    'total' => $listasPrecios->total(),
                    'from' => $listasPrecios->firstItem(),
                    'to' => $listasPrecios->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las listas de precios.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Almacena una nueva lista de precios en la base de datos.
     * Valida solapamiento de vigencia antes de crear.
     *
     * @param StoreLpListaPrecioRequest $request Datos validados de la lista de precios
     * @return JsonResponse Respuesta JSON con la lista de precios creada
     */
    public function store(StoreLpListaPrecioRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Crear la lista de precios
            $listaPrecio = LpListaPrecio::create($request->only([
                'nombre',
                'codigo',
                'fecha_inicio',
                'fecha_fin',
                'descripcion',
                'status'
            ]));

            // Asociar poblaciones
            if ($request->has('poblaciones') && is_array($request->poblaciones)) {
                $listaPrecio->poblaciones()->attach($request->poblaciones);
            }

            DB::commit();

            // Cargar relaciones por defecto
            $listaPrecio->load(['poblaciones']);

            return response()->json([
                'message' => 'Lista de precios creada exitosamente.',
                'data' => new LpListaPrecioResource($listaPrecio),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al crear la lista de precios.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra la lista de precios especificada.
     *
     * @param Request $request Solicitud HTTP con parámetros opcionales
     * @param LpListaPrecio $lpListaPrecio Lista de precios a mostrar
     * @return JsonResponse Respuesta JSON con los datos de la lista de precios
     */
    public function show(Request $request, LpListaPrecio $lpListaPrecio): JsonResponse
    {
        try {
            // Preparar relaciones
            $relations = $request->has('with')
                ? explode(',', $request->with)
                : ['poblaciones'];

            // Cargar relaciones
            $lpListaPrecio->load($relations);

            return response()->json([
                'data' => new LpListaPrecioResource($lpListaPrecio),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la lista de precios.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza la lista de precios especificada en la base de datos.
     * Valida solapamiento de vigencia antes de actualizar.
     *
     * @param UpdateLpListaPrecioRequest $request Datos validados para actualizar
     * @param LpListaPrecio $lpListaPrecio Lista de precios a actualizar
     * @return JsonResponse Respuesta JSON con la lista de precios actualizada
     */
    public function update(UpdateLpListaPrecioRequest $request, LpListaPrecio $lpListaPrecio): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Actualizar campos básicos
            $lpListaPrecio->update($request->only([
                'nombre',
                'codigo',
                'fecha_inicio',
                'fecha_fin',
                'descripcion',
                'status'
            ]));

            // Actualizar poblaciones si se proporcionan
            if ($request->has('poblaciones')) {
                if (is_array($request->poblaciones)) {
                    $lpListaPrecio->poblaciones()->sync($request->poblaciones);
                } else {
                    $lpListaPrecio->poblaciones()->detach();
                }
            }

            DB::commit();

            // Cargar relaciones por defecto
            $lpListaPrecio->load(['poblaciones']);

            return response()->json([
                'message' => 'Lista de precios actualizada exitosamente.',
                'data' => new LpListaPrecioResource($lpListaPrecio->fresh()),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al actualizar la lista de precios.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina (soft delete) la lista de precios especificada.
     *
     * @param LpListaPrecio $lpListaPrecio Lista de precios a eliminar
     * @return JsonResponse Respuesta JSON de confirmación
     */
    public function destroy(LpListaPrecio $lpListaPrecio): JsonResponse
    {
        try {
            $lpListaPrecio->delete();

            return response()->json([
                'message' => 'Lista de precios eliminada exitosamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la lista de precios.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aprueba una lista de precios cambiando su estado a "Aprobada".
     * Solo las listas en estado "En Proceso" pueden ser aprobadas.
     *
     * @param LpListaPrecio $lpListaPrecio Lista de precios a aprobar
     * @return JsonResponse Respuesta JSON con la lista de precios aprobada
     */
    public function aprobar(LpListaPrecio $lpListaPrecio): JsonResponse
    {
        try {
            if ($lpListaPrecio->status !== LpListaPrecio::STATUS_EN_PROCESO) {
                return response()->json([
                    'message' => 'Solo las listas en estado "En Proceso" pueden ser aprobadas.',
                ], 422);
            }

            $lpListaPrecio->update(['status' => LpListaPrecio::STATUS_APROBADA]);

            return response()->json([
                'message' => 'Lista de precios aprobada exitosamente.',
                'data' => new LpListaPrecioResource($lpListaPrecio->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al aprobar la lista de precios.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activa una lista de precios cambiando su estado a "Activa".
     * Solo las listas en estado "Aprobada" pueden ser activadas manualmente.
     *
     * @param LpListaPrecio $lpListaPrecio Lista de precios a activar
     * @return JsonResponse Respuesta JSON con la lista de precios activada
     */
    public function activar(LpListaPrecio $lpListaPrecio): JsonResponse
    {
        try {
            if ($lpListaPrecio->status !== LpListaPrecio::STATUS_APROBADA) {
                return response()->json([
                    'message' => 'Solo las listas en estado "Aprobada" pueden ser activadas manualmente.',
                ], 422);
            }

            $lpListaPrecio->update(['status' => LpListaPrecio::STATUS_ACTIVA]);

            return response()->json([
                'message' => 'Lista de precios activada exitosamente.',
                'data' => new LpListaPrecioResource($lpListaPrecio->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al activar la lista de precios.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Inactiva una lista de precios cambiando su estado a "Inactiva".
     *
     * @param LpListaPrecio $lpListaPrecio Lista de precios a inactivar
     * @return JsonResponse Respuesta JSON con la lista de precios inactivada
     */
    public function inactivar(LpListaPrecio $lpListaPrecio): JsonResponse
    {
        try {
            $lpListaPrecio->update(['status' => LpListaPrecio::STATUS_INACTIVA]);

            return response()->json([
                'message' => 'Lista de precios inactivada exitosamente.',
                'data' => new LpListaPrecioResource($lpListaPrecio->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al inactivar la lista de precios.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
