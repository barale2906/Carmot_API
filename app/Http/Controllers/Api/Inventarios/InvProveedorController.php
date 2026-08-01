<?php

namespace App\Http\Controllers\Api\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventarios\StoreInvProveedorRequest;
use App\Http\Requests\Api\Inventarios\UpdateInvProveedorRequest;
use App\Http\Resources\Api\Inventarios\InvProveedorResource;
use App\Models\Inventarios\InvProveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de proveedores del módulo de inventarios.
 *
 * @package App\Http\Controllers\Api\Inventarios
 */
class InvProveedorController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inv_proveedores')->only(['index', 'show', 'activos']);
        $this->middleware('permission:inv_proveedoresCrear')->only(['store']);
        $this->middleware('permission:inv_proveedoresEditar')->only(['update']);
        $this->middleware('permission:inv_proveedoresInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista paginada de proveedores con filtros y ordenamiento.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status']);

        $proveedores = InvProveedor::withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvProveedorResource::collection($proveedores),
            'meta' => [
                'current_page' => $proveedores->currentPage(),
                'last_page'    => $proveedores->lastPage(),
                'per_page'     => $proveedores->perPage(),
                'total'        => $proveedores->total(),
                'from'         => $proveedores->firstItem(),
                'to'           => $proveedores->lastItem(),
            ],
        ]);
    }

    /**
     * Lista todos los proveedores activos sin paginación (para selectores).
     *
     * @return JsonResponse
     */
    public function activos(): JsonResponse
    {
        $proveedores = InvProveedor::where('status', 1)->orderBy('razon_social')->get();

        return response()->json([
            'data' => InvProveedorResource::collection($proveedores),
            'meta' => [
                'total'       => $proveedores->count(),
                'scope'       => 'activos',
                'descripcion' => 'Proveedores activos y sin eliminación lógica.',
            ],
        ]);
    }

    /**
     * Almacena un nuevo proveedor en la base de datos.
     *
     * @param StoreInvProveedorRequest $request
     * @return JsonResponse
     */
    public function store(StoreInvProveedorRequest $request): JsonResponse
    {
        $proveedor = InvProveedor::create($request->validated());

        return response()->json([
            'message' => 'Proveedor creado exitosamente.',
            'data'    => new InvProveedorResource($proveedor),
        ], 201);
    }

    /**
     * Muestra el proveedor especificado.
     *
     * @param InvProveedor $proveedor
     * @return JsonResponse
     */
    public function show(InvProveedor $proveedor): JsonResponse
    {
        return response()->json([
            'data' => new InvProveedorResource($proveedor),
        ]);
    }

    /**
     * Actualiza el proveedor especificado en la base de datos.
     *
     * @param UpdateInvProveedorRequest $request
     * @param InvProveedor $proveedor
     * @return JsonResponse
     */
    public function update(UpdateInvProveedorRequest $request, InvProveedor $proveedor): JsonResponse
    {
        $proveedor->update($request->validated());

        return response()->json([
            'message' => 'Proveedor actualizado exitosamente.',
            'data'    => new InvProveedorResource($proveedor),
        ]);
    }

    /**
     * Elimina el proveedor (soft delete).
     *
     * @param InvProveedor $proveedor
     * @return JsonResponse
     */
    public function destroy(InvProveedor $proveedor): JsonResponse
    {
        $proveedor->delete();

        return response()->json([
            'message' => 'Proveedor eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un proveedor eliminado (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $proveedor = InvProveedor::onlyTrashed()->findOrFail($id);
        $proveedor->restore();

        return response()->json([
            'message' => 'Proveedor restaurado exitosamente.',
            'data'    => new InvProveedorResource($proveedor),
        ]);
    }

    /**
     * Elimina permanentemente un proveedor.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $proveedor = InvProveedor::onlyTrashed()->findOrFail($id);
        $proveedor->forceDelete();

        return response()->json([
            'message' => 'Proveedor eliminado permanentemente.',
        ]);
    }

    /**
     * Obtiene solo los proveedores eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status']);

        $proveedores = InvProveedor::onlyTrashed()
            ->withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvProveedorResource::collection($proveedores),
            'meta' => [
                'current_page' => $proveedores->currentPage(),
                'last_page'    => $proveedores->lastPage(),
                'per_page'     => $proveedores->perPage(),
                'total'        => $proveedores->total(),
                'from'         => $proveedores->firstItem(),
                'to'           => $proveedores->lastItem(),
            ],
        ]);
    }
}
