<?php

namespace App\Http\Controllers\Api\Financiero\LibroDiario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\LibroDiario\StoreLdImpuestoRequest;
use App\Http\Requests\Api\Financiero\LibroDiario\UpdateLdImpuestoRequest;
use App\Http\Resources\Api\Financiero\LibroDiario\LdImpuestoResource;
use App\Models\Financiero\LibroDiario\LdImpuesto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador del catálogo de impuestos y retenciones del libro diario.
 *
 * CRUD con soft delete, restauración y eliminación permanente (solo si el impuesto
 * no se ha aplicado a movimientos).
 */
class LdImpuestoController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_ldCatalogos')->only(['index', 'show', 'filters']);
        // activos también lo necesita quien registra movimientos para poblar el selector de impuestos
        $this->middleware('permission:fin_ldCatalogos|fin_ldRegistroCrear|fin_ldRegistroEditar')->only(['activos']);
        $this->middleware('permission:fin_ldCatalogoCrear')->only(['store']);
        $this->middleware('permission:fin_ldCatalogoEditar')->only(['update']);
        $this->middleware('permission:fin_ldCatalogoInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Lista paginada de impuestos con filtros (search, status, efecto) y ordenamiento.
     */
    public function index(Request $request): JsonResponse
    {
        $impuestos = LdImpuesto::withFilters($request->only(['search', 'status', 'efecto']))
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => LdImpuestoResource::collection($impuestos),
            'meta' => [
                'current_page' => $impuestos->currentPage(),
                'last_page' => $impuestos->lastPage(),
                'per_page' => $impuestos->perPage(),
                'total' => $impuestos->total(),
                'from' => $impuestos->firstItem(),
                'to' => $impuestos->lastItem(),
            ],
        ]);
    }

    /**
     * Impuestos activos sin paginar para selectores; admite filtrar por efecto.
     */
    public function activos(Request $request): JsonResponse
    {
        $impuestos = LdImpuesto::active()
            ->when($request->filled('efecto'), fn ($q) => $q->where('efecto', $request->integer('efecto')))
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => LdImpuestoResource::collection($impuestos),
        ]);
    }

    /**
     * Crea un impuesto o retención.
     */
    public function store(StoreLdImpuestoRequest $request): JsonResponse
    {
        $impuesto = LdImpuesto::create(array_merge(['status' => 1], $request->validated()));

        return response()->json([
            'message' => 'Impuesto creado exitosamente.',
            'data' => new LdImpuestoResource($impuesto),
        ], 201);
    }

    /**
     * Muestra un impuesto.
     */
    public function show(LdImpuesto $ldImpuesto): JsonResponse
    {
        return response()->json([
            'data' => new LdImpuestoResource($ldImpuesto),
        ]);
    }

    /**
     * Actualiza un impuesto. Los movimientos ya registrados conservan el nombre, efecto y porcentaje aplicados.
     */
    public function update(UpdateLdImpuestoRequest $request, LdImpuesto $ldImpuesto): JsonResponse
    {
        $ldImpuesto->update($request->validated());

        return response()->json([
            'message' => 'Impuesto actualizado exitosamente.',
            'data' => new LdImpuestoResource($ldImpuesto),
        ]);
    }

    /**
     * Elimina el impuesto (soft delete).
     */
    public function destroy(LdImpuesto $ldImpuesto): JsonResponse
    {
        $ldImpuesto->delete();

        return response()->json([
            'message' => 'Impuesto eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un impuesto eliminado.
     */
    public function restore(int $id): JsonResponse
    {
        $impuesto = LdImpuesto::onlyTrashed()->findOrFail($id);
        $impuesto->restore();

        return response()->json([
            'message' => 'Impuesto restaurado exitosamente.',
            'data' => new LdImpuestoResource($impuesto),
        ]);
    }

    /**
     * Elimina permanentemente un impuesto que no se ha aplicado a movimientos.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $impuesto = LdImpuesto::onlyTrashed()->findOrFail($id);

        if ($impuesto->aplicaciones()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el impuesto porque está aplicado en movimientos.',
            ], 422);
        }

        $impuesto->forceDelete();

        return response()->json([
            'message' => 'Impuesto eliminado permanentemente.',
        ]);
    }

    /**
     * Lista los impuestos eliminados.
     */
    public function trashed(Request $request): JsonResponse
    {
        $impuestos = LdImpuesto::onlyTrashed()
            ->withFilters($request->only(['search', 'status', 'efecto']))
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => LdImpuestoResource::collection($impuestos),
            'meta' => [
                'current_page' => $impuestos->currentPage(),
                'last_page' => $impuestos->lastPage(),
                'per_page' => $impuestos->perPage(),
                'total' => $impuestos->total(),
                'from' => $impuestos->firstItem(),
                'to' => $impuestos->lastItem(),
            ],
        ]);
    }

    /**
     * Opciones de filtros: estados y efectos.
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => LdImpuesto::getActiveStatusOptions(),
                'efecto' => LdImpuesto::getEfectoOptions(),
            ],
        ]);
    }
}
