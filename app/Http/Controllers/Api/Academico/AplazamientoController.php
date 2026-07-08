<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\AmpliarAplazamientoRequest;
use App\Http\Requests\Api\Academico\ConfirmarAplazamientoRequest;
use App\Http\Requests\Api\Academico\InterrumpirAplazamientoRequest;
use App\Http\Requests\Api\Academico\StoreAplazamientoRequest;
use App\Http\Resources\Api\Academico\AplazamientoResource;
use App\Models\Academico\Aplazamiento;
use App\Models\Academico\Ciclo;
use App\Services\Academico\AplazamientoCicloService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AplazamientoController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct(private readonly AplazamientoCicloService $servicio)
    {
        $this->middleware('permission:aca_aplazamientos')->only(['index', 'show', 'historialCiclo']);
        $this->middleware('permission:aca_aplazamientoCrear')->only(['aplazar', 'confirmar', 'ampliar', 'interrumpir']);
        $this->middleware('permission:aca_aplazamientoInactivar')->only(['revertir']);
    }

    // -------------------------------------------------------------------------
    // Lectura
    // -------------------------------------------------------------------------

    /**
     * Listado general de aplazamientos, filtrable por ciclo, tipo y estado.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Aplazamiento::with(['tipoAplazamiento', 'user', 'ciclo'])
            ->when($request->ciclo_id, fn ($q) => $q->where('ciclo_id', $request->ciclo_id))
            ->when($request->tipo_aplazamiento_id, fn ($q) => $q->where('tipo_aplazamiento_id', $request->tipo_aplazamiento_id))
            ->when(isset($request->estado), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->fecha_desde, fn ($q) => $q->where('fecha_aplazamiento', '>=', $request->fecha_desde))
            ->when($request->fecha_hasta, fn ($q) => $q->where('fecha_aplazamiento', '<=', $request->fecha_hasta))
            ->orderBy('created_at', 'desc');

        $aplazamientos = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => AplazamientoResource::collection($aplazamientos),
            'meta' => [
                'current_page' => $aplazamientos->currentPage(),
                'last_page'    => $aplazamientos->lastPage(),
                'per_page'     => $aplazamientos->perPage(),
                'total'        => $aplazamientos->total(),
                'from'         => $aplazamientos->firstItem(),
                'to'           => $aplazamientos->lastItem(),
            ],
        ]);
    }

    /**
     * Detalle de un aplazamiento.
     *
     * @param Aplazamiento $aplazamiento
     * @return JsonResponse
     */
    public function show(Aplazamiento $aplazamiento): JsonResponse
    {
        $aplazamiento->load(['tipoAplazamiento', 'user', 'ciclo.sede', 'padre', 'hijos']);

        return response()->json([
            'data' => new AplazamientoResource($aplazamiento),
        ]);
    }

    /**
     * Historial de aplazamientos de un ciclo específico.
     *
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function historialCiclo(Ciclo $ciclo): JsonResponse
    {
        $aplazamientos = $ciclo->aplazamientos()
            ->with(['tipoAplazamiento', 'user', 'hijos'])
            ->get();

        return response()->json([
            'data' => AplazamientoResource::collection($aplazamientos),
        ]);
    }

    // -------------------------------------------------------------------------
    // Mutaciones
    // -------------------------------------------------------------------------

    /**
     * Aplaza un ciclo a la fecha probable de reinicio indicada.
     *
     * @param StoreAplazamientoRequest $request
     * @param Ciclo                    $ciclo
     * @return JsonResponse
     */
    public function aplazar(StoreAplazamientoRequest $request, Ciclo $ciclo): JsonResponse
    {
        $aplazamiento = $this->servicio->aplicar($ciclo, $request->validated());
        $aplazamiento->load(['tipoAplazamiento', 'user']);

        return response()->json([
            'message' => "Ciclo '{$ciclo->nombre}' aplazado {$aplazamiento->dias_aplazamiento} días. "
                . "Fecha probable de reinicio: {$aplazamiento->fecha_reinicio_probable->format('d/m/Y')}.",
            'data'    => new AplazamientoResource($aplazamiento),
        ], 201);
    }

    /**
     * Confirma que el ciclo reinició en la fecha probable (sin ajuste de fechas).
     *
     * @param ConfirmarAplazamientoRequest $request
     * @param Aplazamiento                 $aplazamiento
     * @return JsonResponse
     */
    public function confirmar(ConfirmarAplazamientoRequest $request, Aplazamiento $aplazamiento): JsonResponse
    {
        $aplazamiento = $this->servicio->confirmar($aplazamiento, $request->validated());

        return response()->json([
            'message' => 'Aplazamiento confirmado. El ciclo reinició correctamente.',
            'data'    => new AplazamientoResource($aplazamiento->load(['tipoAplazamiento', 'user'])),
        ]);
    }

    /**
     * Amplía el aplazamiento a una fecha posterior, generando un aplazamiento hijo.
     *
     * @param AmpliarAplazamientoRequest $request
     * @param Aplazamiento               $aplazamiento
     * @return JsonResponse
     */
    public function ampliar(AmpliarAplazamientoRequest $request, Aplazamiento $aplazamiento): JsonResponse
    {
        $hijo = $this->servicio->ampliar($aplazamiento, $request->validated());
        $hijo->load(['tipoAplazamiento', 'user', 'padre']);

        return response()->json([
            'message' => "Aplazamiento ampliado {$hijo->dias_aplazamiento} días adicionales. "
                . "Nueva fecha probable de reinicio: {$hijo->fecha_reinicio_probable->format('d/m/Y')}.",
            'data'    => new AplazamientoResource($hijo),
        ], 201);
    }

    /**
     * Interrumpe el aplazamiento porque el ciclo reinició antes de lo previsto.
     * Ajusta todas las fechas hacia atrás según los días reales transcurridos.
     *
     * @param InterrumpirAplazamientoRequest $request
     * @param Aplazamiento                   $aplazamiento
     * @return JsonResponse
     */
    public function interrumpir(InterrumpirAplazamientoRequest $request, Aplazamiento $aplazamiento): JsonResponse
    {
        $aplazamiento = $this->servicio->interrumpir($aplazamiento, $request->validated());

        return response()->json([
            'message' => "Aplazamiento interrumpido. El ciclo reinició el "
                . $aplazamiento->fecha_reinicio_real->format('d/m/Y')
                . " ({$aplazamiento->dias_reales} días efectivos).",
            'data'    => new AplazamientoResource($aplazamiento->load(['tipoAplazamiento', 'user'])),
        ]);
    }

    /**
     * Revierte completamente el aplazamiento, restaurando todas las fechas al estado original.
     *
     * @param Request      $request
     * @param Aplazamiento $aplazamiento
     * @return JsonResponse
     */
    public function revertir(Request $request, Aplazamiento $aplazamiento): JsonResponse
    {
        $request->validate([
            'observaciones' => 'nullable|string|max:1000',
        ]);

        if (!$aplazamiento->esPendiente()) {
            return response()->json([
                'message' => 'Solo se puede revertir un aplazamiento en estado Pendiente.',
            ], 422);
        }

        $aplazamiento = $this->servicio->revertir($aplazamiento, $request->only('observaciones'));

        return response()->json([
            'message' => 'Aplazamiento revertido. Las fechas del ciclo han sido restauradas a su estado original.',
            'data'    => new AplazamientoResource($aplazamiento->load(['tipoAplazamiento', 'user'])),
        ]);
    }
}
