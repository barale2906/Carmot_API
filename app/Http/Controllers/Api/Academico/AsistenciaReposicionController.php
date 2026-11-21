<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\FiltrarReposicionesRequest;
use App\Http\Requests\Api\Academico\StoreAsistenciaReposicionRequest;
use App\Http\Resources\Api\Academico\AsistenciaReposicionResource;
use App\Models\Academico\AsistenciaReposicion;
use App\Models\User;
use App\Notifications\Academico\ReposicionClaseNotification;
use App\Services\Asistencia\ReposicionOpcionesService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reposiciones de clase: el estudiante (o el instituto por él) notifica que tomará una clase de
 * su grupo en otro grupo de su curso, la misma semana y con el mismo módulo. Solo se registra y
 * se notifica al instituto; no requiere aprobación.
 */
class AsistenciaReposicionController extends Controller
{
    private const RELACIONES = ['estudiante', 'claseOrigen.grupo', 'claseDestino.grupo', 'registradaPor'];

    /**
     * Constructor del controlador.
     */
    public function __construct(private readonly ReposicionOpcionesService $opciones)
    {
        $this->middleware('permission:aca_reposiciones')->only(['index', 'show']);
        $this->middleware('permission:aca_reposicionCrear')->only(['store', 'opciones']);
        $this->middleware('permission:aca_reposicionInactivar')->only(['destroy']);
    }

    /**
     * Listado de reposiciones filtrable por estudiante, grupo (de origen o destino) y rango de la
     * fecha de la clase de destino. Un alumno solo ve las suyas; el personal, las de sus sedes o grupos.
     */
    public function index(FiltrarReposicionesRequest $request): JsonResponse
    {
        $reposiciones = AsistenciaReposicion::visiblesPara($request->user())
            ->conFiltros($request->validated())
            ->with(self::RELACIONES)
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => AsistenciaReposicionResource::collection($reposiciones),
            'meta' => [
                'current_page' => $reposiciones->currentPage(),
                'last_page' => $reposiciones->lastPage(),
                'per_page' => $reposiciones->perPage(),
                'total' => $reposiciones->total(),
                'from' => $reposiciones->firstItem(),
                'to' => $reposiciones->lastItem(),
            ],
        ]);
    }

    /**
     * Opciones para pedir una reposición en una semana (por defecto la actual): las clases del estudiante
     * en sus grupos y, de cada una, las clases de otros grupos que puede tomar (mismo curso, semana y módulo;
     * ver ReposicionOpcionesService). `reposicion_id` indica que esa clase ya tiene reposición. Un alumno solo
     * consulta las suyas.
     *
     * @queryParam estudiante_id int required Estudiante.
     * @queryParam fecha date Cualquier día de la semana a consultar (YYYY-MM-DD). Por defecto, hoy.
     */
    public function opciones(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'estudiante_id' => ['required', 'integer', 'exists:users,id'],
            'fecha' => ['nullable', 'date'],
        ], [
            'estudiante_id.required' => 'El estudiante es obligatorio.',
            'estudiante_id.exists' => 'El estudiante seleccionado no existe.',
            'fecha.date' => 'La fecha no es válida.',
        ]);

        abort_unless($request->user()->puedeVerEstudiante((int) $datos['estudiante_id']), 403, 'Solo puede consultar su propia información.');

        $clase = fn ($c) => [
            'id' => $c->id,
            'fecha_clase' => $c->fecha_clase->format('Y-m-d'),
            'hora_inicio' => $c->hora_inicio,
            'hora_fin' => $c->hora_fin,
            'estado' => $c->estado,
            'grupo' => ['id' => $c->grupo->id, 'nombre' => $c->grupo->nombre, 'sede' => $c->grupo->sede?->nombre],
        ];

        $opciones = $this->opciones->obtener((int) $datos['estudiante_id'], Carbon::parse($datos['fecha'] ?? today()));

        return response()->json(['data' => $opciones->map(fn ($o) => [
            'origen' => $clase($o['origen']),
            'reposicion_id' => $o['reposicion_id'],
            'destinos' => $o['destinos']->map($clase)->values(),
        ])->values()]);
    }

    /**
     * Registra la reposición y notifica al personal de la sede y al profesor del grupo de destino.
     * La clase de origen deja de contar como falla y el estudiante aparece en la planilla del destino.
     */
    public function store(StoreAsistenciaReposicionRequest $request): JsonResponse
    {
        $reposicion = AsistenciaReposicion::create($request->validated() + ['registrada_por_id' => $request->user()->id])
            ->load(self::RELACIONES);

        $this->notificar($reposicion, $request->user());

        return response()->json([
            'message' => 'Reposición registrada. Se notificó al instituto.',
            'data' => new AsistenciaReposicionResource($reposicion),
        ], 201);
    }

    /**
     * Muestra una reposición. Un alumno solo puede ver las suyas (403 en las ajenas).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $reposicion = AsistenciaReposicion::with(self::RELACIONES)->findOrFail($id);

        abort_unless($request->user()->puedeVerEstudiante($reposicion->estudiante_id), 403, 'Solo puede consultar su propia información.');

        return response()->json(['data' => new AsistenciaReposicionResource($reposicion)]);
    }

    /**
     * Cancela la reposición (soft delete): la clase de origen vuelve a contar como falla si no se asistió.
     */
    public function destroy(int $id): JsonResponse
    {
        AsistenciaReposicion::findOrFail($id)->delete();

        return response()->json(['message' => 'Reposición cancelada.']);
    }

    /**
     * Notifica al personal con acceso a la sede del grupo de destino que puede gestionar reposiciones
     * y al profesor de ese grupo, sin avisar de nuevo a quien la registró.
     */
    private function notificar(AsistenciaReposicion $reposicion, User $registrador): void
    {
        $grupo = $reposicion->claseDestino->grupo;

        $destinatarios = User::permission('aca_reposicionInactivar')->get()
            ->filter(fn (User $u) => $u->tieneAccesoSede((int) $grupo->sede_id))
            ->push($grupo->profesor)
            ->filter()
            ->reject(fn (User $u) => $u->id === $registrador->id)
            ->unique('id');

        $destinatarios->each->notify(new ReposicionClaseNotification($reposicion));
    }
}
