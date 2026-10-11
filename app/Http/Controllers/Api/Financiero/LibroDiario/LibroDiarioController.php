<?php

namespace App\Http\Controllers\Api\Financiero\LibroDiario;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Financiero\LibroDiario\LibroDiarioRegistroResource;
use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Models\Financiero\LibroDiario\LdSoporte;
use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Models\Financiero\ReciboPago\ReciboPagoMedioPago;
use App\Services\Financiero\LibroDiario\LibroDiarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador del libro diario unificado por sede.
 *
 * Muestra en una sola vista los ingresos por recibos (académicos y de inventario),
 * los movimientos manuales (egresos y otros ingresos) y las consignaciones,
 * siempre limitado a las sedes asignadas al usuario.
 */
class LibroDiarioController extends Controller
{
    /** Filtros comunes del libro. */
    private const FILTROS = ['sede_id', 'fecha_desde', 'fecha_hasta', 'turno_caja_id', 'origen', 'naturaleza', 'search'];

    /**
     * Registra los middlewares de autenticación y permisos.
     */
    public function __construct(private readonly LibroDiarioService $libroService)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_libroDiario');
    }

    /**
     * Registros del libro paginados, del más reciente al más antiguo. Filtros:
     * sede_id, fecha_desde, fecha_hasta, turno_caja_id, origen, naturaleza, search.
     */
    public function index(Request $request): JsonResponse
    {
        $registros = $this->libroService->registros(
            $request->user()->idsSedesAccesibles(),
            $request->only(self::FILTROS),
            (int) $request->get('per_page', 15)
        );

        return response()->json([
            'data' => LibroDiarioRegistroResource::collection($registros),
            'meta' => [
                'current_page' => $registros->currentPage(),
                'last_page' => $registros->lastPage(),
                'per_page' => $registros->perPage(),
                'total' => $registros->total(),
                'from' => $registros->firstItem(),
                'to' => $registros->lastItem(),
            ],
        ]);
    }

    /**
     * Totales por sede (ingresos académicos, de inventario, otros, egresos,
     * consignaciones y neto) para el rango de fechas, y su consolidado.
     */
    public function resumen(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->libroService->resumen(
                $request->user()->idsSedesAccesibles(),
                $request->only(['sede_id', 'fecha_desde', 'fecha_hasta', 'turno_caja_id'])
            ),
        ]);
    }

    /**
     * Catálogos y opciones de enumerados para los formularios y filtros del módulo.
     */
    public function filters(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'sedes' => $request->user()->sedesAccesibles()->map->only(['id', 'nombre'])->values(),
                'origenes' => LibroDiarioService::getOrigenOptions(),
                'naturalezas' => LibroDiarioService::getNaturalezaOptions(),
                'clases' => LdTipoMovimiento::getClaseOptions(),
                'medios_pago' => ReciboPagoMedioPago::MEDIOS,
                'tipos_identificacion' => LdMovimiento::TIPOS_IDENTIFICACION,
                'tipos_documento' => LdMovimiento::TIPOS_DOCUMENTO,
                'tipos_soporte' => LdSoporte::TIPOS,
                'status_registro' => LdMovimiento::getStatusOptions(),
                'status_turno' => LdTurnoCaja::getStatusOptions(),
            ],
        ]);
    }
}
