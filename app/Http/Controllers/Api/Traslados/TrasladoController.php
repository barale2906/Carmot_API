<?php

namespace App\Http\Controllers\Api\Traslados;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Traslados\OpcionesTrasladoRequest;
use App\Http\Requests\Api\Traslados\ResolverNivelacionRequest;
use App\Http\Requests\Api\Traslados\SimularTrasladoRequest;
use App\Http\Requests\Api\Traslados\StoreTrasladoRequest;
use App\Http\Resources\Api\Traslados\MatriculaTrasladoResource;
use App\Http\Resources\Api\Traslados\TrasladoOpcionesResource;
use App\Http\Resources\Api\Traslados\TrasladoSimulacionResource;
use App\Models\Academico\Matricula;
use App\Models\Traslados\MatriculaTraslado;
use App\Services\Traslados\TrasladoMatriculaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Traslados de estudiantes: cambio de programación (jornada o ciclo), de curso, de sede, o de sede y
 * curso. La matrícula se conserva; el traslado queda en un historial con lo financiero y la
 * nivelación para hacerle seguimiento.
 */
class TrasladoController extends Controller
{
    private const RELACIONES = [
        'matricula.estudiante', 'usuario', 'nivelacionResueltaPor',
        'sedeOrigen', 'sedeDestino', 'cursoOrigen', 'cursoDestino',
        'cicloOrigen', 'cicloDestino', 'programacionOrigen', 'programacionDestino',
    ];

    /**
     * Registra los middlewares de permisos.
     */
    public function __construct(private readonly TrasladoMatriculaService $traslados)
    {
        $this->middleware('permission:tra_traslados')->only(['index', 'show', 'opciones', 'simular']);
        $this->middleware('permission:tra_trasladoCrear')->only(['store']);
        $this->middleware('permission:tra_trasladoNivelacion')->only(['resolverNivelacion']);
    }

    /**
     * Lista paginada de traslados con origen o destino en las sedes del usuario, del más reciente al
     * más antiguo. Filtros: `matricula_id`, `estudiante_id`, `tipo`, `sede_id`, `nivelacion_pendiente`,
     * `fecha_inicio` y `fecha_fin`.
     */
    public function index(Request $request): JsonResponse
    {
        $traslados = MatriculaTraslado::deSedes($request->user()->idsSedesAccesibles())
            ->withFilters($request->only(['matricula_id', 'estudiante_id', 'tipo', 'sede_id', 'nivelacion_pendiente', 'fecha_inicio', 'fecha_fin']))
            ->with(self::RELACIONES)
            ->latest('fecha')
            ->latest('id')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => MatriculaTrasladoResource::collection($traslados),
            'meta' => [
                'current_page' => $traslados->currentPage(),
                'last_page' => $traslados->lastPage(),
                'per_page' => $traslados->perPage(),
                'total' => $traslados->total(),
                'from' => $traslados->firstItem(),
                'to' => $traslados->lastItem(),
            ],
        ]);
    }

    /**
     * Ciclos (con su programación y jornada) y precios de lista disponibles en la sede y el curso de
     * destino, para que el estudiante elija el nuevo horario.
     */
    public function opciones(OpcionesTrasladoRequest $request): JsonResponse
    {
        $matricula = Matricula::findOrFail($request->integer('matricula_id'));

        return response()->json([
            'data' => new TrasladoOpcionesResource(
                $this->traslados->opciones($matricula, $request->integer('sede_id'), $request->integer('curso_id'))
            ),
        ]);
    }

    /**
     * Simula el traslado sin escribir nada: tipo deducido, lo que se cancela y se genera en la
     * cartera, el cobro del traslado y la nivelación (si el curso es el mismo). La nivelación es
     * solo informativa y no impide el traslado.
     */
    public function simular(SimularTrasladoRequest $request): JsonResponse
    {
        return response()->json([
            'data' => new TrasladoSimulacionResource($this->traslados->simular($request->matricula(), $request->destino())),
        ]);
    }

    /**
     * Registra el traslado: mueve la matrícula al destino, cancela la cartera pendiente de la sede de
     * origen y genera la nueva en la de destino (lo ya pagado no se traslada), cobra el traslado si
     * tiene tarifa y cancela las reposiciones pendientes del ciclo de origen.
     */
    public function store(StoreTrasladoRequest $request): JsonResponse
    {
        $traslado = $this->traslados->ejecutar(
            $request->matricula(),
            $request->destino(),
            $request->input('motivo'),
            $request->user()->id
        );

        return response()->json([
            'message' => 'Traslado registrado exitosamente.',
            'data' => new MatriculaTrasladoResource($traslado->load('matricula.estudiante')),
        ], 201);
    }

    /**
     * Muestra un traslado con su origen, destino, lo financiero y la nivelación.
     */
    public function show(Request $request, MatriculaTraslado $matriculaTraslado): JsonResponse
    {
        $this->autorizarSede($request, $matriculaTraslado);

        return response()->json([
            'data' => new MatriculaTrasladoResource($matriculaTraslado->load(self::RELACIONES)),
        ]);
    }

    /**
     * Marca como resuelta la nivelación pendiente de un traslado y deja lo que se acordó.
     */
    public function resolverNivelacion(ResolverNivelacionRequest $request, MatriculaTraslado $matriculaTraslado): JsonResponse
    {
        $this->autorizarSede($request, $matriculaTraslado);
        abort_unless($matriculaTraslado->nivelacion_pendiente, 422, 'Este traslado no tiene una nivelación pendiente.');

        $this->traslados->resolverNivelacion($matriculaTraslado, $request->user()->id, $request->input('observaciones'));

        return response()->json([
            'message' => 'Nivelación marcada como resuelta.',
            'data' => new MatriculaTrasladoResource($matriculaTraslado->load(self::RELACIONES)),
        ]);
    }

    /**
     * Impide consultar o gestionar un traslado que no toca ninguna de las sedes del usuario.
     */
    private function autorizarSede(Request $request, MatriculaTraslado $traslado): void
    {
        abort_unless(
            $request->user()->tieneAccesoSede($traslado->sede_origen_id) || $request->user()->tieneAccesoSede($traslado->sede_destino_id),
            403,
            'No tiene asignada ninguna de las sedes de este traslado.'
        );
    }
}
