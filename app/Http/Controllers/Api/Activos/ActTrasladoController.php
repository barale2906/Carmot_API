<?php

namespace App\Http\Controllers\Api\Activos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Activos\CancelarTrasladoActActivoRequest;
use App\Http\Requests\Api\Activos\RecibirTrasladoActActivoRequest;
use App\Http\Resources\Api\Activos\ActMovimientoResource;
use App\Models\Activos\ActMovimiento;
use App\Services\Activos\ActActivoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Traslados de activos entre sedes y áreas. El envío se hace desde el activo
 * (ActActivoController::trasladar); aquí se consultan los traslados de las sedes
 * del usuario, se registra la recepción en el destino y se cancelan los pendientes.
 */
class ActTrasladoController extends Controller
{
    /**
     * Registra los middlewares de permisos.
     *
     * @param ActActivoService $activos
     */
    public function __construct(private readonly ActActivoService $activos)
    {
        $this->middleware('permission:act_activos|act_activoRecibir|act_activoTrasladar')->only(['index']);
        $this->middleware('permission:act_activoRecibir')->only(['recibir']);
        $this->middleware('permission:act_activoTrasladar')->only(['cancelar']);
    }

    /**
     * Lista paginada de traslados con origen o destino en las sedes del usuario, del más
     * reciente al más antiguo. Filtros: `estado` (pendiente, recibido, cancelado),
     * `direccion` (entrantes, salientes) y `search` (ID, nombre o serial del activo).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $sedes = $request->user()->idsSedesAccesibles();

        $traslados = ActMovimiento::trasladosDeSedes($sedes)
            ->whereHas('activo')
            ->withTrasladoFilters($request->only(['estado', 'direccion', 'search']), $sedes)
            ->with([...ActMovimiento::RELACIONES, 'activo.categoria'])
            ->latest('fecha')
            ->latest('id')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ActMovimientoResource::collection($traslados),
            'meta' => [
                'current_page' => $traslados->currentPage(),
                'last_page'    => $traslados->lastPage(),
                'per_page'     => $traslados->perPage(),
                'total'        => $traslados->total(),
                'from'         => $traslados->firstItem(),
                'to'           => $traslados->lastItem(),
            ],
        ]);
    }

    /**
     * Registra la recepción del activo en la sede de destino; desde ese momento el activo
     * queda ubicado allí. Solo lo hace quien tiene asignada la sede de destino y, para que
     * la llegada la confirme otra persona, no puede ser quien envió el traslado (salvo el
     * superusuario).
     *
     * @param RecibirTrasladoActActivoRequest $request
     * @param ActMovimiento $traslado
     * @return JsonResponse
     */
    public function recibir(RecibirTrasladoActActivoRequest $request, ActMovimiento $traslado): JsonResponse
    {
        $this->autorizarSede($request, $traslado->sede_destino_id, 'No tiene asignada la sede de destino de este traslado.');
        abort_if(
            $traslado->usuario_id === $request->user()->id && ! $request->user()->hasRole('superusuario'),
            403,
            'La recepción la debe registrar una persona distinta a quien envió el traslado.'
        );

        $this->activos->recibirTraslado($traslado, $request->validated(), $request->user()->id);

        return response()->json([
            'message' => 'Recepción del activo registrada.',
            'data'    => new ActMovimientoResource($traslado->fresh([...ActMovimiento::RELACIONES, 'activo.categoria'])),
        ]);
    }

    /**
     * Cancela un traslado pendiente; el activo permanece en su origen. Solo lo hace quien
     * tiene asignada la sede de origen.
     *
     * @param CancelarTrasladoActActivoRequest $request
     * @param ActMovimiento $traslado
     * @return JsonResponse
     */
    public function cancelar(CancelarTrasladoActActivoRequest $request, ActMovimiento $traslado): JsonResponse
    {
        $this->autorizarSede($request, $traslado->sede_origen_id, 'No tiene asignada la sede de origen de este traslado.');

        $this->activos->cancelarTraslado($traslado, $request->motivo, $request->user()->id);

        return response()->json([
            'message' => 'Traslado cancelado.',
            'data'    => new ActMovimientoResource($traslado->fresh([...ActMovimiento::RELACIONES, 'activo.categoria'])),
        ]);
    }

    /**
     * Responde 403 si la sede no está asignada al usuario.
     *
     * @param Request $request
     * @param int|null $sedeId
     * @param string $mensaje
     * @return void
     */
    private function autorizarSede(Request $request, ?int $sedeId, string $mensaje): void
    {
        abort_unless($sedeId && $request->user()->tieneAccesoSede($sedeId), 403, $mensaje);
    }
}
