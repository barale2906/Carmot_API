<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreRecuperacionNotaRequest;
use App\Http\Requests\Api\Academico\UpdateRecuperacionNotaRequest;
use App\Http\Resources\Api\Academico\RecuperacionNotaResource;
use App\Models\Academico\Grupo;
use App\Models\Academico\RecuperacionNota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recuperaciones de módulos reprobados. La nota de recuperación reemplaza la nota final
 * del esquema y define si el estudiante aprueba el módulo (ver SabanaNotasService).
 */
class RecuperacionNotaController extends Controller
{
    /**
     * Constructor del controlador: reutiliza los permisos de notas.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_notas')->only(['index', 'show']);
        $this->middleware('permission:aca_notaCrear')->only(['store']);
        $this->middleware('permission:aca_notaEditar')->only(['update']);
        $this->middleware('permission:aca_notaInactivar')->only(['destroy', 'restore']);
    }

    /**
     * Lista las recuperaciones registradas. A un alumno solo se le listan las suyas.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['estudiante_id', 'grupo_id', 'modulo_id', 'include_trashed', 'only_trashed']);

        if ($request->user()->esSoloEstudiante()) {
            $filters['estudiante_id'] = $request->user()->id;
        }

        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['estudiante', 'grupo', 'modulo'];

        $recuperaciones = RecuperacionNota::withFilters($filters)
            ->withRelationsAndCounts($relations, false)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => RecuperacionNotaResource::collection($recuperaciones),
            'meta' => [
                'current_page' => $recuperaciones->currentPage(),
                'last_page'    => $recuperaciones->lastPage(),
                'per_page'     => $recuperaciones->perPage(),
                'total'        => $recuperaciones->total(),
                'from'         => $recuperaciones->firstItem(),
                'to'           => $recuperaciones->lastItem(),
            ],
        ]);
    }

    /**
     * Registra la recuperación de un módulo reprobado. Si el estudiante ya tenía una
     * (aunque estuviera eliminada) se actualiza: hay una sola por estudiante y grupo.
     *
     * @param StoreRecuperacionNotaRequest $request
     * @return JsonResponse
     */
    public function store(StoreRecuperacionNotaRequest $request): JsonResponse
    {
        $grupo = Grupo::findOrFail($request->grupo_id);
        $this->autorizarGrupo($request, $grupo);

        $recuperacion = RecuperacionNota::withTrashed()->updateOrCreate(
            [
                'estudiante_id' => $request->estudiante_id,
                'grupo_id'      => $grupo->id,
                'modulo_id'     => $grupo->modulo_id,
            ],
            [
                'nota'              => $request->nota,
                'fecha_registro'    => $request->fecha_registro,
                'registrado_por_id' => $request->user()->id,
                'observaciones'     => $request->observaciones,
                'deleted_at'        => null,
            ]
        );

        return response()->json([
            'message' => 'Recuperación registrada exitosamente.',
            'data'    => new RecuperacionNotaResource($recuperacion->load(['estudiante', 'grupo', 'modulo', 'registradoPor'])),
        ], 201);
    }

    /**
     * Muestra una recuperación. Un alumno solo puede ver las suyas.
     *
     * @param Request $request
     * @param RecuperacionNota $recuperacionNota
     * @return JsonResponse
     */
    public function show(Request $request, RecuperacionNota $recuperacionNota): JsonResponse
    {
        abort_unless($request->user()->puedeVerEstudiante($recuperacionNota->estudiante_id), 403, 'Solo puede consultar su propia información.');

        return response()->json([
            'data' => new RecuperacionNotaResource($recuperacionNota->load(['estudiante', 'grupo', 'modulo', 'registradoPor'])),
        ]);
    }

    /**
     * Actualiza la nota, la fecha o las observaciones de una recuperación.
     *
     * @param UpdateRecuperacionNotaRequest $request
     * @param RecuperacionNota $recuperacionNota
     * @return JsonResponse
     */
    public function update(UpdateRecuperacionNotaRequest $request, RecuperacionNota $recuperacionNota): JsonResponse
    {
        $this->autorizarGrupo($request, $recuperacionNota->grupo);

        $recuperacionNota->update($request->safe()->only(['nota', 'fecha_registro', 'observaciones']) + [
            'registrado_por_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Recuperación actualizada exitosamente.',
            'data'    => new RecuperacionNotaResource($recuperacionNota->load(['estudiante', 'grupo', 'modulo', 'registradoPor'])),
        ]);
    }

    /**
     * Elimina una recuperación (soft delete): el módulo vuelve a quedar reprobado por la
     * nota final. No se permite si las notas del ciclo están cerradas.
     *
     * @param Request $request
     * @param RecuperacionNota $recuperacionNota
     * @return JsonResponse
     */
    public function destroy(Request $request, RecuperacionNota $recuperacionNota): JsonResponse
    {
        $this->autorizarGrupo($request, $recuperacionNota->grupo);

        if ($recuperacionNota->grupo->notasCerradasPara($recuperacionNota->estudiante_id)) {
            return $this->respuestaNotasCerradas();
        }

        $recuperacionNota->delete();

        return response()->json([
            'message' => 'Recuperación eliminada exitosamente.',
        ]);
    }

    /**
     * Restaura una recuperación eliminada. No se permite si las notas del ciclo están cerradas.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $recuperacion = RecuperacionNota::onlyTrashed()->findOrFail($id);
        $this->autorizarGrupo($request, $recuperacion->grupo);

        if ($recuperacion->grupo->notasCerradasPara($recuperacion->estudiante_id)) {
            return $this->respuestaNotasCerradas();
        }

        $recuperacion->restore();

        return response()->json([
            'message' => 'Recuperación restaurada exitosamente.',
            'data'    => new RecuperacionNotaResource($recuperacion->load(['estudiante', 'grupo', 'modulo'])),
        ]);
    }

    /**
     * Corta con 403 si el usuario no puede registrar en el grupo: solo su profesor o el
     * personal con la sede del grupo asignada (igual que las notas).
     *
     * @param Request $request
     * @param Grupo|null $grupo
     * @return void
     */
    private function autorizarGrupo(Request $request, ?Grupo $grupo): void
    {
        abort_unless($grupo && $request->user()->puedeGestionarGrupo($grupo), 403, 'Solo el profesor del grupo o el personal de su sede puede registrar en este grupo.');
    }

    /**
     * Respuesta 422 cuando las notas del ciclo del estudiante están cerradas.
     *
     * @return JsonResponse
     */
    private function respuestaNotasCerradas(): JsonResponse
    {
        return response()->json([
            'message' => 'Las notas del ciclo están cerradas. Para modificarlas, primero deben reabrirse.',
        ], 422);
    }
}
