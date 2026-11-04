<?php

namespace App\Http\Controllers\Api\Financiero\LibroDiario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\LibroDiario\AbrirLdTurnoCajaRequest;
use App\Http\Requests\Api\Financiero\LibroDiario\AprobarCierreLdTurnoCajaRequest;
use App\Http\Requests\Api\Financiero\LibroDiario\CerrarLdTurnoCajaRequest;
use App\Http\Requests\Api\Financiero\LibroDiario\FiltrarLdTurnosCajaRequest;
use App\Http\Requests\Api\Financiero\LibroDiario\MotivoLibroDiarioRequest;
use App\Http\Resources\Api\Financiero\LibroDiario\LdTurnoCajaResource;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Models\Financiero\LibroDiario\LdTurnoEvento;
use App\Services\Financiero\LibroDiario\LdTurnoCajaService;
use App\Services\Financiero\LibroDiario\LdTurnosCajaExcelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador de turnos de caja (apertura, arqueo, cierre y reversión).
 *
 * El cajero abre su turno con la base inicial antes de generar cualquier recibo
 * o registro (uno por sede; quien factura para varias sedes abre una caja en cada
 * una y cierra cada una por separado), y lo cierra con un conteo ciego (no ve el efectivo esperado). El
 * cierre queda Por aprobar hasta que alguien con `fin_ldCierreAprobar` lo aprueba
 * o lo rechaza. Quien supervisa cajas ve los turnos de sus sedes y el arqueo;
 * `fin_ldTurnoReversar` reabre un cierre aprobado para corregirlo.
 */
class LdTurnoCajaController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos.
     */
    public function __construct(
        private readonly LdTurnoCajaService $turnoService,
        private readonly LdTurnosCajaExcelService $turnosExcel,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_ldTurnoCaja|fin_ldTurnos|fin_ldCierreAprobar')->only(['index', 'show', 'pdf', 'excel']);
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
        $turnos = LdTurnoCaja::visiblesPara($request->user())
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
     * Turnos abiertos del usuario (uno por sede; puede tener varios si factura para
     * otras sedes), cada uno con su resumen en vivo.
     *
     * `meta.sedes_disponibles` trae cada sede del usuario con su turno abierto
     * (`turno_abierto_id`) y si puede abrir uno nuevo o por qué no (p. ej. ya cerró caja
     * hoy en esa sede sin autorización); `puede_abrir` indica si hay alguna sede donde
     * abrir y `motivo_no_puede`, cuando no hay ninguna, por qué. Informa además los cierres por aprobar y los turnos abiertos cuyo último
     * cierre fue rechazado.
     */
    public function actual(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $turnos = $this->turnoService->turnosAbiertosDe($usuario->id)
            ->load(['sede', 'cajero', 'eventos'])
            ->each(fn (LdTurnoCaja $turno) => $turno->resumen = $this->turnoService->calcularResumen($turno));

        $sedes = $this->turnoService->sedesDeCaja($usuario);
        $puedeAbrir = $sedes->contains('puede_abrir', true);
        $sinTurno = $sedes->whereNull('turno_abierto_id');

        $porAprobar = LdTurnoCaja::where('cajero_id', $usuario->id)
            ->where('status', LdTurnoCaja::STATUS_POR_APROBAR)
            ->with('sede')
            ->latest('cerrado_at')
            ->get();

        $rechazados = $turnos
            ->filter(fn (LdTurnoCaja $turno) => $turno->eventos->first()?->accion === LdTurnoEvento::ACCION_RECHAZO)
            ->map(fn (LdTurnoCaja $turno) => [
                'turno_id' => $turno->id,
                'sede' => $turno->sede?->nombre,
                'motivo' => $turno->eventos->first()->motivo,
                'fecha' => $turno->eventos->first()->created_at?->format('Y-m-d H:i:s'),
            ])
            ->values();

        $turnos->each->unsetRelation('eventos');

        return response()->json([
            'data' => LdTurnoCajaResource::collection($turnos),
            'meta' => [
                'puede_abrir' => $puedeAbrir,
                'motivo_no_puede' => match (true) {
                    $puedeAbrir, $sedes->isNotEmpty() && $sinTurno->isEmpty() => null,
                    $sedes->isEmpty() => 'No tiene sedes asignadas para abrir caja.',
                    default => $sinTurno->first()['motivo_no_puede'],
                },
                'cierres_por_aprobar' => $porAprobar->map(fn (LdTurnoCaja $turno) => [
                    'id' => $turno->id,
                    'fecha' => $turno->fecha?->format('Y-m-d'),
                    'cerrado_at' => $turno->cerrado_at?->format('Y-m-d H:i:s'),
                    'sede' => $turno->sede?->nombre,
                ])->values(),
                'cierres_rechazados' => $rechazados,
                'sedes_disponibles' => $sedes,
            ],
        ]);
    }

    /**
     * Abre el turno de caja del usuario en la sede con la base inicial. Si el usuario
     * tiene una sola sede, `sede_id` es opcional. Puede tener turnos abiertos a la vez
     * en varias sedes, pero solo uno por sede.
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
     * El archivo se nombra con la fecha del turno y su id: cierre-caja-{Y-m-d}-{id}.pdf.
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
            ->download($ldTurnoCaja->nombreArchivoPdf());
    }

    /**
     * Descarga en Excel los cierres de caja con los mismos filtros y alcance del listado
     * (sedes del usuario; solo los propios si no supervisa cajas), detallados por turno y
     * por medio de pago. Respeta el conteo ciego igual que el detalle y el PDF.
     */
    public function excel(FiltrarLdTurnosCajaRequest $request): StreamedResponse
    {
        $spreadsheet = $this->turnosExcel->generar($request->user(), $request->filtros());

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'cierres-caja-'.now()->format('Y-m-d').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
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
