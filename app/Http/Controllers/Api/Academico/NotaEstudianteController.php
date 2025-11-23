<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreNotaEstudianteRequest;
use App\Http\Requests\Api\Academico\StoreNotaMasivaRequest;
use App\Http\Requests\Api\Academico\UpdateNotaEstudianteRequest;
use App\Http\Resources\Api\Academico\NotaEstudianteResource;
use App\Http\Resources\Api\Academico\SabanaNotasEstudianteResource;
use App\Http\Resources\Api\Academico\SabanaNotasGrupalResource;
use App\Models\Academico\Ciclo;
use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\Grupo;
use App\Models\Academico\Matricula;
use App\Models\Academico\Modulo;
use App\Models\Academico\NotaEstudiante;
use App\Models\Academico\TipoNotaEsquema;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotaEstudianteController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_notas')->only(['index', 'show', 'sabanaEstudiante', 'sabanaGrupal', 'calcularNotaFinal']);
        $this->middleware('permission:aca_notaCrear')->only(['store', 'storeMasivo']);
        $this->middleware('permission:aca_notaEditar')->only(['update']);
        $this->middleware('permission:aca_notaInactivar')->only(['destroy', 'restore']);
    }

    /**
     * Muestra una lista de notas de estudiantes.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'estudiante_id', 'grupo_id', 'modulo_id', 'esquema_calificacion_id',
            'tipo_nota_esquema_id', 'status', 'include_trashed', 'only_trashed'
        ]);

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
     * Elimina la nota especificada (soft delete).
     *
     * @param NotaEstudiante $notaEstudiante
     * @return JsonResponse
     */
    public function destroy(NotaEstudiante $notaEstudiante): JsonResponse
    {
        $notaEstudiante->delete();

        return response()->json([
            'message' => 'Nota eliminada exitosamente.',
        ]);
    }

    /**
     * Restaura una nota eliminada (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $nota = NotaEstudiante::onlyTrashed()->findOrFail($id);
        $nota->restore();

        return response()->json([
            'message' => 'Nota restaurada exitosamente.',
            'data' => new NotaEstudianteResource($nota->load(['estudiante', 'grupo', 'modulo', 'tipoNotaEsquema'])),
        ]);
    }

    /**
     * Calcula la nota final de un estudiante en un módulo.
     *
     * @param Request $request
     * @param int $estudianteId
     * @param int $moduloId
     * @param int|null $grupoId
     * @return JsonResponse
     */
    public function calcularNotaFinal(Request $request, int $estudianteId, int $moduloId, ?int $grupoId = null): JsonResponse
    {
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
     * Obtiene la sabana de notas de un estudiante (todos sus módulos).
     *
     * @param Request $request
     * @param int $estudianteId
     * @return JsonResponse
     */
    public function sabanaEstudiante(Request $request, int $estudianteId): JsonResponse
    {
        try {
            $estudiante = User::findOrFail($estudianteId);
            $cicloId = $request->get('ciclo_id');
            $cursoId = $request->get('curso_id');

            // Obtener matrícula activa del estudiante
            $matriculaQuery = Matricula::where('estudiante_id', $estudianteId)
                ->where('status', 1);

            if ($cicloId) {
                $matriculaQuery->where('ciclo_id', $cicloId);
            }

            if ($cursoId) {
                $matriculaQuery->where('curso_id', $cursoId);
            }

            $matricula = $matriculaQuery->with(['curso', 'ciclo'])->first();

            if (!$matricula) {
                return response()->json([
                    'message' => 'No se encontró una matrícula activa para este estudiante.',
                ], 404);
            }

            // Obtener grupos del ciclo
            $ciclo = $matricula->ciclo;
            $grupos = $ciclo->grupos()->with('modulo')->get();

            $modulosData = [];
            $sumaNotasFinales = 0;
            $modulosCompletos = 0;

            foreach ($grupos as $grupo) {
                $modulo = $grupo->modulo;
                $moduloId = $modulo->id;

                // Obtener esquema activo para este módulo/grupo
                $esquema = EsquemaCalificacion::activoParaModuloGrupo($moduloId, $grupo->id);

                if (!$esquema) {
                    continue;
                }

                // Obtener todas las notas del estudiante en este módulo/grupo
                $notas = NotaEstudiante::where('estudiante_id', $estudianteId)
                    ->where('grupo_id', $grupo->id)
                    ->where('modulo_id', $moduloId)
                    ->where('esquema_calificacion_id', $esquema->id)
                    ->where('status', 1)
                    ->with('tipoNotaEsquema')
                    ->get();

                // Calcular nota final
                $notaFinal = $notas->sum('nota_ponderada');

                // Obtener tipos de nota del esquema
                $tiposNota = $esquema->tiposNota;
                $tiposConNota = $notas->pluck('tipo_nota_esquema_id')->toArray();

                // Construir datos de tipos de nota
                $tiposNotaData = $tiposNota->map(function ($tipo) use ($notas) {
                    $nota = $notas->firstWhere('tipo_nota_esquema_id', $tipo->id);
                    return [
                        'id' => $tipo->id,
                        'nombre_tipo' => $tipo->nombre_tipo,
                        'peso' => (float) $tipo->peso,
                        'nota' => $nota ? (float) $nota->nota : null,
                        'nota_ponderada' => $nota ? (float) $nota->nota_ponderada : null,
                        'pendiente' => !$nota,
                    ];
                });

                $completo = $tiposConNota === $tiposNota->pluck('id')->toArray();

                if ($completo) {
                    $modulosCompletos++;
                    $sumaNotasFinales += $notaFinal;
                }

                $modulosData[] = [
                    'modulo' => [
                        'id' => $modulo->id,
                        'nombre' => $modulo->nombre,
                    ],
                    'grupo' => [
                        'id' => $grupo->id,
                        'nombre' => $grupo->nombre,
                    ],
                    'esquema_calificacion' => [
                        'id' => $esquema->id,
                        'nombre_esquema' => $esquema->nombre_esquema,
                    ],
                    'tipos_nota' => $tiposNotaData,
                    'nota_final' => round($notaFinal, 2),
                    'completo' => $completo,
                ];
            }

            $promedioGeneral = $modulosCompletos > 0 ? round($sumaNotasFinales / $modulosCompletos, 2) : null;

            $data = [
                'estudiante' => [
                    'id' => $estudiante->id,
                    'name' => $estudiante->name,
                    'email' => $estudiante->email,
                    'documento' => $estudiante->documento,
                ],
                'curso' => [
                    'id' => $matricula->curso->id,
                    'nombre' => $matricula->curso->nombre,
                ],
                'ciclo' => [
                    'id' => $matricula->ciclo->id,
                    'nombre' => $matricula->ciclo->nombre,
                ],
                'modulos' => $modulosData,
                'promedio_general' => $promedioGeneral,
                'total_modulos' => count($modulosData),
                'modulos_completos' => $modulosCompletos,
            ];

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
     * Obtiene la sabana de notas grupal (todos los estudiantes de un grupo en un módulo).
     *
     * @param Request $request
     * @param int $grupoId
     * @param int $moduloId
     * @return JsonResponse
     */
    public function sabanaGrupal(Request $request, int $grupoId, int $moduloId): JsonResponse
    {
        try {
            $grupo = Grupo::with('modulo')->findOrFail($grupoId);
            $modulo = Modulo::findOrFail($moduloId);

            // Verificar que el módulo pertenezca al grupo
            if ($grupo->modulo_id !== $moduloId) {
                return response()->json([
                    'message' => 'El módulo no pertenece al grupo especificado.',
                ], 422);
            }

            // Obtener esquema activo
            $esquema = EsquemaCalificacion::activoParaModuloGrupo($moduloId, $grupoId);

            if (!$esquema) {
                return response()->json([
                    'message' => 'No se encontró un esquema activo para este módulo y grupo.',
                ], 404);
            }

            // Obtener estudiantes del grupo (a través de matrículas del ciclo)
            // Primero obtener los ciclos del grupo
            $ciclos = $grupo->ciclos()->pluck('ciclos.id');

            // Obtener matrículas activas de esos ciclos
            $matriculas = Matricula::whereIn('ciclo_id', $ciclos)
                ->where('status', 1)
                ->with('estudiante')
                ->get();

            $estudiantesIds = $matriculas->pluck('estudiante_id')->unique();

            // Obtener todas las notas de estos estudiantes en este módulo/grupo
            $notas = NotaEstudiante::whereIn('estudiante_id', $estudiantesIds)
                ->where('grupo_id', $grupoId)
                ->where('modulo_id', $moduloId)
                ->where('esquema_calificacion_id', $esquema->id)
                ->where('status', 1)
                ->with(['estudiante', 'tipoNotaEsquema'])
                ->get();

            // Obtener tipos de nota del esquema
            $tiposNota = $esquema->tiposNota->sortBy('orden');

            // Construir datos de estudiantes
            $estudiantesData = [];

            foreach ($estudiantesIds as $estudianteId) {
                $estudiante = $matriculas->firstWhere('estudiante_id', $estudianteId)->estudiante;
                $notasEstudiante = $notas->where('estudiante_id', $estudianteId);

                // Construir datos de tipos de nota para este estudiante
                $tiposNotaData = $tiposNota->map(function ($tipo) use ($notasEstudiante) {
                    $nota = $notasEstudiante->firstWhere('tipo_nota_esquema_id', $tipo->id);
                    return [
                        'id' => $tipo->id,
                        'nombre_tipo' => $tipo->nombre_tipo,
                        'peso' => (float) $tipo->peso,
                        'nota' => $nota ? (float) $nota->nota : null,
                        'nota_ponderada' => $nota ? (float) $nota->nota_ponderada : null,
                        'pendiente' => !$nota,
                    ];
                });

                // Calcular nota final
                $notaFinal = $notasEstudiante->sum('nota_ponderada');

                $estudiantesData[] = [
                    'estudiante' => [
                        'id' => $estudiante->id,
                        'name' => $estudiante->name,
                        'email' => $estudiante->email,
                        'documento' => $estudiante->documento,
                    ],
                    'tipos_nota' => $tiposNotaData,
                    'nota_final' => round($notaFinal, 2),
                    'completo' => $notasEstudiante->count() === $tiposNota->count(),
                ];
            }

            $data = [
                'grupo' => [
                    'id' => $grupo->id,
                    'nombre' => $grupo->nombre,
                ],
                'modulo' => [
                    'id' => $modulo->id,
                    'nombre' => $modulo->nombre,
                ],
                'esquema_calificacion' => $esquema->load(['modulo', 'grupo', 'profesor', 'tiposNota']),
                'estudiantes' => $estudiantesData,
                'total_estudiantes' => count($estudiantesData),
                'estudiantes_con_notas' => collect($estudiantesData)->filter(fn($e) => $e['completo'])->count(),
            ];

            return response()->json([
                'data' => new SabanaNotasGrupalResource($data),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al generar la sabana de notas grupal.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
