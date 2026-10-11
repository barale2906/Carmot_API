<?php

namespace App\Http\Controllers\Api\Financiero\LibroDiario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\LibroDiario\MotivoLibroDiarioRequest;
use App\Http\Requests\Api\Financiero\LibroDiario\StoreLdMovimientoRequest;
use App\Http\Requests\Api\Financiero\LibroDiario\UpdateLdMovimientoRequest;
use App\Http\Resources\Api\Financiero\LibroDiario\LdMovimientoResource;
use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Services\Financiero\LibroDiario\LdMovimientoService;
use App\Services\Financiero\LibroDiario\LdTurnoCajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de movimientos manuales del libro diario (egresos y otros ingresos).
 *
 * Un movimiento se registra en el turno abierto de quien lo crea, se edita
 * mientras ese turno siga abierto y nunca se borra: se anula con motivo. Anular
 * un movimiento de un turno cerrado exige además `fin_ldAnularCerrado` y ajusta el cierre.
 */
class LdMovimientoController extends Controller
{
    /** Relaciones que acompañan al movimiento en el detalle. */
    private const RELACIONES_DETALLE = ['sede', 'usuario', 'tipoMovimiento', 'banco', 'impuestos', 'soportes.usuario', 'anuladoPor', 'turnoCaja'];

    /**
     * Registra los middlewares de autenticación y permisos.
     */
    public function __construct(
        private readonly LdMovimientoService $movimientoService,
        private readonly LdTurnoCajaService $turnoService,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_libroDiario')->only(['index', 'show']);
        $this->middleware('permission:fin_ldRegistroCrear')->only(['store']);
        $this->middleware('permission:fin_ldRegistroEditar')->only(['update']);
        $this->middleware('permission:fin_ldRegistroAnular')->only(['anular']);
    }

    /**
     * Lista paginada de movimientos de las sedes del usuario. Filtros: search,
     * sede_id, turno_caja_id, status, clase, tipo_movimiento_id, fecha_desde, fecha_hasta.
     */
    public function index(Request $request): JsonResponse
    {
        $movimientos = LdMovimiento::deSedes($request->user()->idsSedesAccesibles())
            ->withFilters($request->only([
                'search', 'sede_id', 'turno_caja_id', 'status', 'clase',
                'tipo_movimiento_id', 'fecha_desde', 'fecha_hasta',
            ]))
            ->with(['sede', 'usuario', 'tipoMovimiento'])
            ->withCount('soportes')
            ->withSorting($request->get('sort_by', 'fecha'), $request->get('sort_direction', 'desc'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => LdMovimientoResource::collection($movimientos),
            'meta' => [
                'current_page' => $movimientos->currentPage(),
                'last_page' => $movimientos->lastPage(),
                'per_page' => $movimientos->perPage(),
                'total' => $movimientos->total(),
                'from' => $movimientos->firstItem(),
                'to' => $movimientos->lastItem(),
            ],
        ]);
    }

    /**
     * Registra un movimiento en el turno abierto del usuario. Los soportes se cargan
     * después con el endpoint de soportes.
     */
    public function store(StoreLdMovimientoRequest $request): JsonResponse
    {
        $turno = $this->turnoService->turnoAbiertoDe($request->user()->id);
        $movimiento = $this->movimientoService->crear($turno, $request->validated());

        return response()->json([
            'message' => 'Movimiento registrado exitosamente.',
            'data' => new LdMovimientoResource($movimiento->load(self::RELACIONES_DETALLE)),
        ], 201);
    }

    /**
     * Muestra el movimiento con impuestos, soportes y datos de anulación.
     */
    public function show(Request $request, LdMovimiento $ldMovimiento): JsonResponse
    {
        $this->autorizarSede($request, $ldMovimiento);

        return response()->json([
            'data' => new LdMovimientoResource($ldMovimiento->load(self::RELACIONES_DETALLE)),
        ]);
    }

    /**
     * Actualiza el movimiento y sus impuestos mientras su turno siga abierto.
     */
    public function update(UpdateLdMovimientoRequest $request, LdMovimiento $ldMovimiento): JsonResponse
    {
        $this->autorizarSede($request, $ldMovimiento);

        if ($ldMovimiento->estaAnulado() || ! $ldMovimiento->turnoCaja->estaAbierto()) {
            return response()->json([
                'message' => 'Solo se pueden editar movimientos vigentes de un turno de caja abierto.',
            ], 422);
        }

        $movimiento = $this->movimientoService->actualizar($ldMovimiento, $request->validated());

        return response()->json([
            'message' => 'Movimiento actualizado exitosamente.',
            'data' => new LdMovimientoResource($movimiento->load(self::RELACIONES_DETALLE)),
        ]);
    }

    /**
     * Anula el movimiento con su motivo. Si su turno ya está cerrado exige
     * `fin_ldAnularCerrado` y recalcula el cierre.
     */
    public function anular(MotivoLibroDiarioRequest $request, LdMovimiento $ldMovimiento): JsonResponse
    {
        $this->autorizarSede($request, $ldMovimiento);

        if ($ldMovimiento->estaAnulado()) {
            return response()->json(['message' => 'El movimiento ya está anulado.'], 422);
        }

        abort_if(
            ! $ldMovimiento->turnoCaja->estaAbierto() && ! $request->user()->can('fin_ldAnularCerrado'),
            403,
            'El turno de caja está cerrado. Anular este movimiento requiere un permiso especial.'
        );

        $this->turnoService->anularRegistro($ldMovimiento, $request->motivo, $request->user());

        return response()->json([
            'message' => 'Movimiento anulado exitosamente.',
            'data' => new LdMovimientoResource($ldMovimiento->fresh(self::RELACIONES_DETALLE)),
        ]);
    }

    /**
     * El movimiento debe ser de una sede asignada al usuario.
     */
    private function autorizarSede(Request $request, LdMovimiento $movimiento): void
    {
        abort_unless($request->user()->tieneAccesoSede($movimiento->sede_id), 403, 'No tiene asignada la sede de este registro.');
    }
}
