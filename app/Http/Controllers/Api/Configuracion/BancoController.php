<?php

namespace App\Http\Controllers\Api\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Configuracion\StoreBancoRequest;
use App\Http\Requests\Api\Configuracion\UpdateBancoRequest;
use App\Http\Resources\Api\Configuracion\BancoResource;
use App\Models\Configuracion\Banco;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión del catálogo de bancos.
 *
 * Administra el CRUD de bancos, incluyendo soft delete, restauración
 * y eliminación permanente. Los bancos se usan para identificar el origen
 * de los pagos por transferencia en los recibos.
 *
 * @package App\Http\Controllers\Api\Configuracion
 */
class BancoController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:co_bancos')->only(['index', 'show', 'filters', 'statistics']);
        // activos también lo necesita el cajero para poblar el selector en el formulario de recibo
        $this->middleware('permission:co_bancos|fin_reciboPagoCrear')->only(['activos']);
        $this->middleware('permission:co_bancosCrear')->only(['store']);
        $this->middleware('permission:co_bancosEditar')->only(['update']);
        $this->middleware('permission:co_bancosInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista paginada de bancos con filtros y ordenamiento.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'nombre', 'status']);

        $bancos = Banco::withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->withCount('mediosPago')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => BancoResource::collection($bancos),
            'meta' => [
                'current_page' => $bancos->currentPage(),
                'last_page'    => $bancos->lastPage(),
                'per_page'     => $bancos->perPage(),
                'total'        => $bancos->total(),
                'from'         => $bancos->firstItem(),
                'to'           => $bancos->lastItem(),
            ],
        ]);
    }

    /**
     * Lista todos los bancos activos sin paginación para selectores de formulario.
     *
     * @return JsonResponse
     */
    public function activos(): JsonResponse
    {
        $bancos = Banco::where('status', 1)->orderBy('nombre')->get();

        return response()->json([
            'data' => BancoResource::collection($bancos),
            'meta' => [
                'total'       => $bancos->count(),
                'scope'       => 'activos',
                'descripcion' => 'Bancos activos disponibles para pagos por transferencia.',
            ],
        ]);
    }

    /**
     * Almacena un nuevo banco en la base de datos.
     *
     * @param StoreBancoRequest $request
     * @return JsonResponse
     */
    public function store(StoreBancoRequest $request): JsonResponse
    {
        $banco = Banco::create([
            'nombre' => $request->nombre,
            'codigo' => $request->codigo,
            'status' => $request->status ?? 1,
        ]);

        return response()->json([
            'message' => 'Banco creado exitosamente.',
            'data'    => new BancoResource($banco),
        ], 201);
    }

    /**
     * Muestra el banco especificado.
     *
     * @param Banco $banco
     * @return JsonResponse
     */
    public function show(Banco $banco): JsonResponse
    {
        $banco->loadCount('mediosPago');

        return response()->json([
            'data' => new BancoResource($banco),
        ]);
    }

    /**
     * Actualiza el banco especificado.
     *
     * @param UpdateBancoRequest $request
     * @param Banco $banco
     * @return JsonResponse
     */
    public function update(UpdateBancoRequest $request, Banco $banco): JsonResponse
    {
        $banco->update($request->only(['nombre', 'codigo', 'status']));

        return response()->json([
            'message' => 'Banco actualizado exitosamente.',
            'data'    => new BancoResource($banco),
        ]);
    }

    /**
     * Elimina el banco (soft delete).
     * No permite eliminar si tiene medios de pago asociados.
     *
     * @param Banco $banco
     * @return JsonResponse
     */
    public function destroy(Banco $banco): JsonResponse
    {
        if ($banco->mediosPago()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el banco porque tiene pagos por transferencia asociados.',
            ], 422);
        }

        $banco->delete();

        return response()->json([
            'message' => 'Banco eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un banco eliminado (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $banco = Banco::onlyTrashed()->findOrFail($id);
        $banco->restore();

        return response()->json([
            'message' => 'Banco restaurado exitosamente.',
            'data'    => new BancoResource($banco),
        ]);
    }

    /**
     * Elimina permanentemente un banco (solo si está en soft delete y sin medios de pago).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $banco = Banco::onlyTrashed()->findOrFail($id);

        if ($banco->mediosPago()->withTrashed()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el banco porque tiene pagos asociados.',
            ], 422);
        }

        $banco->forceDelete();

        return response()->json([
            'message' => 'Banco eliminado permanentemente.',
        ]);
    }

    /**
     * Lista los bancos en soft delete.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'nombre', 'status']);

        $bancos = Banco::onlyTrashed()
            ->withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => BancoResource::collection($bancos),
            'meta' => [
                'current_page' => $bancos->currentPage(),
                'last_page'    => $bancos->lastPage(),
                'per_page'     => $bancos->perPage(),
                'total'        => $bancos->total(),
                'from'         => $bancos->firstItem(),
                'to'           => $bancos->lastItem(),
            ],
        ]);
    }

    /**
     * Devuelve las opciones de filtros disponibles.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => Banco::getActiveStatusOptions(),
            ],
        ]);
    }

    /**
     * Devuelve estadísticas del catálogo de bancos.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        return response()->json([
            'data' => [
                'totales' => [
                    'total'      => Banco::withTrashed()->count(),
                    'activos'    => Banco::where('status', 1)->count(),
                    'inactivos'  => Banco::where('status', 0)->count(),
                    'eliminados' => Banco::onlyTrashed()->count(),
                ],
                'con_transferencias' => [
                    'con_pagos'    => Banco::has('mediosPago')->count(),
                    'sin_pagos'    => Banco::doesntHave('mediosPago')->count(),
                ],
                'top_bancos' => Banco::withCount('mediosPago')
                    ->orderByDesc('medios_pago_count')
                    ->limit(10)
                    ->get()
                    ->map(fn ($b) => [
                        'id'                 => $b->id,
                        'nombre'             => $b->nombre,
                        'medios_pago_count'  => $b->medios_pago_count,
                    ]),
            ],
        ]);
    }
}
