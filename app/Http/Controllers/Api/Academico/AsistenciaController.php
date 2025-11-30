<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreAsistenciaMasivaRequest;
use App\Http\Requests\Api\Academico\StoreAsistenciaRequest;
use App\Http\Requests\Api\Academico\UpdateAsistenciaRequest;
use App\Http\Resources\Api\Academico\AsistenciaResource;
use App\Http\Resources\Api\Academico\ListaAsistenciaResource;
use App\Models\Academico\Asistencia;
use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsistenciaController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_asistencias')->only(['index', 'show', 'listaAsistencia', 'reporteEstudiante', 'reporteGrupo']);
        $this->middleware('permission:aca_asistenciaCrear')->only(['store', 'storeMasivo']);
        $this->middleware('permission:aca_asistenciaEditar')->only(['update']);
        $this->middleware('permission:aca_asistenciaInactivar')->only(['destroy', 'restore']);
    }

    /**
     * Muestra una lista de asistencias con filtros.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'estudiante_id', 'grupo_id', 'ciclo_id', 'curso_id', 'modulo_id',
            'clase_programada_id', 'estado', 'fecha_registro', 'include_trashed', 'only_trashed'
        ]);

        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['estudiante', 'claseProgramada', 'grupo', 'ciclo'];

        $asistencias = Asistencia::withFilters($filters)
            ->withRelationsAndCounts($relations, false)
            ->withSorting($request->get('sort_by', 'fecha_registro'), $request->get('sort_direction', 'desc'))
            ->paginate($request->get('per_page', 15));

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
     * Almacena una nueva asistencia individual.
     *
     * @param StoreAsistenciaRequest $request
     * @return JsonResponse
     */
    public function store(StoreAsistenciaRequest $request): JsonResponse
    {
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
     * Almacena asistencias masivas.
     *
     * @param StoreAsistenciaMasivaRequest $request
     * @return JsonResponse
     */
    public function storeMasivo(StoreAsistenciaMasivaRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $claseProgramada = AsistenciaClaseProgramada::with(['grupo', 'ciclo'])->findOrFail($request->clase_programada_id);
            $grupo = $claseProgramada->grupo;
            $ciclo = $claseProgramada->ciclo;

            $asistencias = [];
            foreach ($request->asistencias as $asistenciaData) {
                $asistencia = Asistencia::create([
                    'estudiante_id' => $asistenciaData['estudiante_id'],
                    'clase_programada_id' => $request->clase_programada_id,
                    'grupo_id' => $grupo->id,
                    'ciclo_id' => $ciclo->id,
                    'modulo_id' => $grupo->modulo_id,
                    'curso_id' => $ciclo->curso_id,
                    'estado' => $asistenciaData['estado'],
                    'hora_registro' => now()->format('H:i:s'),
                    'observaciones' => $asistenciaData['observaciones'] ?? null,
                    'registrado_por_id' => auth()->id(),
                    'fecha_registro' => now(),
                ]);

                $asistencias[] = $asistencia;
            }

            DB::commit();

            return response()->json([
                'message' => count($asistencias) . ' asistencias registradas exitosamente.',
                'data' => AsistenciaResource::collection(collect($asistencias)->load(['estudiante', 'claseProgramada', 'grupo', 'ciclo'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar las asistencias.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra una asistencia específica.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $asistencia = Asistencia::withRelations(['estudiante', 'claseProgramada', 'grupo', 'ciclo', 'modulo', 'curso', 'registradoPor'])
            ->findOrFail($id);

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
        try {
            $asistencia = Asistencia::findOrFail($id);

            $asistencia->update($request->validated());

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
     * Obtiene la lista de asistencia por grupo (ciclos activos).
     *
     * @param Request $request
     * @param int $grupoId
     * @return JsonResponse
     */
    public function listaAsistencia(Request $request, int $grupoId): JsonResponse
    {
        try {
            $grupo = Grupo::findOrFail($grupoId);

            // Obtener ciclos activos que contienen este grupo
            $ciclosActivos = Ciclo::whereHas('grupos', function ($query) use ($grupoId) {
                $query->where('grupos.id', $grupoId);
            })
                ->activosVigentes()
                ->get();

            if ($ciclosActivos->isEmpty()) {
                return response()->json([
                    'message' => 'No se encontraron ciclos activos para este grupo.',
                    'data' => new ListaAsistenciaResource([
                        'grupo' => $grupo,
                        'ciclos' => collect([]),
                        'estudiantes' => collect([]),
                        'clases_programadas' => collect([]),
                    ]),
                ]);
            }

            $ciclosIds = $ciclosActivos->pluck('id');

            // Obtener matrículas activas de esos ciclos
            $matriculas = Matricula::whereIn('ciclo_id', $ciclosIds)
                ->where('status', 1)
                ->with(['estudiante', 'ciclo'])
                ->get();

            // Extraer estudiantes únicos con información del ciclo
            $estudiantes = $matriculas->map(function ($matricula) {
                $estudiante = $matricula->estudiante;
                $estudiante->ciclo_id = $matricula->ciclo_id;
                $estudiante->ciclo_nombre = $matricula->ciclo->nombre;
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
     * Genera reporte de asistencia por estudiante.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function reporteEstudiante(Request $request, int $id): JsonResponse
    {
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
            $justificadas = $asistencias->where('estado', 'justificado')->count();
            $tardanzas = $asistencias->where('estado', 'tardanza')->count();

            $horasTotales = $asistencias->sum(function ($asistencia) {
                return $asistencia->claseProgramada->duracion_horas ?? 0;
            });

            $horasAsistidas = $asistencias->filter(function ($asistencia) {
                return in_array($asistencia->estado, ['presente', 'tardanza', 'justificado']);
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
     * Genera reporte de asistencia por grupo.
     *
     * @param Request $request
     * @param int $grupoId
     * @return JsonResponse
     */
    public function reporteGrupo(Request $request, int $grupoId): JsonResponse
    {
        try {
            $grupo = Grupo::with('modulo')->findOrFail($grupoId);
            $cicloId = $request->get('ciclo_id');

            $query = Asistencia::where('grupo_id', $grupoId);

            if ($cicloId) {
                $query->where('ciclo_id', $cicloId);
            }

            $asistencias = $query->with(['estudiante', 'claseProgramada', 'ciclo'])
                ->get();

            // Agrupar por estudiante
            $reportePorEstudiante = $asistencias->groupBy('estudiante_id')->map(function ($asistenciasEstudiante) {
                $estudiante = $asistenciasEstudiante->first()->estudiante;
                $totalClases = $asistenciasEstudiante->count();
                $presentes = $asistenciasEstudiante->where('estado', 'presente')->count();
                $horasTotales = $asistenciasEstudiante->sum(function ($a) {
                    return $a->claseProgramada->duracion_horas ?? 0;
                });
                $horasAsistidas = $asistenciasEstudiante->filter(function ($a) {
                    return in_array($a->estado, ['presente', 'tardanza', 'justificado']);
                })->sum(function ($a) {
                    return $a->claseProgramada->duracion_horas ?? 0;
                });
                $porcentaje = $horasTotales > 0 ? ($horasAsistidas / $horasTotales) * 100 : 0;

                return [
                    'estudiante' => [
                        'id' => $estudiante->id,
                        'name' => $estudiante->name,
                        'email' => $estudiante->email,
                    ],
                    'total_clases' => $totalClases,
                    'presentes' => $presentes,
                    'horas_totales' => round($horasTotales, 2),
                    'horas_asistidas' => round($horasAsistidas, 2),
                    'porcentaje_asistencia' => round($porcentaje, 2),
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
                        'total_clases' => $asistencias->count(),
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
}
