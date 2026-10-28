<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\CambiarCierreNotasCicloRequest;
use App\Http\Resources\Api\Academico\CierreNotasCicloResource;
use App\Models\Academico\Ciclo;
use App\Services\Academico\CierreNotasCicloService;
use Illuminate\Http\JsonResponse;

/**
 * Cierre y reapertura del registro de notas por ciclo. Cerrar y reabrir son permisos
 * distintos (`aca_notaCerrar`, `aca_notaReabrir`) para que la reapertura quede en
 * manos de pocos; cada acción queda en la bitácora del ciclo.
 */
class CierreNotasCicloController extends Controller
{
    /**
     * Constructor del controlador.
     *
     * @param CierreNotasCicloService $cierre
     */
    public function __construct(private CierreNotasCicloService $cierre)
    {
        $this->middleware('permission:aca_notas')->only(['show']);
        $this->middleware('permission:aca_notaCerrar')->only(['cerrar']);
        $this->middleware('permission:aca_notaReabrir')->only(['reabrir']);
    }

    /**
     * Estado del registro de notas del ciclo y su bitácora de cierres y reaperturas.
     *
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function show(Ciclo $ciclo): JsonResponse
    {
        return response()->json([
            'data' => new CierreNotasCicloResource($ciclo->load(['notasCerradasPor', 'cierresNotas.usuario'])),
        ]);
    }

    /**
     * Cierra las notas del ciclo: desde ese momento no se registran, editan ni eliminan
     * notas ni recuperaciones de sus estudiantes.
     *
     * @param CambiarCierreNotasCicloRequest $request
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function cerrar(CambiarCierreNotasCicloRequest $request, Ciclo $ciclo): JsonResponse
    {
        $this->autorizarSede($request, $ciclo);

        return response()->json([
            'message' => 'Notas del ciclo cerradas.',
            'data'    => new CierreNotasCicloResource($this->cierre->cerrar($ciclo, $request->user())),
        ]);
    }

    /**
     * Reabre las notas del ciclo. Exige un motivo, que queda en la bitácora.
     *
     * @param CambiarCierreNotasCicloRequest $request
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function reabrir(CambiarCierreNotasCicloRequest $request, Ciclo $ciclo): JsonResponse
    {
        $this->autorizarSede($request, $ciclo);

        return response()->json([
            'message' => 'Notas del ciclo reabiertas.',
            'data'    => new CierreNotasCicloResource($this->cierre->reabrir($ciclo, $request->user(), $request->validated('motivo'))),
        ]);
    }

    /**
     * Corta con 403 si el usuario no tiene asignada la sede del ciclo.
     *
     * @param CambiarCierreNotasCicloRequest $request
     * @param Ciclo $ciclo
     * @return void
     */
    private function autorizarSede(CambiarCierreNotasCicloRequest $request, Ciclo $ciclo): void
    {
        abort_unless($request->user()->tieneAccesoSede((int) $ciclo->sede_id), 403, 'Solo el personal de la sede del ciclo puede cerrar o reabrir sus notas.');
    }
}
