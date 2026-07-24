<?php

namespace App\Http\Controllers\Api\Financiero\Cartera;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\Cartera\StoreAcuerdoPagoRequest;
use App\Http\Resources\Api\Financiero\Cartera\CarteraResource;
use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use App\Services\Financiero\AcuerdoPagoService;
use App\Services\Financiero\CarteraDescuentoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador CarteraController
 *
 * Gestiona la consulta y acciones sobre las cuentas por cobrar (carteras).
 * La cartera es inmutable: no existe endpoint update, destroy ni restore.
 * Solo se permiten acciones controladas: anular y acuerdoPago.
 */
class CarteraController extends Controller
{
    public function __construct(
        private readonly AcuerdoPagoService $acuerdoPagoService,
        private readonly CarteraDescuentoService $descuentoService,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:fin_carteras')->only(['index', 'show', 'deudasEstudiante', 'detalleMatricula', 'reportes']);
        $this->middleware('permission:fin_carteraAnular')->only(['anular']);
        $this->middleware('permission:fin_carteraAcuerdo')->only(['acuerdoPago']);
    }

    /**
     * Lista paginada de carteras agrupadas por matrícula y estudiante.
     * Cada elemento del resultado representa una matrícula con sus cuotas anidadas.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Closure reutilizado en whereHas (filtrar qué matriculas incluir)
        // y en with (cargar solo las carteras que cumplan la condición).
        $filtrarCarteras = function ($q) use ($request) {
            if ($request->filled('status')) {
                $q->byStatus($request->integer('status'));
            }
            if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
                $q->byFechaVencimientoRange($request->input('fecha_desde'), $request->input('fecha_hasta'));
            }
            if ($request->boolean('solo_pendientes')) {
                $q->pendientes();
            }
            if ($request->boolean('solo_vencidas')) {
                $q->vencidas();
            }
            if ($request->filled('concepto')) {
                $q->byConcepto($request->input('concepto'));
            }
        };

        // Query de Cartera para calcular totales globales según los mismos filtros.
        // Las columnas matricula_id / estudiante_id / sede_id existen directamente en carteras.
        // ciclo_id y curso_id se resuelven vía whereHas('matricula').
        $agregado = Cartera::query();
        if ($request->filled('matricula_id')) {
            $agregado->byMatricula($request->integer('matricula_id'));
        }
        if ($request->filled('estudiante_id')) {
            $agregado->byEstudiante($request->integer('estudiante_id'));
        }
        if ($request->filled('sede_id')) {
            $agregado->bySede($request->integer('sede_id'));
        }
        if ($request->filled('ciclo_id')) {
            $agregado->byCiclo($request->integer('ciclo_id'));
        }
        if ($request->filled('curso_id')) {
            $agregado->byCurso($request->integer('curso_id'));
        }
        $filtrarCarteras($agregado);

        $totalSaldoFiltrado = (float) $agregado->sum('saldo');
        $totalValorFiltrado = (float) $agregado->sum('valor');

        $query = Matricula::query()
            ->whereHas('carteras', $filtrarCarteras)
            ->with([
                'carteras' => function ($q) use ($filtrarCarteras) {
                    $filtrarCarteras($q);
                    $q->orderBy('numero_cuota');
                },
                'curso',
                'estudiante',
                'sede',
            ]);

        if ($request->filled('matricula_id')) {
            $query->where('id', $request->integer('matricula_id'));
        }
        if ($request->filled('estudiante_id')) {
            $query->where('estudiante_id', $request->integer('estudiante_id'));
        }
        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->integer('sede_id'));
        }
        if ($request->filled('ciclo_id')) {
            $query->where('ciclo_id', $request->integer('ciclo_id'));
        }
        if ($request->filled('curso_id')) {
            $query->where('curso_id', $request->integer('curso_id'));
        }

        $matriculas = $query
            ->orderBy('estudiante_id')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15));

        $data = $matriculas->getCollection()->map(fn (Matricula $matricula) => [
            'matricula_id' => $matricula->id,
            'matricula'    => [
                'id'              => $matricula->id,
                'curso'           => $matricula->curso?->nombre,
                'fecha_matricula' => $matricula->fecha_matricula?->toDateString(),
            ],
            'estudiante'   => [
                'id'     => $matricula->estudiante?->id,
                'nombre' => $matricula->estudiante?->nombre_completo ?? $matricula->estudiante?->name,
            ],
            'sede'         => [
                'id'     => $matricula->sede?->id,
                'nombre' => $matricula->sede?->nombre,
            ],
            'total_valor'  => (float) $matricula->carteras->sum('valor'),
            'total_saldo'  => (float) $matricula->carteras->sum('saldo'),
            'total_abono'  => (float) $matricula->carteras->sum('abono'),
            'carteras'     => CarteraResource::collection($matricula->carteras),
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page'        => $matriculas->currentPage(),
                'last_page'           => $matriculas->lastPage(),
                'per_page'            => $matriculas->perPage(),
                'total'               => $matriculas->total(),
                'from'                => $matriculas->firstItem(),
                'to'                  => $matriculas->lastItem(),
                'total_saldo_filtrado' => $totalSaldoFiltrado,
                'total_valor_filtrado' => $totalValorFiltrado,
            ],
        ]);
    }

    /**
     * Muestra el detalle de una cartera específica.
     *
     * @param Cartera $cartera
     * @return JsonResponse
     */
    public function show(Cartera $cartera): JsonResponse
    {
        $cartera->load(['matricula.curso', 'sede', 'estudiante']);

        return response()->json([
            'data' => new CarteraResource($cartera),
        ]);
    }

    /**
     * Devuelve las deudas del estudiante agrupadas por matrícula.
     * Equivale al método obligaciones() de tailpoli/RecibosPagoCrear.
     *
     * Parámetros: estudiante_id (requerido)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deudasEstudiante(Request $request): JsonResponse
    {
        $request->validate([
            'estudiante_id' => 'required|integer|exists:users,id',
        ]);

        $deudas = Cartera::query()
            ->byEstudiante($request->integer('estudiante_id'))
            ->pendientes()
            ->with(['matricula.curso'])
            ->get()
            ->groupBy('matricula_id')
            ->map(function ($carteras, $matriculaId) {
                $primera = $carteras->first();

                return [
                    'matricula_id'    => $matriculaId,
                    'curso'           => $primera->matricula?->curso?->nombre,
                    'total_saldo'     => $carteras->sum('saldo'),
                    'total_valor'     => $carteras->sum('valor'),
                    'carteras_count'  => $carteras->count(),
                ];
            })
            ->values();

        return response()->json(['data' => $deudas]);
    }

    /**
     * Devuelve el detalle de deudas de una matrícula específica.
     * Equivale al método matrielegida() de tailpoli/RecibosPagoCrear.
     * Incluye cálculo de descuento disponible por pronto pago.
     *
     * Parámetros: matricula_id (requerido), fecha_referencia (opcional, default: hoy)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function detalleMatricula(Request $request): JsonResponse
    {
        $request->validate([
            'matricula_id'     => 'required|integer|exists:matriculas,id',
            'fecha_referencia' => 'nullable|date',
        ]);

        $matricula = Matricula::findOrFail($request->integer('matricula_id'));
        $fecha     = $request->filled('fecha_referencia')
            ? Carbon::parse($request->input('fecha_referencia'))
            : Carbon::today();

        $vencidas   = $matricula->carteras()->vencidas($fecha)->orderBy('fecha_vencimiento')->get();
        $proximas   = $matricula->carteras()->proximas($fecha)->orderBy('fecha_vencimiento')->get();
        $totalSaldo = $matricula->carteras()->pendientes()->sum('saldo');

        $descuento = $this->descuentoService->calcular(
            $matricula,
            $totalSaldo,
            $fecha
        );

        return response()->json([
            'data' => [
                'vencidas'              => CarteraResource::collection($vencidas),
                'proximas'              => CarteraResource::collection($proximas),
                'siguiente_cuota'       => $proximas->first() ? new CarteraResource($proximas->first()) : null,
                'total_saldo'           => $totalSaldo,
                'descuento_disponible'  => $descuento,
            ],
        ]);
    }

    /**
     * Anula una cartera (estado → Anulada).
     * Solo aplicable a carteras Activas o Abonadas que aún no tienen pagos registrados.
     *
     * @param Cartera $cartera
     * @return JsonResponse
     */
    public function anular(Cartera $cartera): JsonResponse
    {
        $statusAnulada = Cartera::getStatusKey('Anulada');
        $statusCerrada = Cartera::getStatusKey('Cerrada');

        if ($cartera->status === $statusAnulada) {
            return response()->json(['message' => 'La cartera ya está anulada.'], 422);
        }
        if ($cartera->status === $statusCerrada) {
            return response()->json(['message' => 'No se puede anular una cartera cerrada.'], 422);
        }

        $cartera->anular();

        return response()->json([
            'message' => 'Cartera anulada exitosamente.',
            'data'    => new CarteraResource($cartera->fresh()),
        ]);
    }

    /**
     * Registra un acuerdo de pago para una matrícula con cuotas vencidas.
     * Marca las carteras pendientes como "En Acuerdo" y genera nuevas con
     * las condiciones reestructuradas.
     *
     * @param StoreAcuerdoPagoRequest $request
     * @return JsonResponse
     */
    public function acuerdoPago(StoreAcuerdoPagoRequest $request): JsonResponse
    {
        $matricula = Matricula::findOrFail($request->integer('matricula_id'));

        $resultado = $this->acuerdoPagoService->procesarAcuerdo(
            matricula: $matricula,
            montoInicial: (float) $request->monto_inicial,
            numeroCuotas: (int) $request->numero_cuotas,
            valorCuota: (float) $request->valor_cuota,
            observaciones: $request->input('observaciones', ''),
        );

        return response()->json([
            'message' => 'Acuerdo de pago registrado exitosamente.',
            'data'    => [
                'carteras_acuerdo'    => CarteraResource::collection($resultado['carteras_acuerdo']),
                'total_reestructurado' => $resultado['total_reestructurado'],
            ],
        ], 201);
    }

    /**
     * Genera reportes de cartera para facturación diaria y seguimiento de mora.
     *
     * Parámetros: sede_id, fecha_desde, fecha_hasta, status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reportes(Request $request): JsonResponse
    {
        $query = Cartera::query();

        if ($request->filled('sede_id')) {
            $query->bySede($request->integer('sede_id'));
        }
        if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $query->byFechaVencimientoRange($request->input('fecha_desde'), $request->input('fecha_hasta'));
        }
        if ($request->filled('status')) {
            $query->byStatus($request->integer('status'));
        }

        $resumen = [
            'total_valor'    => (clone $query)->sum('valor'),
            'total_saldo'    => (clone $query)->sum('saldo'),
            'total_abono'    => (clone $query)->sum('abono'),
            'total_descuento' => (clone $query)->sum('descuento'),
            'por_status'     => (clone $query)->selectRaw('status, count(*) as total, sum(saldo) as total_saldo')
                ->groupBy('status')
                ->get()
                ->map(fn ($r) => [
                    'status'      => $r->status,
                    'status_text' => Cartera::getStatusText($r->status),
                    'total'       => $r->total,
                    'total_saldo' => $r->total_saldo,
                ]),
        ];

        return response()->json(['data' => $resumen]);
    }
}
