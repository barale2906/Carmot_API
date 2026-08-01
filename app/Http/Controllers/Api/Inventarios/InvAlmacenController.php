<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\StoreInvAlmacenRequest;
use App\Http\Requests\Api\Inventarios\UpdateInvAlmacenRequest;
use App\Http\Resources\Api\Inventarios\InvAlmacenResource;
use App\Models\Inventarios\InvAlmacen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de almacenes del inventario.
 *
 * Administra el ciclo de vida de los almacenes (bodegas por sede):
 * CRUD, soft delete, restauración, eliminación permanente
 * y asignación de cajeros.
 *
 * @package App\Http\Controllers\Api\Inventarios
 */
class InvAlmacenController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inv_almacenes')->only(['index', 'show', 'filters', 'activos']);
        $this->middleware('permission:inv_almacenesCrear')->only(['store']);
        $this->middleware('permission:inv_almacenesEditar')->only(['update', 'syncUsuarios']);
        $this->middleware('permission:inv_almacenesInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista paginada de almacenes con filtros y ordenamiento.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status']);

        $almacenes = InvAlmacen::withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->with('sede')
            ->withCount('usuarios')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvAlmacenResource::collection($almacenes),
            'meta' => [
                'current_page' => $almacenes->currentPage(),
                'last_page'    => $almacenes->lastPage(),
                'per_page'     => $almacenes->perPage(),
                'total'        => $almacenes->total(),
                'from'         => $almacenes->firstItem(),
                'to'           => $almacenes->lastItem(),
            ],
        ]);
    }

    /**
     * Lista todos los almacenes activos sin paginación (para selectores).
     * Filtra por sede_id si se proporciona.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function activos(Request $request): JsonResponse
    {
        $query = InvAlmacen::where('status', 1)->with('sede')->orderBy('nombre');

        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->get('sede_id'));
        }

        $almacenes = $query->get();

        return response()->json([
            'data' => InvAlmacenResource::collection($almacenes),
            'meta' => [
                'total'       => $almacenes->count(),
                'scope'       => 'activos',
                'descripcion' => 'Almacenes activos y sin eliminación lógica.',
            ],
        ]);
    }

    /**
     * Almacena un nuevo almacén en la base de datos.
     *
     * @param StoreInvAlmacenRequest $request
     * @return JsonResponse
     */
    public function store(StoreInvAlmacenRequest $request): JsonResponse
    {
        $almacen = InvAlmacen::create([
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion,
            'sede_id'     => $request->sede_id,
            'status'      => $request->status ?? 1,
        ]);

        $almacen->load('sede');

        return response()->json([
            'message' => 'Almacén creado exitosamente.',
            'data'    => new InvAlmacenResource($almacen),
        ], 201);
    }

    /**
     * Muestra el almacén especificado con sus cajeros asignados.
     *
     * @param InvAlmacen $almacen
     * @return JsonResponse
     */
    public function show(InvAlmacen $almacen): JsonResponse
    {
        $almacen->load(['sede', 'usuarios']);

        return response()->json([
            'data' => new InvAlmacenResource($almacen),
        ]);
    }

    /**
     * Actualiza el almacén especificado en la base de datos.
     *
     * @param UpdateInvAlmacenRequest $request
     * @param InvAlmacen $almacen
     * @return JsonResponse
     */
    public function update(UpdateInvAlmacenRequest $request, InvAlmacen $almacen): JsonResponse
    {
        $almacen->update($request->only(['nombre', 'descripcion', 'sede_id', 'status']));
        $almacen->load('sede');

        return response()->json([
            'message' => 'Almacén actualizado exitosamente.',
            'data'    => new InvAlmacenResource($almacen),
        ]);
    }

    /**
     * Elimina el almacén (soft delete).
     *
     * @param InvAlmacen $almacen
     * @return JsonResponse
     */
    public function destroy(InvAlmacen $almacen): JsonResponse
    {
        $almacen->delete();

        return response()->json([
            'message' => 'Almacén eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un almacén eliminado (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $almacen = InvAlmacen::onlyTrashed()->findOrFail($id);
        $almacen->restore();

        return response()->json([
            'message' => 'Almacén restaurado exitosamente.',
            'data'    => new InvAlmacenResource($almacen),
        ]);
    }

    /**
     * Elimina permanentemente un almacén.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $almacen = InvAlmacen::onlyTrashed()->findOrFail($id);
        $almacen->forceDelete();

        return response()->json([
            'message' => 'Almacén eliminado permanentemente.',
        ]);
    }

    /**
     * Obtiene solo los almacenes eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status']);

        $almacenes = InvAlmacen::onlyTrashed()
            ->withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->with('sede')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvAlmacenResource::collection($almacenes),
            'meta' => [
                'current_page' => $almacenes->currentPage(),
                'last_page'    => $almacenes->lastPage(),
                'per_page'     => $almacenes->perPage(),
                'total'        => $almacenes->total(),
                'from'         => $almacenes->firstItem(),
                'to'           => $almacenes->lastItem(),
            ],
        ]);
    }

    /**
     * Obtiene las opciones de filtros disponibles para almacenes.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => InvAlmacen::getActiveStatusOptions(),
            ],
        ]);
    }

    /**
     * Sincroniza los cajeros asignados a un almacén.
     * Reemplaza completamente la lista de usuarios con acceso.
     *
     * @param Request $request
     * @param InvAlmacen $almacen
     * @return JsonResponse
     */
    public function syncUsuarios(Request $request, InvAlmacen $almacen): JsonResponse
    {
        $request->validate([
            'user_ids'   => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $almacen->usuarios()->sync($request->user_ids);
        $almacen->load('usuarios');

        return response()->json([
            'message' => 'Cajeros del almacén actualizados exitosamente.',
            'data'    => new InvAlmacenResource($almacen),
        ]);
    }
}
