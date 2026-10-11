<?php

namespace App\Http\Controllers\Api\Financiero\LibroDiario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\LibroDiario\StoreLdTipoMovimientoRequest;
use App\Http\Requests\Api\Financiero\LibroDiario\UpdateLdTipoMovimientoRequest;
use App\Http\Resources\Api\Financiero\LibroDiario\LdTipoMovimientoResource;
use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador del catálogo de tipos de movimiento del libro diario.
 *
 * CRUD con soft delete, restauración y eliminación permanente (solo si el tipo
 * no tiene movimientos registrados).
 */
class LdTipoMovimientoController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_ldCatalogos')->only(['index', 'show', 'filters']);
        // activos también lo necesita quien registra movimientos para poblar el selector
        $this->middleware('permission:fin_ldCatalogos|fin_ldRegistroCrear|fin_ldRegistroEditar')->only(['activos']);
        $this->middleware('permission:fin_ldCatalogoCrear')->only(['store']);
        $this->middleware('permission:fin_ldCatalogoEditar')->only(['update']);
        $this->middleware('permission:fin_ldCatalogoInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Lista paginada de tipos de movimiento con filtros (search, status, clase) y ordenamiento.
     */
    public function index(Request $request): JsonResponse
    {
        $tipos = LdTipoMovimiento::withFilters($request->only(['search', 'status', 'clase']))
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => LdTipoMovimientoResource::collection($tipos),
            'meta' => [
                'current_page' => $tipos->currentPage(),
                'last_page' => $tipos->lastPage(),
                'per_page' => $tipos->perPage(),
                'total' => $tipos->total(),
                'from' => $tipos->firstItem(),
                'to' => $tipos->lastItem(),
            ],
        ]);
    }

    /**
     * Tipos activos sin paginar para selectores; admite filtrar por clase.
     */
    public function activos(Request $request): JsonResponse
    {
        $tipos = LdTipoMovimiento::active()
            ->when($request->filled('clase'), fn ($q) => $q->where('clase', $request->integer('clase')))
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => LdTipoMovimientoResource::collection($tipos),
        ]);
    }

    /**
     * Crea un tipo de movimiento.
     */
    public function store(StoreLdTipoMovimientoRequest $request): JsonResponse
    {
        $tipo = LdTipoMovimiento::create(array_merge(['status' => 1], $request->validated()));

        return response()->json([
            'message' => 'Tipo de movimiento creado exitosamente.',
            'data' => new LdTipoMovimientoResource($tipo),
        ], 201);
    }

    /**
     * Muestra un tipo de movimiento.
     */
    public function show(LdTipoMovimiento $ldTipoMovimiento): JsonResponse
    {
        return response()->json([
            'data' => new LdTipoMovimientoResource($ldTipoMovimiento),
        ]);
    }

    /**
     * Actualiza un tipo de movimiento. Los movimientos ya registrados conservan su clase.
     */
    public function update(UpdateLdTipoMovimientoRequest $request, LdTipoMovimiento $ldTipoMovimiento): JsonResponse
    {
        $ldTipoMovimiento->update($request->validated());

        return response()->json([
            'message' => 'Tipo de movimiento actualizado exitosamente.',
            'data' => new LdTipoMovimientoResource($ldTipoMovimiento),
        ]);
    }

    /**
     * Elimina el tipo de movimiento (soft delete).
     */
    public function destroy(LdTipoMovimiento $ldTipoMovimiento): JsonResponse
    {
        $ldTipoMovimiento->delete();

        return response()->json([
            'message' => 'Tipo de movimiento eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un tipo de movimiento eliminado.
     */
    public function restore(int $id): JsonResponse
    {
        $tipo = LdTipoMovimiento::onlyTrashed()->findOrFail($id);
        $tipo->restore();

        return response()->json([
            'message' => 'Tipo de movimiento restaurado exitosamente.',
            'data' => new LdTipoMovimientoResource($tipo),
        ]);
    }

    /**
     * Elimina permanentemente un tipo de movimiento sin movimientos registrados.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $tipo = LdTipoMovimiento::onlyTrashed()->findOrFail($id);

        if ($tipo->movimientos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el tipo porque tiene movimientos registrados.',
            ], 422);
        }

        $tipo->forceDelete();

        return response()->json([
            'message' => 'Tipo de movimiento eliminado permanentemente.',
        ]);
    }

    /**
     * Lista los tipos de movimiento eliminados.
     */
    public function trashed(Request $request): JsonResponse
    {
        $tipos = LdTipoMovimiento::onlyTrashed()
            ->withFilters($request->only(['search', 'status', 'clase']))
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => LdTipoMovimientoResource::collection($tipos),
            'meta' => [
                'current_page' => $tipos->currentPage(),
                'last_page' => $tipos->lastPage(),
                'per_page' => $tipos->perPage(),
                'total' => $tipos->total(),
                'from' => $tipos->firstItem(),
                'to' => $tipos->lastItem(),
            ],
        ]);
    }

    /**
     * Opciones de filtros: estados y clases.
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => LdTipoMovimiento::getActiveStatusOptions(),
                'clase' => LdTipoMovimiento::getClaseOptions(),
            ],
        ]);
    }
}
