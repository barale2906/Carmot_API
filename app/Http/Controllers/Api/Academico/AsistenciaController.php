<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\FiltrarAsistenciasRequest;
use App\Http\Requests\Api\Academico\StoreAsistenciaMasivaRequest;
use App\Http\Requests\Api\Academico\StoreAsistenciaRequest;
use App\Http\Requests\Api\Academico\UpdateAsistenciaRequest;
use App\Http\Resources\Api\Academico\AsistenciaResource;
use App\Http\Resources\Api\Academico\ListaAsistenciaResource;
use App\Models\Academico\Asistencia;
use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\AsistenciaConfiguracion;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use App\Services\Academico\AsistenciasExcelService;
use App\Services\Asistencia\RegistroAsistenciaService;
use App\Services\Asistencia\VerificarCumplimientoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AsistenciaController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct(
        private readonly RegistroAsistenciaService $registroAsistencia,
        private readonly VerificarCumplimientoService $cumplimiento,
        private readonly AsistenciasExcelService $asistenciasExcel
    )
    {
        $this->middleware('permission:aca_asistencias')->only(['index', 'filters', 'excel', 'show', 'listaAsistencia', 'reporteEstudiante', 'reporteGrupo']);
        $this->middleware('permission:aca_asistenciaCrear')->only(['store', 'storeMasivo']);
        $this->middleware('permission:aca_asistenciaEditar')->only(['update']);
        $this->middleware('permission:aca_asistenciaInactivar')->only(['destroy', 'restore']);
    }

    /**
     * Listado de asistencias filtrable por grupo, sede, curso, estudiante y fecha de la clase
     * (un día o un rango). Cada registro trae el saldo vencido del estudiante (`en_mora`).
     * Sin `sort_by` ordena por la clase más reciente. A un alumno solo se le listan las suyas.
     *
     * @param FiltrarAsistenciasRequest $request
     * @return JsonResponse
     */
    public function index(FiltrarAsistenciasRequest $request): JsonResponse
    {
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['estudiante', 'claseProgramada', 'grupo.sede', 'ciclo', 'curso', 'modulo'];

        $asistencias = Asistencia::withFilters($request->filtros())
            ->withRelationsAndCounts($relations, false)
            ->when(
                $request->filled('sort_by'),
                fn ($q) => $q->withSorting($request->sort_by, $request->get('sort_direction', 'desc')),
                fn ($q) => $q->ordenadoPorClase()
            )
            ->paginate($request->get('per_page', 15));
        Asistencia::cargarMora($asistencias->getCollection());

        return response()->json([
            'data' => AsistenciaResource::collection($asistencias),
            'meta' => [
                'current_page' => $asistencias->currentPage(),
                'last_page' => $asistencias->lastPage(),
                'per_page' => $asistencias->perPage(),
                'total' => $asistencias->total(),
                'from' => $asistencias->firstItem(),
                'to' => $asistencias->lastItem(),
            ],
        ]);
    }

    /**
     * Opciones de los filtros del listado: sedes del usuario, y cursos y grupos que ya tienen
     * asistencia registrada (de sus sedes; a quien solo es profesor, solo sus grupos).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function filters(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $sedes   = $usuario->sedesAccesibles()->sortBy('nombre')->values();

        $grupos = Grupo::whereIn('id', Asistencia::select('grupo_id'))
            ->when(
                $usuario->esSoloProfesor(),
                fn ($q) => $q->where('profesor_id', $usuario->id),
                fn ($q) => $q->whereIn('sede_id', $sedes->pluck('id'))
            )
            ->with('modulo:id,nombre')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'sede_id', 'modulo_id']);

        $cursos = Curso::whereIn('id', Asistencia::whereIn('grupo_id', $grupos->pluck('id'))->select('curso_id'))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return response()->json([
            'data' => [
                'estados' => collect(Asistencia::ESTADOS)->map(fn ($estado) => ['value' => $estado, 'label' => ucfirst($estado)])->all(),
                'sedes' => $sedes->map(fn ($sede) => ['id' => $sede->id, 'nombre' => $sede->nombre])->all(),
                'cursos' => $cursos,
                'grupos' => $grupos->map(fn ($grupo) => [
                    'id' => $grupo->id,
                    'nombre' => $grupo->nombre,
                    'sede_id' => $grupo->sede_id,
                    'modulo' => $grupo->modulo?->nombre,
                ])->all(),
            ],
        ]);
    }

    /**
     * Descarga en Excel el listado de asistencias con los mismos filtros del listado.
     *
     * @param FiltrarAsistenciasRequest $request
     * @return StreamedResponse
     */
    public function excel(FiltrarAsistenciasRequest $request): StreamedResponse
    {
        $spreadsheet = $this->asistenciasExcel->generar($request->filtros());
        $nombre = 'asistencias-' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Almacena una nueva asistencia individual.
     *
     * @param StoreAsistenciaRequest $request
     * @return JsonResponse
     */
    public function store(StoreAsistenciaRequest $request): JsonResponse
    {
        $this->autorizarGrupo($request, AsistenciaClaseProgramada::findOrFail($request->clase_programada_id)->grupo);

        try {
            DB::beginTransaction();

            $claseProgramada = AsistenciaClaseProgramada::with(['grupo', 'ciclo'])->findOrFail($request->clase_programada_id);

            // Obtener información adicional de la clase programada
            $grupo = $claseProgramada->grupo;
            $ciclo = $claseProgramada->ciclo;

            $asistencia = Asistencia::create([
                'estudiante_id' => $request->estudiante_id,
                'clase_programada_id' => $request->clase_programada_id,
                'grupo_id' => $grupo->id,
                'ciclo_id' => $ciclo->id,
                'modulo_id' => $grupo->modulo_id,
                'curso_id' => $ciclo->curso_id,
                'estado' => $request->estado,
                'justificada' => $request->boolean('justificada'),
                'justificacion' => $request->boolean('justificada') ? $request->justificacion : null,
                'hora_registro' => $request->hora_registro ?? now()->format('H:i:s'),
                'observaciones' => $request->observaciones,
                'registrado_por_id' => auth()->id(),
                'fecha_registro' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Asistencia registrada exitosamente.',
                'data' => new AsistenciaResource($asistencia->load(['estudiante', 'claseProgramada', 'grupo', 'ciclo'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar la asistencia.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registra o corrige la lista de asistencia de una clase.
     *
     * Volver a enviar la lista de la misma clase actualiza los registros en lugar de
     * duplicarlos; la clase queda marcada como dictada.
     *
     * @param StoreAsistenciaMasivaRequest $request
     * @return JsonResponse
     */
    public function storeMasivo(StoreAsistenciaMasivaRequest $request): JsonResponse
    {
        $clase = AsistenciaClaseProgramada::findOrFail($request->clase_programada_id);
        $this->autorizarGrupo($request, $clase->grupo);

        $resultado = $this->registroAsistencia->registrarClase($clase, $request->asistencias, auth()->id());

        return response()->json([
            'message' => "Asistencia registrada: {$resultado['creadas']} nuevas, {$resultado['actualizadas']} actualizadas.",
            'data'    => AsistenciaResource::collection($resultado['asistencias']->load('estudiante')),
            'meta'    => [
                'creadas'      => $resultado['creadas'],
                'actualizadas' => $resultado['actualizadas'],
            ],
        ], 201);
    }

    /**
     * Muestra una asistencia específica. Un alumno solo puede ver las suyas (403 en las ajenas).
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $asistencia = Asistencia::withRelations(['estudiante', 'claseProgramada', 'grupo', 'ciclo', 'modulo', 'curso', 'registradoPor'])
            ->findOrFail($id);

        abort_unless($request->user()->puedeVerEstudiante($asistencia->estudiante_id), 403, 'Solo puede consultar su propia información.');

        return response()->json([
            'data' => new AsistenciaResource($asistencia),
        ]);
    }

    /**
     * Actualiza una asistencia específica.
     *
     * @param UpdateAsistenciaRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateAsistenciaRequest $request, int $id): JsonResponse
    {
        $this->autorizarGrupo($request, Asistencia::findOrFail($id)->grupo);

        try {
            $asistencia = Asistencia::findOrFail($id);

            $asistencia->update($request->datos());

            return response()->json([
                'message' => 'Asistencia actualizada exitosamente.',
                'data' => new AsistenciaResource($asistencia->load(['estudiante', 'claseProgramada', 'grupo', 'ciclo'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la asistencia.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina una asistencia (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $asistencia = Asistencia::findOrFail($id);
            $asistencia->delete();

            return response()->json([
                'message' => 'Asistencia eliminada exitosamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la asistencia.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restaura una asistencia eliminada.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $asistencia = Asistencia::onlyTrashed()->findOrFail($id);
            $asistencia->restore();

            return response()->json([
                'message' => 'Asistencia restaurada exitosamente.',
                'data' => new AsistenciaResource($asistencia->load(['estudiante', 'claseProgramada', 'grupo', 'ciclo'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al restaurar la asistencia.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene la lista de asistencia por grupo: ciclos en curso o en su plazo de cierre
     * (`academico.dias_cierre_ciclo`). Cada estudiante trae su saldo vencido para que quien
     * toma la asistencia decida si entra a clase. Prohibida para alumnos.
     *
     * @param Request $request
     * @param int $grupoId
     * @return JsonResponse
     */
    public function listaAsistencia(Request $request, int $grupoId): JsonResponse
    {
        abort_if($request->user()->esSoloEstudiante(), 403, 'Un estudiante no puede consultar la información de un grupo completo.');

        try {
            $grupo = Grupo::findOrFail($grupoId);

            // Ciclos del grupo en curso o dentro del plazo de cierre (N17)
            $ciclosActivos = Ciclo::whereHas('grupos', function ($query) use ($grupoId) {
                $query->where('grupos.id', $grupoId);
            })
                ->abiertosParaRegistro()
                ->get();

            if ($ciclosActivos->isEmpty()) {
                return response()->json([
                    'message' => 'No hay ciclos en curso ni en periodo de cierre para este grupo.',
                    'data' => new ListaAsistenciaResource([
                        'grupo' => $grupo,
                        'ciclos' => collect([]),
                        'estudiantes' => collect([]),
                        'clases_programadas' => collect([]),
                    ]),
                ]);
            }

            $ciclosIds = $ciclosActivos->pluck('id');

            // Matrículas vigentes de esos ciclos; los aplazados no asisten mientras dure el aplazamiento.
            $matriculas = Matricula::whereIn('ciclo_id', $ciclosIds)
                ->where('status', 1)
                ->where('estado_academico', Matricula::ESTADO_ACTIVO)
                ->with(['estudiante', 'ciclo'])
                ->get();

            $saldosVencidos = Cartera::saldosVencidosPorEstudiante($matriculas->pluck('estudiante_id')->unique()->values()->all());

            // Extraer estudiantes únicos con información del ciclo y su mora
            $estudiantes = $matriculas->map(function ($matricula) use ($saldosVencidos) {
                $estudiante = $matricula->estudiante;
                $estudiante->ciclo_id = $matricula->ciclo_id;
                $estudiante->ciclo_nombre = $matricula->ciclo->nombre;
                $estudiante->saldo_vencido = $saldosVencidos->get($estudiante->id, 0.0);
                return $estudiante;
            })->unique('id');

            // Obtener clases programadas del grupo
            $clasesProgramadas = AsistenciaClaseProgramada::where('grupo_id', $grupoId)
                ->whereIn('ciclo_id', $ciclosIds)
                ->with(['grupo', 'ciclo'])
                ->orderBy('fecha_clase')
                ->orderBy('hora_inicio')
                ->get();

            return response()->json([
                'data' => new ListaAsistenciaResource([
                    'grupo' => $grupo,
                    'ciclos' => $ciclosActivos,
                    'estudiantes' => $estudiantes,
                    'clases_programadas' => $clasesProgramadas,
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la lista de asistencia.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Genera reporte de asistencia por estudiante. Un alumno solo puede pedir el suyo.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function reporteEstudiante(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->puedeVerEstudiante($id), 403, 'Solo puede consultar su propia información.');

        try {
            $estudiante = \App\Models\User::findOrFail($id);
            $grupoId = $request->get('grupo_id');
            $cicloId = $request->get('ciclo_id');

            $query = Asistencia::where('estudiante_id', $id);

            if ($grupoId) {
                $query->where('grupo_id', $grupoId);
            }

            if ($cicloId) {
                $query->where('ciclo_id', $cicloId);
            }

            $asistencias = $query->with(['claseProgramada', 'grupo', 'ciclo', 'modulo', 'curso'])
                ->orderBy('fecha_registro', 'desc')
                ->get();

            $totalClases = $asistencias->count();
            $presentes = $asistencias->where('estado', 'presente')->count();
            $ausentes = $asistencias->where('estado', 'ausente')->count();
            $justificadas = $asistencias->where('justificada', true)->count();
            $tardanzas = $asistencias->where('estado', 'tardanza')->count();

            $horasTotales = $asistencias->sum(function ($asistencia) {
                return $asistencia->claseProgramada->duracion_horas ?? 0;
            });

            // La justificación no borra la falla: solo cuentan presente y tardanza
            $horasAsistidas = $asistencias->filter(function ($asistencia) {
                return $asistencia->esPresente();
            })->sum(function ($asistencia) {
                return $asistencia->claseProgramada->duracion_horas ?? 0;
            });

            $porcentaje = $horasTotales > 0 ? ($horasAsistidas / $horasTotales) * 100 : 0;

            return response()->json([
                'data' => [
                    'estudiante' => [
                        'id' => $estudiante->id,
                        'name' => $estudiante->name,
                        'email' => $estudiante->email,
                        'documento' => $estudiante->documento,
                    ],
                    'resumen' => [
                        'total_clases' => $totalClases,
                        'presentes' => $presentes,
                        'ausentes' => $ausentes,
                        'justificadas' => $justificadas,
                        'tardanzas' => $tardanzas,
                        'horas_totales' => round($horasTotales, 2),
                        'horas_asistidas' => round($horasAsistidas, 2),
                        'porcentaje_asistencia' => round($porcentaje, 2),
                    ],
                    'asistencias' => AsistenciaResource::collection($asistencias),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al generar el reporte.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Genera reporte de asistencia por grupo con el cumplimiento de la regla de asistencia
     * de cada estudiante (porcentaje mínimo y alerta de pérdida por fallas). Es informativo:
     * si aprueba o no lo define la nota del profesor. Prohibido para alumnos.
     *
     * @param Request $request
     * @param int $grupoId
     * @return JsonResponse
     */
    public function reporteGrupo(Request $request, int $grupoId): JsonResponse
    {
        abort_if($request->user()->esSoloEstudiante(), 403, 'Un estudiante no puede consultar la información de un grupo completo.');

        try {
            $grupo = Grupo::with('modulo')->findOrFail($grupoId);
            $cicloId = $request->get('ciclo_id');

            $query = Asistencia::where('grupo_id', $grupoId);

            if ($cicloId) {
                $query->where('ciclo_id', $cicloId);
            }

            $asistencias = $query->with(['estudiante', 'claseProgramada', 'ciclo'])
                ->get();

            // Agrupar por estudiante; el cumplimiento usa la regla del curso y módulo del grupo (N19)
            $reportePorEstudiante = $asistencias->groupBy('estudiante_id')->map(function ($asistenciasEstudiante) use ($grupo) {
                $estudiante    = $asistenciasEstudiante->first()->estudiante;
                $configuracion = AsistenciaConfiguracion::obtenerPara($asistenciasEstudiante->first()->curso_id, $grupo->modulo_id);
                $cumplimiento  = $this->cumplimiento->evaluar($asistenciasEstudiante, $configuracion);

                return [
                    'estudiante' => [
                        'id' => $estudiante->id,
                        'name' => $estudiante->name,
                        'email' => $estudiante->email,
                    ],
                    'total_clases' => $asistenciasEstudiante->count(),
                    'presentes' => $asistenciasEstudiante->where('estado', 'presente')->count(),
                    'ausentes' => $asistenciasEstudiante->where('estado', 'ausente')->count(),
                    'justificadas' => $asistenciasEstudiante->where('justificada', true)->count(),
                    'horas_totales' => $cumplimiento['horas_totales'],
                    'horas_asistidas' => $cumplimiento['horas_asistidas'],
                    'porcentaje_asistencia' => $cumplimiento['porcentaje'],
                    'porcentaje_minimo' => $cumplimiento['porcentaje_minimo'],
                    'cumple_minimo' => $cumplimiento['cumple'],
                    'pierde_por_fallas' => $cumplimiento['pierde_por_fallas'],
                ];
            })->values();

            return response()->json([
                'data' => [
                    'grupo' => [
                        'id' => $grupo->id,
                        'nombre' => $grupo->nombre,
                        'modulo' => $grupo->modulo->nombre ?? null,
                    ],
                    'resumen_grupal' => [
                        'total_estudiantes' => $reportePorEstudiante->count(),
                        // Clases distintas con asistencia, no registros (uno por estudiante, B24)
                        'total_clases' => $asistencias->pluck('clase_programada_id')->unique()->count(),
                    ],
                    'estudiantes' => $reportePorEstudiante,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al generar el reporte.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Corta con 403 si el usuario no puede registrar asistencia en el grupo (B22):
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
}
