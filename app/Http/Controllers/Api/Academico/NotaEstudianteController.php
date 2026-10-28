<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\FiltrarConsolidadoNotasRequest;
use App\Http\Requests\Api\Academico\StoreNotaEstudianteRequest;
use App\Http\Requests\Api\Academico\StoreNotaMasivaRequest;
use App\Http\Requests\Api\Academico\UpdateNotaEstudianteRequest;
use App\Http\Resources\Api\Academico\NotaEstudianteResource;
use App\Http\Resources\Api\Academico\ResultadoModuloResource;
use App\Http\Resources\Api\Academico\SabanaNotasEstudianteResource;
use App\Http\Resources\Api\Academico\SabanaNotasGrupalResource;
use App\Models\Academico\Ciclo;
use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\Grupo;
use App\Models\Academico\NotaEstudiante;
use App\Models\Academico\TipoNotaEsquema;
use App\Models\User;
use App\Services\Academico\ConsolidadoNotasService;
use App\Services\Academico\SabanaNotasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NotaEstudianteController extends Controller
{
    /**
     * Constructor del controlador.
     *
     * @param SabanaNotasService      $sabanaNotas  Cálculo de las sábanas de notas (por estudiante y grupal).
     * @param ConsolidadoNotasService $consolidado  Consulta de resultados por estudiante, grupo, ciclo o profesor.
     */
    public function __construct(
        private SabanaNotasService $sabanaNotas,
        private ConsolidadoNotasService $consolidado
    ) {
        $this->middleware('permission:aca_notas')->only(['index', 'show', 'sabanaEstudiante', 'sabanaGrupal', 'calcularNotaFinal', 'consolidado', 'filters']);
        $this->middleware('permission:aca_notaCrear')->only(['store', 'storeMasivo']);
        $this->middleware('permission:aca_notaEditar')->only(['update']);
        $this->middleware('permission:aca_notaInactivar')->only(['destroy', 'restore']);
    }

    /**
     * Muestra una lista de notas de estudiantes. A un alumno solo se le listan sus propias notas.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'estudiante_id', 'grupo_id', 'modulo_id', 'ciclo_id', 'profesor_id', 'esquema_calificacion_id',
            'tipo_nota_esquema_id', 'status', 'include_trashed', 'only_trashed'
        ]);

        // El alumno solo ve sus propias notas
        if ($request->user()->esSoloEstudiante()) {
            $filters['estudiante_id'] = $request->user()->id;
        }

        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['estudiante', 'grupo', 'modulo', 'tipoNotaEsquema'];

        $notas = NotaEstudiante::withFilters($filters)
            ->withRelationsAndCounts($relations, false)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => NotaEstudianteResource::collection($notas),
            'meta' => [
                'current_page' => $notas->currentPage(),
                'last_page' => $notas->lastPage(),
                'per_page' => $notas->perPage(),
                'total' => $notas->total(),
                'from' => $notas->firstItem(),
                'to' => $notas->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena una nueva nota de estudiante.
     *
     * @param StoreNotaEstudianteRequest $request
     * @return JsonResponse
     */
    public function store(StoreNotaEstudianteRequest $request): JsonResponse
    {
        $this->autorizarGrupo($request, Grupo::findOrFail($request->grupo_id));

        try {
            DB::beginTransaction();

            // Obtener el tipo de nota para validar rango y calcular peso
            $tipoNota = TipoNotaEsquema::findOrFail($request->tipo_nota_esquema_id);

            // Validar que la nota esté en el rango permitido
            if (!$tipoNota->notaValida($request->nota)) {
                return response()->json([
                    'message' => "La nota debe estar entre {$tipoNota->nota_minima} y {$tipoNota->nota_maxima}.",
                ], 422);
            }

            // Calcular nota ponderada
            $notaPonderada = NotaEstudiante::calcularNotaPonderada($request->nota, $tipoNota->peso);

            // Buscar si ya existe una nota para este estudiante/tipo/grupo/módulo
            $notaExistente = NotaEstudiante::where('estudiante_id', $request->estudiante_id)
                ->where('grupo_id', $request->grupo_id)
                ->where('modulo_id', $request->modulo_id)
                ->where('tipo_nota_esquema_id', $request->tipo_nota_esquema_id)
                ->first();

            if ($notaExistente) {
                // Actualizar nota existente
                $notaExistente->update([
                    'nota' => $request->nota,
                    'nota_ponderada' => $notaPonderada,
                    'fecha_registro' => $request->fecha_registro,
                    'registrado_por_id' => auth()->id(),
                    'observaciones' => $request->observaciones,
                    'status' => $request->status ?? 1,
                ]);

                $nota = $notaExistente;
            } else {
                // Crear nueva nota
                $nota = NotaEstudiante::create([
                    'estudiante_id' => $request->estudiante_id,
                    'grupo_id' => $request->grupo_id,
                    'modulo_id' => $request->modulo_id,
                    'esquema_calificacion_id' => $request->esquema_calificacion_id,
                    'tipo_nota_esquema_id' => $request->tipo_nota_esquema_id,
                    'nota' => $request->nota,
                    'nota_ponderada' => $notaPonderada,
                    'fecha_registro' => $request->fecha_registro,
                    'registrado_por_id' => auth()->id(),
                    'observaciones' => $request->observaciones,
                    'status' => $request->status ?? 1,
                ]);
            }

            DB::commit();

            $nota->load(['estudiante', 'grupo', 'modulo', 'esquemaCalificacion', 'tipoNotaEsquema', 'registradoPor']);

            return response()->json([
                'message' => 'Nota registrada exitosamente.',
                'data' => new NotaEstudianteResource($nota),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar la nota.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Almacena múltiples notas de estudiantes (registro masivo).
     *
     * @param StoreNotaMasivaRequest $request
     * @return JsonResponse
     */
    public function storeMasivo(StoreNotaMasivaRequest $request): JsonResponse
    {
        $this->autorizarGrupo($request, Grupo::findOrFail($request->grupo_id));

        try {
            DB::beginTransaction();

            $tipoNota = TipoNotaEsquema::findOrFail($request->tipo_nota_esquema_id);
            $notasCreadas = [];
            $notasActualizadas = [];
            $errores = [];

            foreach ($request->notas as $notaData) {
                try {
                    // Validar rango de nota
                    if (!$tipoNota->notaValida($notaData['nota'])) {
                        $errores[] = [
                            'estudiante_id' => $notaData['estudiante_id'],
                            'error' => "La nota debe estar entre {$tipoNota->nota_minima} y {$tipoNota->nota_maxima}."
                        ];
                        continue;
                    }

                    // Calcular nota ponderada
                    $notaPonderada = NotaEstudiante::calcularNotaPonderada($notaData['nota'], $tipoNota->peso);

                    // Buscar si ya existe
                    $notaExistente = NotaEstudiante::where('estudiante_id', $notaData['estudiante_id'])
                        ->where('grupo_id', $request->grupo_id)
                        ->where('modulo_id', $request->modulo_id)
                        ->where('tipo_nota_esquema_id', $request->tipo_nota_esquema_id)
                        ->first();

                    if ($notaExistente) {
                        $notaExistente->update([
                            'nota' => $notaData['nota'],
                            'nota_ponderada' => $notaPonderada,
                            'fecha_registro' => $request->fecha_registro,
                            'registrado_por_id' => auth()->id(),
                            'observaciones' => $notaData['observaciones'] ?? null,
                            'status' => 1,
                        ]);
                        $notasActualizadas[] = $notaExistente->id;
                    } else {
                        $nota = NotaEstudiante::create([
                            'estudiante_id' => $notaData['estudiante_id'],
                            'grupo_id' => $request->grupo_id,
                            'modulo_id' => $request->modulo_id,
                            'esquema_calificacion_id' => $request->esquema_calificacion_id,
                            'tipo_nota_esquema_id' => $request->tipo_nota_esquema_id,
                            'nota' => $notaData['nota'],
                            'nota_ponderada' => $notaPonderada,
                            'fecha_registro' => $request->fecha_registro,
                            'registrado_por_id' => auth()->id(),
                            'observaciones' => $notaData['observaciones'] ?? null,
                            'status' => 1,
                        ]);
                        $notasCreadas[] = $nota->id;
                    }
                } catch (\Exception $e) {
                    $errores[] = [
                        'estudiante_id' => $notaData['estudiante_id'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Registro masivo de notas completado.',
                'data' => [
                    'creadas' => count($notasCreadas),
                    'actualizadas' => count($notasActualizadas),
                    'errores' => count($errores),
                    'detalle_errores' => $errores,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al realizar el registro masivo de notas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra la nota especificada.
     *
     * @param NotaEstudiante $notaEstudiante
     * @param Request $request
     * @return JsonResponse
     */
    public function show(NotaEstudiante $notaEstudiante, Request $request): JsonResponse
    {
        abort_unless($request->user()->puedeVerEstudiante($notaEstudiante->estudiante_id), 403, 'Solo puede consultar su propia información.');

        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['estudiante', 'grupo', 'modulo', 'esquemaCalificacion', 'tipoNotaEsquema', 'registradoPor'];

        $notaEstudiante->load($relations);

        return response()->json([
            'data' => new NotaEstudianteResource($notaEstudiante),
        ]);
    }

    /**
     * Actualiza la nota especificada.
     *
     * @param UpdateNotaEstudianteRequest $request
     * @param NotaEstudiante $notaEstudiante
     * @return JsonResponse
     */
    public function update(UpdateNotaEstudianteRequest $request, NotaEstudiante $notaEstudiante): JsonResponse
    {
        $this->autorizarGrupo($request, $notaEstudiante->grupo);

        try {
            DB::beginTransaction();

            $updateData = [];

            // Si se actualiza la nota, recalcular la ponderada
            if ($request->has('nota')) {
                $tipoNota = $notaEstudiante->tipoNotaEsquema;

                if (!$tipoNota->notaValida($request->nota)) {
                    return response()->json([
                        'message' => "La nota debe estar entre {$tipoNota->nota_minima} y {$tipoNota->nota_maxima}.",
                    ], 422);
                }

                $updateData['nota'] = $request->nota;
                $updateData['nota_ponderada'] = NotaEstudiante::calcularNotaPonderada($request->nota, $tipoNota->peso);
                $updateData['registrado_por_id'] = auth()->id();
            }

            if ($request->has('fecha_registro')) {
                $updateData['fecha_registro'] = $request->fecha_registro;
            }

            if ($request->has('observaciones')) {
                $updateData['observaciones'] = $request->observaciones;
            }

            if ($request->has('status')) {
                $updateData['status'] = $request->status;
            }

            $notaEstudiante->update($updateData);

            DB::commit();

            $notaEstudiante->load(['estudiante', 'grupo', 'modulo', 'esquemaCalificacion', 'tipoNotaEsquema', 'registradoPor']);

            return response()->json([
                'message' => 'Nota actualizada exitosamente.',
                'data' => new NotaEstudianteResource($notaEstudiante),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar la nota.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina la nota especificada (soft delete). No se permite si las notas del ciclo están cerradas.
     *
     * @param NotaEstudiante $notaEstudiante
     * @return JsonResponse
     */
    public function destroy(NotaEstudiante $notaEstudiante): JsonResponse
    {
        if ($notaEstudiante->grupo->notasCerradasPara($notaEstudiante->estudiante_id)) {
            return $this->respuestaNotasCerradas();
        }

        $notaEstudiante->delete();

        return response()->json([
            'message' => 'Nota eliminada exitosamente.',
        ]);
    }

    /**
     * Restaura una nota eliminada (soft delete). No se permite si las notas del ciclo están cerradas.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $nota = NotaEstudiante::onlyTrashed()->findOrFail($id);

        if ($nota->grupo->notasCerradasPara($nota->estudiante_id)) {
            return $this->respuestaNotasCerradas();
        }

        $nota->restore();

        return response()->json([
            'message' => 'Nota restaurada exitosamente.',
            'data' => new NotaEstudianteResource($nota->load(['estudiante', 'grupo', 'modulo', 'tipoNotaEsquema'])),
        ]);
    }

    /**
     * Calcula la nota final de un estudiante en un módulo. Un alumno solo puede calcular la suya.
     *
     * @param Request $request
     * @param int $estudianteId
     * @param int $moduloId
     * @param int|null $grupoId
     * @return JsonResponse
     */
    public function calcularNotaFinal(Request $request, int $estudianteId, int $moduloId, ?int $grupoId = null): JsonResponse
    {
        abort_unless($request->user()->puedeVerEstudiante($estudianteId), 403, 'Solo puede consultar su propia información.');

        try {
            // Obtener el esquema activo
            $esquema = EsquemaCalificacion::activoParaModuloGrupo($moduloId, $grupoId)->first();

            if (!$esquema) {
                return response()->json([
                    'message' => 'No se encontró un esquema activo para este módulo y grupo.',
                ], 404);
            }

            // Obtener todas las notas del estudiante en este módulo/grupo
            $query = NotaEstudiante::where('estudiante_id', $estudianteId)
                ->where('modulo_id', $moduloId)
                ->where('esquema_calificacion_id', $esquema->id)
                ->where('status', 1); // Solo notas registradas

            if ($grupoId) {
                $query->where('grupo_id', $grupoId);
            }

            $notas = $query->get();

            // Calcular nota final (suma de notas ponderadas)
            $notaFinal = $notas->sum('nota_ponderada');

            // Obtener tipos de nota del esquema para verificar cuáles faltan
            $tiposNota = $esquema->tiposNota;
            $tiposConNota = $notas->pluck('tipo_nota_esquema_id')->toArray();
            $tiposPendientes = $tiposNota->whereNotIn('id', $tiposConNota);

            return response()->json([
                'data' => [
                    'estudiante_id' => $estudianteId,
                    'modulo_id' => $moduloId,
                    'grupo_id' => $grupoId,
                    'esquema_calificacion_id' => $esquema->id,
                    'nota_final' => round($notaFinal, 2),
                    'total_tipos_nota' => $tiposNota->count(),
                    'tipos_con_nota' => count($tiposConNota),
                    'tipos_pendientes' => $tiposPendientes->count(),
                    'completo' => $tiposPendientes->isEmpty(),
                    'notas' => NotaEstudianteResource::collection($notas),
                    'tipos_pendientes_detalle' => $tiposPendientes->map(function ($tipo) {
                        return [
                            'id' => $tipo->id,
                            'nombre_tipo' => $tipo->nombre_tipo,
                            'peso' => (float) $tipo->peso,
                        ];
                    }),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al calcular la nota final.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene la sabana de notas de un estudiante (todos sus módulos). Un alumno solo puede pedir la suya.
     *
     * @param Request $request
     * @param int $estudianteId
     * @return JsonResponse
     */
    public function sabanaEstudiante(Request $request, int $estudianteId): JsonResponse
    {
        abort_unless($request->user()->puedeVerEstudiante($estudianteId), 403, 'Solo puede consultar su propia información.');
        User::findOrFail($estudianteId); // 404 si no existe (antes 500, B27)

        try {
            $data = $this->sabanaNotas->porEstudiante(
                $estudianteId,
                $request->get('ciclo_id'),
                $request->get('curso_id')
            );

            if (!$data) {
                return response()->json([
                    'message' => 'No se encontró una matrícula activa para este estudiante.',
                ], 404);
            }

            return response()->json([
                'data' => new SabanaNotasEstudianteResource($data),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al generar la sabana de notas del estudiante.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene la sabana de notas grupal (todos los estudiantes de un grupo en un módulo). Prohibida para alumnos.
     * Cada estudiante trae su resultado: nota final, recuperación, nota definitiva, estado y si sus notas están cerradas.
     *
     * @param Request $request
     * @param int $grupoId
     * @param int $moduloId
     * @return JsonResponse
     */
    public function sabanaGrupal(Request $request, int $grupoId, int $moduloId): JsonResponse
    {
        abort_if($request->user()->esSoloEstudiante(), 403, 'Un estudiante no puede consultar la información de un grupo completo.');

        $grupo = Grupo::findOrFail($grupoId);

        if ((int) $grupo->modulo_id !== $moduloId) {
            return response()->json([
                'message' => 'El módulo no pertenece al grupo especificado.',
            ], 422);
        }

        $data = $this->sabanaNotas->porGrupo($grupo);

        if (!$data) {
            return response()->json([
                'message' => 'No se encontró un esquema activo para este módulo y grupo.',
            ], 404);
        }

        return response()->json([
            'data' => new SabanaNotasGrupalResource($data),
        ]);
    }

    /**
     * Consolidado de resultados por módulo: una fila por estudiante y grupo con nota final,
     * recuperación, nota definitiva y estado (pendiente, aprobado, reprobado).
     *
     * Se filtra por ciclo, grupo, profesor, estudiante o búsqueda (combinables; al menos uno).
     * El alumno solo ve lo suyo, el profesor solo sus grupos y el resto del personal los
     * grupos de sus sedes. `meta` agrega el `resumen` del total filtrado (no solo de la
     * página) y `sin_esquema`, los grupos cuyo módulo no tiene esquema activo.
     *
     * @param FiltrarConsolidadoNotasRequest $request
     * @return JsonResponse
     */
    public function consolidado(FiltrarConsolidadoNotasRequest $request): JsonResponse
    {
        $resultado = $this->consolidado->consultar($request->filtros());
        $filas     = $resultado['filas'];
        $perPage   = (int) $request->get('per_page', 50);
        $page      = (int) $request->get('page', 1);
        $pagina    = new LengthAwarePaginator($filas->forPage($page, $perPage)->values(), $filas->count(), $perPage, $page);

        return response()->json([
            'data' => ResultadoModuloResource::collection($pagina->getCollection()),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page'    => $pagina->lastPage(),
                'per_page'     => $pagina->perPage(),
                'total'        => $pagina->total(),
                'from'         => $pagina->firstItem(),
                'to'           => $pagina->lastItem(),
                'resumen'      => $this->consolidado->resumen($filas),
                'sin_esquema'  => $resultado['sin_esquema'],
            ],
        ]);
    }

    /**
     * Opciones de filtro de la consulta de notas: ciclos (con su estado de cierre), grupos
     * y profesores que el usuario puede consultar, y los estados de resultado.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function filters(Request $request): JsonResponse
    {
        $usuario = $request->user();
        abort_if($usuario->esSoloEstudiante(), 403, 'Un estudiante no puede consultar las notas de otros estudiantes.');

        $grupos = Grupo::with('modulo')
            ->whereHas('ciclos')
            ->when(
                $usuario->esSoloProfesor(),
                fn ($q) => $q->where('profesor_id', $usuario->id),
                fn ($q) => $q->whereIn('sede_id', $usuario->idsSedesAccesibles())
            )
            ->orderBy('nombre')
            ->get();

        $ciclos = Ciclo::whereHas('grupos', fn ($q) => $q->whereIn('grupos.id', $grupos->modelKeys()))
            ->orderByDesc('fecha_inicio')
            ->get();

        $profesores = User::whereIn('id', $grupos->pluck('profesor_id')->filter()->unique())
            ->get()
            ->sortBy('name')
            ->values();

        return response()->json([
            'data' => [
                'ciclos' => $ciclos->map(fn (Ciclo $ciclo) => [
                    'id'             => $ciclo->id,
                    'nombre'         => $ciclo->nombre,
                    'fecha_inicio'   => $ciclo->fecha_inicio?->format('Y-m-d'),
                    'fecha_fin'      => $ciclo->fecha_fin?->format('Y-m-d'),
                    'notas_cerradas' => $ciclo->notasCerradas(),
                ]),
                'grupos' => $grupos->map(fn (Grupo $grupo) => [
                    'id'          => $grupo->id,
                    'nombre'      => $grupo->nombre,
                    'profesor_id' => $grupo->profesor_id,
                    'modulo'      => ['id' => $grupo->modulo->id, 'nombre' => $grupo->modulo->nombre],
                ]),
                'profesores' => $profesores->map(fn (User $profesor) => ['id' => $profesor->id, 'name' => $profesor->name]),
                'estados' => [
                    ['value' => SabanaNotasService::ESTADO_PENDIENTE, 'label' => 'Pendiente'],
                    ['value' => SabanaNotasService::ESTADO_APROBADO,  'label' => 'Aprobado'],
                    ['value' => SabanaNotasService::ESTADO_REPROBADO, 'label' => 'Reprobado'],
                ],
            ],
        ]);
    }

    /**
     * Corta con 403 si el usuario no puede registrar notas en el grupo (B25):
     * solo su profesor o el personal con la sede del grupo asignada.
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
