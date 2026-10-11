<?php

namespace App\Http\Controllers\Api\Financiero\LibroDiario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\LibroDiario\AbrirLdTurnoCajaRequest;
use App\Http\Requests\Api\Financiero\LibroDiario\AprobarCierreLdTurnoCajaRequest;
use App\Http\Requests\Api\Financiero\LibroDiario\CerrarLdTurnoCajaRequest;
use App\Http\Requests\Api\Financiero\LibroDiario\MotivoLibroDiarioRequest;
use App\Http\Resources\Api\Financiero\LibroDiario\LdTurnoCajaResource;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Models\Financiero\LibroDiario\LdTurnoEvento;
use App\Services\Financiero\LibroDiario\LdTurnoCajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Controlador de turnos de caja (apertura, arqueo, cierre y reversión).
 *
 * El cajero abre su turno con la base inicial antes de generar cualquier recibo
 * o registro, y lo cierra con un conteo ciego (no ve el efectivo esperado). El
 * cierre queda Por aprobar hasta que alguien con `fin_ldCierreAprobar` lo aprueba
 * o lo rechaza. Quien supervisa cajas ve los turnos de sus sedes y el arqueo;
 * `fin_ldTurnoReversar` reabre un cierre aprobado para corregirlo.
 */
class LdTurnoCajaController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos.
     */
    public function __construct(private readonly LdTurnoCajaService $turnoService)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_ldTurnoCaja|fin_ldTurnos|fin_ldCierreAprobar')->only(['index', 'show', 'pdf']);
        $this->middleware('permission:fin_ldTurnoCaja|fin_ldTurnos')->only(['cerrar']);
        $this->middleware('permission:fin_ldCierreAprobar')->only(['aprobar', 'rechazar']);
        $this->middleware('permission:fin_ldTurnoCaja')->only(['actual', 'store']);
        $this->middleware('permission:fin_ldTurnoReversar')->only(['reversar']);
    }

    /**
     * Lista paginada de turnos de las sedes del usuario. Quien no supervisa cajas
     * solo ve sus turnos propios. Filtros: sede_id, cajero_id, status, fecha_desde, fecha_hasta.
     */
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $turnos = LdTurnoCaja::deSedes($usuario->idsSedesAccesibles())
            ->when(! LdTurnoCaja::supervisaCaja($usuario), fn ($q) => $q->where('cajero_id', $usuario->id))
            ->withFilters($request->only(['sede_id', 'cajero_id', 'status', 'fecha_desde', 'fecha_hasta']))
            ->with(['sede', 'cajero', 'cerradoPor', 'aprobadoPor'])
            ->withSorting($request->get('sort_by', 'abierto_at'), $request->get('sort_direction', 'desc'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => LdTurnoCajaResource::collection($turnos),
            'meta' => [
                'current_page' => $turnos->currentPage(),
                'last_page' => $turnos->lastPage(),
                'per_page' => $turnos->perPage(),
                'total' => $turnos->total(),
                'from' => $turnos->firstItem(),
                'to' => $turnos->lastItem(),
            ],
        ]);
    }

    /**
     * Turno abierto del usuario con su resumen en vivo. Si no tiene, indica si
     * puede abrir uno y, si no, por qué (p. ej. ya cerró caja hoy sin autorización).
     * Informa además si tiene un cierre por aprobar o si su último cierre fue rechazado.
     */
    public function actual(Request $request): JsonResponse
    {
        $turno = $this->turnoService->turnoAbiertoDe($request->user()->id);

        if ($turno) {
            $turno->resumen = $this->turnoService->calcularResumen($turno);
            $turno->load(['sede', 'cajero']);
        }

        $motivo = $turno ? null : $this->turnoService->motivoNoPuedeAbrir($request->user()->id);
        $ultimoEvento = $turno?->eventos()->first();
        $porAprobar = LdTurnoCaja::where('cajero_id', $request->user()->id)
            ->where('status', LdTurnoCaja::STATUS_POR_APROBAR)
            ->latest('cerrado_at')
            ->first();

        return response()->json([
            'data' => $turno ? new LdTurnoCajaResource($turno) : null,
            'meta' => [
                'puede_abrir' => ! $turno && ! $motivo,
                'motivo_no_puede' => $motivo,
                'cierre_por_aprobar' => $porAprobar?->only(['id', 'fecha', 'cerrado_at']),
                'cierre_rechazado' => $ultimoEvento?->accion === LdTurnoEvento::ACCION_RECHAZO
                    ? ['motivo' => $ultimoEvento->motivo, 'fecha' => $ultimoEvento->created_at?->format('Y-m-d H:i:s')]
                    : null,
                'sedes_disponibles' => $request->user()->sedesAccesibles()->map->only(['id', 'nombre'])->values(),
            ],
        ]);
    }

    /**
     * Abre el turno de caja del usuario con la base inicial.
     */
    public function store(AbrirLdTurnoCajaRequest $request): JsonResponse
    {
        try {
            $turno = $this->turnoService->abrir($request->user(), $request->integer('sede_id'), (float) $request->base_inicial);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $turno->resumen = $this->turnoService->calcularResumen($turno);

        return response()->json([
            'message' => 'Turno de caja abierto exitosamente.',
            'data' => new LdTurnoCajaResource($turno->load(['sede', 'cajero'])),
        ], 201);
    }

    /**
     * Muestra el turno con su bitácora. Si está abierto, el resumen se calcula en vivo
     * (sirve como previsualización del arqueo antes de cerrar).
     */
    public function show(Request $request, LdTurnoCaja $ldTurnoCaja): JsonResponse
    {
        $this->autorizarTurno($request, $ldTurnoCaja);

        if ($ldTurnoCaja->estaAbierto()) {
            $ldTurnoCaja->resumen = $this->turnoService->calcularResumen($ldTurnoCaja);
        }

        return response()->json([
            'data' => new LdTurnoCajaResource($ldTurnoCaja->load(['sede', 'cajero', 'cerradoPor', 'aprobadoPor', 'autorizacion', 'eventos.usuario'])),
        ]);
    }

    /**
     * Cierra el turno con el conteo ciego del efectivo. El cierre queda Por aprobar,
     * los recibos creados del turno pasan a Cerrado y el cajero no podrá abrir otro
     * turno hoy sin autorización.
     */
    public function cerrar(CerrarLdTurnoCajaRequest $request, LdTurnoCaja $ldTurnoCaja): JsonResponse
    {
        $this->autorizarTurno($request, $ldTurnoCaja);

        try {
            $turno = $this->turnoService->cerrar(
                $ldTurnoCaja,
                (float) $request->efectivo_contado,
                $request->observaciones,
                $request->user()
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Cierre de caja enviado para aprobación.',
            'data' => new LdTurnoCajaResource($turno->load(['sede', 'cajero', 'cerradoPor'])),
        ]);
    }

    /**
     * Aprueba el cierre de caja Por aprobar. No se puede aprobar un cierre propio.
     */
    public function aprobar(AprobarCierreLdTurnoCajaRequest $request, LdTurnoCaja $ldTurnoCaja): JsonResponse
    {
        abort_unless($request->user()->tieneAccesoSede($ldTurnoCaja->sede_id), 403, 'No tiene asignada la sede de este turno.');

        try {
            $turno = $this->turnoService->aprobar($ldTurnoCaja, $request->observaciones, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Cierre de caja aprobado.',
            'data' => new LdTurnoCajaResource($turno->load(['sede', 'cajero', 'cerradoPor', 'aprobadoPor'])),
        ]);
    }

    /**
     * Rechaza el cierre de caja Por aprobar: el turno vuelve a Abierto para que el
     * cajero corrija o vuelva a contar. No se puede rechazar un cierre propio.
     */
    public function rechazar(MotivoLibroDiarioRequest $request, LdTurnoCaja $ldTurnoCaja): JsonResponse
    {
        abort_unless($request->user()->tieneAccesoSede($ldTurnoCaja->sede_id), 403, 'No tiene asignada la sede de este turno.');

        try {
            $turno = $this->turnoService->rechazar($ldTurnoCaja, $request->motivo, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Cierre de caja rechazado. El turno quedó abierto para que el cajero lo corrija.',
            'data' => new LdTurnoCajaResource($turno->load(['sede', 'cajero'])),
        ]);
    }

    /**
     * Reversa el cierre aprobado de un turno: lo reabre para corregirlo y devuelve sus
     * recibos a Creado. El cierre anterior queda en la bitácora con el motivo.
     */
    public function reversar(MotivoLibroDiarioRequest $request, LdTurnoCaja $ldTurnoCaja): JsonResponse
    {
        abort_unless($request->user()->tieneAccesoSede($ldTurnoCaja->sede_id), 403, 'No tiene asignada la sede de este turno.');

        try {
            $turno = $this->turnoService->reversar($ldTurnoCaja, $request->motivo, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Cierre de caja reversado. El turno quedó abierto para corrección.',
            'data' => new LdTurnoCajaResource($turno->load(['sede', 'cajero'])),
        ]);
    }

    /**
     * Descarga el PDF del cierre de caja (o del arqueo en vivo si el turno sigue abierto).
     * El efectivo esperado y la diferencia solo aparecen para quien supervisa cajas y no es el cajero.
     */
    public function pdf(Request $request, LdTurnoCaja $ldTurnoCaja): Response
    {
        $this->autorizarTurno($request, $ldTurnoCaja);

        if ($ldTurnoCaja->estaAbierto()) {
            $ldTurnoCaja->resumen = $this->turnoService->calcularResumen($ldTurnoCaja);
        }

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.ld_cierre_caja', [
            'turno' => $ldTurnoCaja->load(['sede', 'cajero', 'cerradoPor', 'aprobadoPor']),
            'verArqueo' => $ldTurnoCaja->arqueoVisiblePara($request->user()),
        ])
            ->setPaper('letter', 'portrait')
            ->download("cierre-caja-{$ldTurnoCaja->id}.pdf");
    }

    /**
     * El turno debe ser del usuario, o de una de sus sedes si supervisa cajas.
     */
    private function autorizarTurno(Request $request, LdTurnoCaja $turno): void
    {
        $usuario = $request->user();

        $permitido = $turno->cajero_id === $usuario->id
            || (LdTurnoCaja::supervisaCaja($usuario) && $usuario->tieneAccesoSede($turno->sede_id));

        abort_unless($permitido, 403, 'No tiene acceso a este turno de caja.');
    }
}
