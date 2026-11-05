<?php

namespace App\Http\Controllers\Api\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Configuracion\StoreCuentaBancariaRequest;
use App\Http\Requests\Api\Configuracion\UpdateCuentaBancariaRequest;
use App\Http\Resources\Api\Configuracion\CuentaBancariaResource;
use App\Models\Configuracion\Banco;
use App\Models\Configuracion\CuentaBancaria;
use App\Models\Configuracion\Poblacion;
use App\Models\Configuracion\Sede;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para la gestión de las cuentas bancarias del instituto.
 *
 * Administra el CRUD de las cuentas donde se recibe el dinero de los pagos por
 * transferencia, cada una asociada a una sede, incluyendo soft delete, restauración
 * y eliminación permanente.
 */
class CuentaBancariaController extends Controller
{
    private const FILTROS = ['search', 'sede_id', 'poblacion_id', 'banco_id', 'tipo_cuenta', 'status'];

    /** Relaciones con que se responde una cuenta. */
    private const RELACIONES = ['sedes', 'poblacion', 'banco'];

    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:co_cuentasBancarias')->only(['index', 'show', 'filters', 'statistics']);
        // activas la necesitan quienes registran pagos por transferencia (recibos y ventas de inventario)
        $this->middleware('permission:co_cuentasBancarias|fin_reciboPagoCrear|inv_ventasCrear|inv_ventasAbonar')->only(['activas']);
        $this->middleware('permission:co_cuentasBancariasCrear')->only(['store']);
        $this->middleware('permission:co_cuentasBancariasEditar')->only(['update']);
        $this->middleware('permission:co_cuentasBancariasInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista paginada de cuentas bancarias con filtros y ordenamiento.
     *
     * @param  Request  $request  search, sede_id, banco_id, tipo_cuenta, status, sort_by, sort_direction, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $cuentas = CuentaBancaria::with(self::RELACIONES)
            ->withFilters($request->only(self::FILTROS))
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->withCount('mediosPago')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CuentaBancariaResource::collection($cuentas),
            'meta' => [
                'current_page' => $cuentas->currentPage(),
                'last_page' => $cuentas->lastPage(),
                'per_page' => $cuentas->perPage(),
                'total' => $cuentas->total(),
                'from' => $cuentas->firstItem(),
                'to' => $cuentas->lastItem(),
            ],
        ]);
    }

    /**
     * Lista las cuentas activas de una sede, sin paginación, para el selector de cuenta
     * destino al registrar un pago por transferencia: solo se ofrecen las cuentas que
     * aplican a la sede donde se recibe el pago (asociadas a ella o a su ciudad). El
     * front envía la sede del turno de caja con que se factura.
     *
     * @param  Request  $request  sede_id (obligatorio)
     */
    public function activas(Request $request): JsonResponse
    {
        $request->validate(
            ['sede_id' => 'required|integer|exists:sedes,id'],
            ['sede_id.required' => 'La sede es obligatoria.', 'sede_id.exists' => 'La sede seleccionada no existe.']
        );

        $cuentas = CuentaBancaria::with('banco')
            ->activasDeSede($request->integer('sede_id'))
            ->get()
            ->sortBy('etiqueta')
            ->values();

        return response()->json([
            'data' => CuentaBancariaResource::collection($cuentas),
            'meta' => [
                'total' => $cuentas->count(),
                'scope' => 'activas',
                'descripcion' => 'Cuentas activas de la sede para recibir pagos por transferencia.',
            ],
        ]);
    }

    /**
     * Almacena una nueva cuenta bancaria con las sedes y/o la ciudad a las que aplica.
     */
    public function store(StoreCuentaBancariaRequest $request): JsonResponse
    {
        $cuenta = DB::transaction(function () use ($request) {
            $cuenta = CuentaBancaria::create(array_merge(
                $request->safe()->except('sedes'),
                ['status' => $request->status ?? 1]
            ));
            $cuenta->sedes()->sync($request->input('sedes', []));

            return $cuenta;
        });

        return response()->json([
            'message' => 'Cuenta bancaria creada exitosamente.',
            'data' => new CuentaBancariaResource($cuenta->load(self::RELACIONES)),
        ], 201);
    }

    /**
     * Muestra la cuenta bancaria especificada.
     */
    public function show(CuentaBancaria $cuentaBancaria): JsonResponse
    {
        $cuentaBancaria->load(self::RELACIONES)->loadCount('mediosPago');

        return response()->json([
            'data' => new CuentaBancariaResource($cuentaBancaria),
        ]);
    }

    /**
     * Actualiza la cuenta bancaria especificada; si llegan `sedes`, reemplaza las asociadas.
     */
    public function update(UpdateCuentaBancariaRequest $request, CuentaBancaria $cuentaBancaria): JsonResponse
    {
        DB::transaction(function () use ($request, $cuentaBancaria) {
            $cuentaBancaria->update($request->safe()->except('sedes'));
            if ($request->has('sedes')) {
                $cuentaBancaria->sedes()->sync($request->input('sedes', []));
            }
        });

        return response()->json([
            'message' => 'Cuenta bancaria actualizada exitosamente.',
            'data' => new CuentaBancariaResource($cuentaBancaria->load(self::RELACIONES)),
        ]);
    }

    /**
     * Elimina la cuenta bancaria (soft delete).
     * No permite eliminar si ya recibió pagos: en ese caso debe inactivarse.
     */
    public function destroy(CuentaBancaria $cuentaBancaria): JsonResponse
    {
        if ($cuentaBancaria->mediosPago()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la cuenta porque tiene pagos por transferencia asociados. Puede inactivarla.',
            ], 422);
        }

        $cuentaBancaria->delete();

        return response()->json([
            'message' => 'Cuenta bancaria eliminada exitosamente.',
        ]);
    }

    /**
     * Restaura una cuenta bancaria eliminada (soft delete).
     */
    public function restore(int $id): JsonResponse
    {
        $cuenta = CuentaBancaria::onlyTrashed()->findOrFail($id);
        $cuenta->restore();

        return response()->json([
            'message' => 'Cuenta bancaria restaurada exitosamente.',
            'data' => new CuentaBancariaResource($cuenta->load(self::RELACIONES)),
        ]);
    }

    /**
     * Elimina permanentemente una cuenta bancaria (solo si está en soft delete y sin pagos).
     */
    public function forceDelete(int $id): JsonResponse
    {
        $cuenta = CuentaBancaria::onlyTrashed()->findOrFail($id);

        if ($cuenta->mediosPago()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente la cuenta porque tiene pagos asociados.',
            ], 422);
        }

        $cuenta->forceDelete();

        return response()->json([
            'message' => 'Cuenta bancaria eliminada permanentemente.',
        ]);
    }

    /**
     * Lista las cuentas bancarias en soft delete.
     *
     * @param  Request  $request  search, sede_id, banco_id, tipo_cuenta, status, sort_by, sort_direction, per_page
     */
    public function trashed(Request $request): JsonResponse
    {
        $cuentas = CuentaBancaria::onlyTrashed()
            ->with(self::RELACIONES)
            ->withFilters($request->only(self::FILTROS))
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CuentaBancariaResource::collection($cuentas),
            'meta' => [
                'current_page' => $cuentas->currentPage(),
                'last_page' => $cuentas->lastPage(),
                'per_page' => $cuentas->perPage(),
                'total' => $cuentas->total(),
                'from' => $cuentas->firstItem(),
                'to' => $cuentas->lastItem(),
            ],
        ]);
    }

    /**
     * Devuelve las opciones de filtros y de formulario: estados, tipos de cuenta,
     * sedes, ciudades con sedes y bancos activos.
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => CuentaBancaria::getActiveStatusOptions(),
                'tipos_cuenta' => CuentaBancaria::TIPOS,
                'sedes' => Sede::orderBy('nombre')->get(['id', 'nombre', 'poblacion_id']),
                'poblaciones' => Poblacion::has('sedes')->orderBy('nombre')->get(['id', 'nombre']),
                'bancos' => Banco::where('status', 1)->orderBy('nombre')->get(['id', 'nombre']),
            ],
        ]);
    }

    /**
     * Devuelve estadísticas de las cuentas bancarias.
     */
    public function statistics(): JsonResponse
    {
        return response()->json([
            'data' => [
                'totales' => [
                    'total' => CuentaBancaria::withTrashed()->count(),
                    'activas' => CuentaBancaria::where('status', 1)->count(),
                    'inactivas' => CuentaBancaria::where('status', 0)->count(),
                    'eliminadas' => CuentaBancaria::onlyTrashed()->count(),
                ],
                'con_transferencias' => [
                    'con_pagos' => CuentaBancaria::has('mediosPago')->count(),
                    'sin_pagos' => CuentaBancaria::doesntHave('mediosPago')->count(),
                ],
                // Cuentas activas que aplica a cada sede (asociadas a ella o a su ciudad)
                'por_sede' => Sede::orderBy('nombre')
                    ->get(['id', 'nombre'])
                    ->map(fn ($s) => [
                        'id' => $s->id,
                        'nombre' => $s->nombre,
                        'cuentas_activas' => CuentaBancaria::activasDeSede($s->id)->count(),
                    ]),
            ],
        ]);
    }
}
