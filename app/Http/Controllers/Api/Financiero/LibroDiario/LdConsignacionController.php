<?php

namespace App\Http\Controllers\Api\Financiero\LibroDiario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\LibroDiario\MotivoLibroDiarioRequest;
use App\Http\Requests\Api\Financiero\LibroDiario\StoreLdConsignacionRequest;
use App\Http\Requests\Api\Financiero\LibroDiario\UpdateLdConsignacionRequest;
use App\Http\Resources\Api\Financiero\LibroDiario\LdConsignacionResource;
use App\Models\Financiero\LibroDiario\LdConsignacion;
use App\Services\Financiero\LibroDiario\LdTurnoCajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de consignaciones del efectivo de la caja en cuentas bancarias.
 *
 * Mismas reglas que los movimientos: se registran en el turno abierto de quien
 * consigna, se editan mientras el turno siga abierto y se anulan con motivo.
 */
class LdConsignacionController extends Controller
{
    /** Relaciones que acompañan a la consignación en el detalle. */
    private const RELACIONES_DETALLE = ['sede', 'usuario', 'banco', 'soportes.usuario', 'anuladoPor', 'turnoCaja'];

    /** Campos que se pueden registrar o editar. */
    private const CAMPOS = ['banco_id', 'valor', 'titular', 'numero_cuenta', 'numero_comprobante', 'observaciones'];

    /**
     * Registra los middlewares de autenticación y permisos.
     */
    public function __construct(private readonly LdTurnoCajaService $turnoService)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_libroDiario')->only(['index', 'show']);
        $this->middleware('permission:fin_ldRegistroCrear')->only(['store']);
        $this->middleware('permission:fin_ldRegistroEditar')->only(['update']);
        $this->middleware('permission:fin_ldRegistroAnular')->only(['anular']);
    }

    /**
     * Lista paginada de consignaciones de las sedes del usuario. Filtros: search,
     * sede_id, turno_caja_id, status, fecha_desde, fecha_hasta.
     */
    public function index(Request $request): JsonResponse
    {
        $consignaciones = LdConsignacion::deSedes($request->user()->idsSedesAccesibles())
            ->withFilters($request->only(['search', 'sede_id', 'turno_caja_id', 'status', 'fecha_desde', 'fecha_hasta']))
            ->with(['sede', 'usuario', 'banco'])
            ->withCount('soportes')
            ->withSorting($request->get('sort_by', 'fecha'), $request->get('sort_direction', 'desc'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => LdConsignacionResource::collection($consignaciones),
            'meta' => [
                'current_page' => $consignaciones->currentPage(),
                'last_page' => $consignaciones->lastPage(),
                'per_page' => $consignaciones->perPage(),
                'total' => $consignaciones->total(),
                'from' => $consignaciones->firstItem(),
                'to' => $consignaciones->lastItem(),
            ],
        ]);
    }

    /**
     * Registra una consignación en el turno abierto del usuario.
     */
    public function store(StoreLdConsignacionRequest $request): JsonResponse
    {
        $turno = $this->turnoService->turnoAbiertoDe($request->user()->id);

        $consignacion = LdConsignacion::create(array_merge($request->only(self::CAMPOS), [
            'turno_caja_id' => $turno->id,
            'sede_id' => $turno->sede_id,
            'usuario_id' => $request->user()->id,
            'fecha' => today(),
            'status' => LdConsignacion::STATUS_REGISTRADO,
        ]));

        return response()->json([
            'message' => 'Consignación registrada exitosamente.',
            'data' => new LdConsignacionResource($consignacion->load(self::RELACIONES_DETALLE)),
        ], 201);
    }

    /**
     * Muestra la consignación con sus soportes.
     */
    public function show(Request $request, LdConsignacion $ldConsignacion): JsonResponse
    {
        $this->autorizarSede($request, $ldConsignacion);

        return response()->json([
            'data' => new LdConsignacionResource($ldConsignacion->load(self::RELACIONES_DETALLE)),
        ]);
    }

    /**
     * Actualiza la consignación mientras su turno siga abierto.
     */
    public function update(UpdateLdConsignacionRequest $request, LdConsignacion $ldConsignacion): JsonResponse
    {
        $this->autorizarSede($request, $ldConsignacion);

        if ($ldConsignacion->estaAnulado() || ! $ldConsignacion->turnoCaja->estaAbierto()) {
            return response()->json([
                'message' => 'Solo se pueden editar consignaciones vigentes de un turno de caja abierto.',
            ], 422);
        }

        $ldConsignacion->update($request->only(self::CAMPOS));

        return response()->json([
            'message' => 'Consignación actualizada exitosamente.',
            'data' => new LdConsignacionResource($ldConsignacion->load(self::RELACIONES_DETALLE)),
        ]);
    }

    /**
     * Anula la consignación con su motivo. Si su turno ya está cerrado exige
     * `fin_ldAnularCerrado` y recalcula el cierre.
     */
    public function anular(MotivoLibroDiarioRequest $request, LdConsignacion $ldConsignacion): JsonResponse
    {
        $this->autorizarSede($request, $ldConsignacion);

        if ($ldConsignacion->estaAnulado()) {
            return response()->json(['message' => 'La consignación ya está anulada.'], 422);
        }

        abort_if(
            ! $ldConsignacion->turnoCaja->estaAbierto() && ! $request->user()->can('fin_ldAnularCerrado'),
            403,
            'El turno de caja está cerrado. Anular esta consignación requiere un permiso especial.'
        );

        $this->turnoService->anularRegistro($ldConsignacion, $request->motivo, $request->user());

        return response()->json([
            'message' => 'Consignación anulada exitosamente.',
            'data' => new LdConsignacionResource($ldConsignacion->fresh(self::RELACIONES_DETALLE)),
        ]);
    }

    /**
     * La consignación debe ser de una sede asignada al usuario.
     */
    private function autorizarSede(Request $request, LdConsignacion $consignacion): void
    {
        abort_unless($request->user()->tieneAccesoSede($consignacion->sede_id), 403, 'No tiene asignada la sede de este registro.');
    }
}
