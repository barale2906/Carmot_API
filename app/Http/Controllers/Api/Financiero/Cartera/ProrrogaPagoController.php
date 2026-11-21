<?php

namespace App\Http\Controllers\Api\Financiero\Cartera;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Financiero\Cartera\FiltrarProrrogasPagoRequest;
use App\Http\Requests\Api\Financiero\Cartera\StoreProrrogaPagoRequest;
use App\Http\Resources\Api\Financiero\Cartera\ProrrogaPagoResource;
use App\Models\Financiero\Cartera\ProrrogaPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Prórrogas de pago: plazo nuevo (máx. 15 días) que se acuerda con un estudiante en mora para
 * dejarlo entrar a clase. No modifica la cartera; aparece en la planilla de asistencia mientras
 * esté vigente y, si vence sin pago, el sistema avisa al estudiante y a la administración.
 */
class ProrrogaPagoController extends Controller
{
    private const RELACIONES = ['estudiante', 'sede', 'registradaPor'];

    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:fin_prorrogasPago')->only(['index', 'show']);
        $this->middleware('permission:fin_prorrogaPagoCrear')->only(['store']);
        $this->middleware('permission:fin_prorrogaPagoCancelar')->only(['destroy']);
    }

    /**
     * Listado de prórrogas filtrable por estudiante, estado y rango de la fecha compromiso.
     * Un alumno solo ve las suyas; el personal, las de sus sedes.
     */
    public function index(FiltrarProrrogasPagoRequest $request): JsonResponse
    {
        $prorrogas = ProrrogaPago::visiblesPara($request->user())
            ->conFiltros($request->validated())
            ->with(self::RELACIONES)
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => ProrrogaPagoResource::collection($prorrogas),
            'meta' => [
                'current_page' => $prorrogas->currentPage(),
                'last_page' => $prorrogas->lastPage(),
                'per_page' => $prorrogas->perPage(),
                'total' => $prorrogas->total(),
                'from' => $prorrogas->firstItem(),
                'to' => $prorrogas->lastItem(),
            ],
        ]);
    }

    /**
     * Registra el plazo nuevo de un estudiante en mora. Guarda una foto de sus cuotas vencidas sin
     * modificarlas; el estudiante puede entrar a clase hasta la fecha compromiso.
     */
    public function store(StoreProrrogaPagoRequest $request): JsonResponse
    {
        $prorroga = ProrrogaPago::registrar(
            $request->integer('estudiante_id'),
            $request->input('fecha_compromiso'),
            $request->input('observacion'),
            $request->user()
        )->load(self::RELACIONES);

        return response()->json([
            'message' => 'Plazo nuevo registrado. Aparecerá en la asistencia hasta la fecha compromiso.',
            'data' => new ProrrogaPagoResource($prorroga),
        ], 201);
    }

    /**
     * Muestra una prórroga. Un alumno solo puede ver las suyas (403 en las ajenas).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $prorroga = ProrrogaPago::with(self::RELACIONES)->findOrFail($id);

        abort_unless($request->user()->puedeVerEstudiante($prorroga->estudiante_id), 403, 'Solo puede consultar su propia información.');

        return response()->json(['data' => new ProrrogaPagoResource($prorroga)]);
    }

    /**
     * Cancela la prórroga (soft delete): deja de aparecer en la asistencia y el sistema no la vigila.
     */
    public function destroy(int $id): JsonResponse
    {
        ProrrogaPago::findOrFail($id)->delete();

        return response()->json(['message' => 'Plazo nuevo cancelado.']);
    }
}
