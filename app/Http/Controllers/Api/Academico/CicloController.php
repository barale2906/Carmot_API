<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreCicloRequest;
use App\Http\Requests\Api\Academico\UpdateCicloRequest;
use App\Http\Resources\Api\Academico\CicloResource;
use App\Models\Academico\Ciclo;
use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CicloController extends Controller
{
    use HasActiveStatus;

    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_ciclos')->only(['index', 'show', 'filters', 'statistics']);
        $this->middleware('permission:aca_cicloCrear')->only(['store']);
        $this->middleware('permission:aca_cicloEditar')->only(['update']);
        $this->middleware('permission:aca_cicloInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista de los ciclos.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only([
            'search', 'status', 'sede_id', 'curso_id', 'include_trashed', 'only_trashed'
        ]);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sede', 'curso', 'grupos'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (
            str_contains($request->with, 'sede') ||
            str_contains($request->with, 'curso') ||
            str_contains($request->with, 'grupos')
        );

        // Construir query usando scopes
        $ciclos = Ciclo::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CicloResource::collection($ciclos),
            'meta' => [
                'current_page' => $ciclos->currentPage(),
                'last_page' => $ciclos->lastPage(),
                'per_page' => $ciclos->perPage(),
                'total' => $ciclos->total(),
                'from' => $ciclos->firstItem(),
                'to' => $ciclos->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena un nuevo ciclo en la base de datos.
     *
     * @param StoreCicloRequest $request
     * @return JsonResponse
     */
    public function store(StoreCicloRequest $request): JsonResponse
    {
        $ciclo = Ciclo::create([
            'sede_id' => $request->sede_id,
            'curso_id' => $request->curso_id,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
            'fecha_fin_automatica' => $request->fecha_fin_automatica ?? true,
            'status' => $request->status ?? 1, // Por defecto estado "Activo"
        ]);

        // Asignar grupos al ciclo si se proporcionan
        if ($request->has('grupos') && is_array($request->grupos)) {
            // Si se proporciona orden específico
            if ($request->has('con_orden') && $request->con_orden) {
                $ciclo->asignarGruposConOrden($request->grupos);
            } else {
                // Asignar con orden automático
                $gruposConOrden = [];
                foreach ($request->grupos as $index => $grupoId) {
                    $gruposConOrden[] = [
                        'grupo_id' => $grupoId,
                        'orden' => $index + 1
                    ];
                }
                $ciclo->asignarGruposConOrden($gruposConOrden);
            }

            // Si el cálculo automático está habilitado, calcular fecha de fin
            if ($ciclo->fecha_fin_automatica) {
                $ciclo->actualizarFechaFin();
                $ciclo->save();
            }
        }

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Ciclo creado exitosamente.',
            'data' => new CicloResource($ciclo),
        ], 201);
    }

    /**
     * Muestra el ciclo especificado.
     *
     * @param Request $request
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function show(Request $request, Ciclo $ciclo): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sede', 'curso', 'grupos'];

        // Cargar relaciones y contadores
        $ciclo->load($relations);
        $ciclo->loadCount(['sede', 'curso', 'grupos']);

        return response()->json([
            'data' => new CicloResource($ciclo),
        ]);
    }

    /**
     * Actualiza el ciclo especificado en la base de datos.
     *
     * @param UpdateCicloRequest $request
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function update(UpdateCicloRequest $request, Ciclo $ciclo): JsonResponse
    {
        $ciclo->update($request->only([
            'sede_id',
            'curso_id',
            'nombre',
            'descripcion',
            'fecha_inicio',
            'fecha_fin',
            'fecha_fin_automatica',
            'status',
        ]));

        // Actualizar grupos asignados al ciclo si se proporcionan
        if ($request->has('grupos')) {
            if (is_array($request->grupos)) {
                $ciclo->grupos()->sync($request->grupos);
            } else {
                // Si se envía null o array vacío, desasignar todos los grupos
                $ciclo->grupos()->detach();
            }

            // Si el cálculo automático está habilitado, recalcular fecha de fin
            if ($ciclo->fecha_fin_automatica) {
                $ciclo->actualizarFechaFin();
                $ciclo->save();
            }
        }

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Ciclo actualizado exitosamente.',
            'data' => new CicloResource($ciclo),
        ]);
    }

    /**
     * Elimina el ciclo especificado de la base de datos (soft delete).
     *
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function destroy(Ciclo $ciclo): JsonResponse
    {
        // Verificar si tiene grupos asociados
        if ($ciclo->grupos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el ciclo porque tiene grupos asociados.',
            ], 422);
        }

        $ciclo->delete(); // Soft delete

        return response()->json([
            'message' => 'Ciclo eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un ciclo eliminado (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $ciclo = Ciclo::onlyTrashed()->findOrFail($id);
        $ciclo->restore();

        return response()->json([
            'message' => 'Ciclo restaurado exitosamente.',
            'data' => new CicloResource($ciclo->load(['sede', 'curso', 'grupos'])),
        ]);
    }

    /**
     * Elimina permanentemente un ciclo.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $ciclo = Ciclo::onlyTrashed()->findOrFail($id);

        // Verificar si tiene grupos asociados
        if ($ciclo->grupos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el ciclo porque tiene grupos asociados.',
            ], 422);
        }

        $ciclo->forceDelete();

        return response()->json([
            'message' => 'Ciclo eliminado permanentemente.',
        ]);
    }

    /**
     * Obtiene solo los ciclos eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only([
            'search', 'status', 'sede_id', 'curso_id'
        ]);
        $filters['only_trashed'] = true;

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sede', 'curso', 'grupos'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (
            str_contains($request->with, 'sede') ||
            str_contains($request->with, 'curso') ||
            str_contains($request->with, 'grupos')
        );

        // Construir query usando scopes (solo eliminados)
        $ciclos = Ciclo::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => CicloResource::collection($ciclos),
            'meta' => [
                'current_page' => $ciclos->currentPage(),
                'last_page' => $ciclos->lastPage(),
                'per_page' => $ciclos->perPage(),
                'total' => $ciclos->total(),
                'from' => $ciclos->firstItem(),
                'to' => $ciclos->lastItem(),
            ],
        ]);
    }

    /**
     * Obtiene las opciones de filtros disponibles.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        $sedes = \App\Models\Configuracion\Sede::select('id', 'nombre')->get();
        $cursos = \App\Models\Academico\Curso::select('id', 'nombre')->get();

        return response()->json([
            'data' => [
                'status_options' => self::getActiveStatusOptions(),
                'sedes' => $sedes,
                'cursos' => $cursos,
            ],
        ]);
    }


    /**
     * Obtiene estadísticas de ciclos.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Ciclo::count(),
                'activos' => Ciclo::whereNull('deleted_at')->count(),
                'eliminados' => Ciclo::onlyTrashed()->count(),
            ],
            'por_status' => [
                'activos' => Ciclo::where('status', 1)->count(),
                'inactivos' => Ciclo::where('status', 0)->count(),
            ],
            'por_sede' => Ciclo::with('sede')
                ->selectRaw('sede_id, COUNT(*) as total')
                ->groupBy('sede_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'sede' => $item->sede->nombre ?? 'Sin sede',
                        'total' => $item->total
                    ];
                }),
            'por_curso' => Ciclo::with('curso')
                ->selectRaw('curso_id, COUNT(*) as total')
                ->groupBy('curso_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'curso' => $item->curso->nombre ?? 'Sin curso',
                        'total' => $item->total
                    ];
                }),
            'con_grupos' => Ciclo::whereHas('grupos')->count(),
            'sin_grupos' => Ciclo::whereDoesntHave('grupos')->count(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Asigna grupos a un ciclo.
     *
     * @param Request $request
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function asignarGrupos(Request $request, Ciclo $ciclo): JsonResponse
    {
        $request->validate([
            'grupos' => 'required|array',
            'grupos.*' => 'exists:grupos,id'
        ]);

        // Si se proporciona orden específico
        if ($request->has('con_orden') && $request->con_orden) {
            $request->validate([
                'grupos' => 'required|array',
                'grupos.*.grupo_id' => 'required|exists:grupos,id',
                'grupos.*.orden' => 'required|integer|min:1'
            ]);

            $ciclo->asignarGruposConOrden($request->grupos);
        } else {
            // Asignar con orden automático
            $gruposConOrden = [];
            $siguienteOrden = $ciclo->getSiguienteOrden();

            foreach ($request->grupos as $grupoId) {
                $gruposConOrden[] = [
                    'grupo_id' => $grupoId,
                    'orden' => $siguienteOrden++
                ];
            }

            $ciclo->asignarGruposConOrden($gruposConOrden);
        }

        // Si el cálculo automático está habilitado, recalcular fecha de fin
        if ($ciclo->fecha_fin_automatica) {
            $ciclo->actualizarFechaFin();
            $ciclo->save();
        }

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Grupos asignados exitosamente.',
            'data' => new CicloResource($ciclo),
        ]);
    }

    /**
     * Desasigna un grupo de un ciclo.
     *
     * @param Request $request
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function desasignarGrupo(Request $request, Ciclo $ciclo): JsonResponse
    {
        $request->validate([
            'grupo_id' => 'required|exists:grupos,id'
        ]);

        $ciclo->grupos()->detach($request->grupo_id);

        // Si el cálculo automático está habilitado, recalcular fecha de fin
        if ($ciclo->fecha_fin_automatica) {
            $ciclo->actualizarFechaFin();
            $ciclo->save();
        }

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Grupo desasignado exitosamente.',
            'data' => new CicloResource($ciclo),
        ]);
    }

    /**
     * Calcula y actualiza la fecha de fin del ciclo.
     *
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function calcularFechaFin(Ciclo $ciclo): JsonResponse
    {
        $fechaFinCalculada = $ciclo->calcularFechaFin();

        if (!$fechaFinCalculada) {
            return response()->json([
                'message' => 'No se pudo calcular la fecha de fin. Verifique que el ciclo tenga fecha de inicio y grupos con horarios configurados.',
            ], 422);
        }

        $ciclo->fecha_fin = $fechaFinCalculada;
        $ciclo->duracion_dias = $ciclo->fecha_inicio->diffInDays($fechaFinCalculada);
        $ciclo->save();

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Fecha de fin calculada exitosamente.',
            'data' => new CicloResource($ciclo),
        ]);
    }

    /**
     * Obtiene información detallada del cálculo de fechas del ciclo.
     *
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function informacionCalculo(Ciclo $ciclo): JsonResponse
    {
        $ciclo->load(['grupos.modulo', 'grupos.horarios']);

        $informacion = [
            'ciclo' => [
                'id' => $ciclo->id,
                'nombre' => $ciclo->nombre,
                'fecha_inicio' => $ciclo->fecha_inicio,
                'fecha_fin' => $ciclo->fecha_fin,
                'fecha_fin_automatica' => $ciclo->fecha_fin_automatica,
                'duracion_dias' => $ciclo->duracion_dias,
            ],
            'grupos' => $ciclo->grupos->map(function ($grupo) {
                return [
                    'id' => $grupo->id,
                    'nombre' => $grupo->nombre,
                    'modulo' => [
                        'nombre' => $grupo->modulo->nombre ?? 'Sin módulo',
                        'duracion' => $grupo->modulo->duracion ?? 0,
                    ],
                    'horarios' => $grupo->horarios->map(function ($horario) {
                        return [
                            'dia' => $horario->dia,
                            'hora' => $horario->hora,
                            'duracion_horas' => $horario->duracion_horas,
                        ];
                    }),
                    'total_horas_semana' => $grupo->getTotalHorasSemanaAttribute(),
                ];
            }),
            'calculos' => [
                'total_horas' => $ciclo->getTotalHorasAttribute(),
                'horas_por_semana' => $ciclo->getHorasPorSemanaAttribute(),
                'semanas_estimadas' => $ciclo->getHorasPorSemanaAttribute() > 0
                    ? ceil($ciclo->getTotalHorasAttribute() / $ciclo->getHorasPorSemanaAttribute())
                    : 0,
                'fecha_fin_calculada' => $ciclo->calcularFechaFin(),
            ],
            'estado' => [
                'en_curso' => $ciclo->getEnCursoAttribute(),
                'finalizado' => $ciclo->getFinalizadoAttribute(),
                'por_iniciar' => $ciclo->getPorIniciarAttribute(),
            ]
        ];

        return response()->json([
            'data' => $informacion,
        ]);
    }

    /**
     * Actualiza el orden de un grupo específico en el ciclo.
     *
     * @param Request $request
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function actualizarOrdenGrupo(Request $request, Ciclo $ciclo): JsonResponse
    {
        $request->validate([
            'grupo_id' => 'required|exists:grupos,id',
            'nuevo_orden' => 'required|integer|min:1'
        ]);

        // Verificar que el grupo esté asignado al ciclo
        if (!$ciclo->grupos()->where('grupo_id', $request->grupo_id)->exists()) {
            return response()->json([
                'message' => 'El grupo no está asignado a este ciclo.',
            ], 422);
        }

        $actualizado = $ciclo->actualizarOrdenGrupo($request->grupo_id, $request->nuevo_orden);

        if (!$actualizado) {
            return response()->json([
                'message' => 'No se pudo actualizar el orden del grupo.',
            ], 422);
        }

        // Si el cálculo automático está habilitado, recalcular fecha de fin
        if ($ciclo->fecha_fin_automatica) {
            $ciclo->actualizarFechaFin();
            $ciclo->save();
        }

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Orden del grupo actualizado exitosamente.',
            'data' => new CicloResource($ciclo),
        ]);
    }

    /**
     * Reordena todos los grupos del ciclo.
     *
     * @param Request $request
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function reordenarGrupos(Request $request, Ciclo $ciclo): JsonResponse
    {
        $request->validate([
            'nuevo_orden' => 'required|array',
            'nuevo_orden.*' => 'integer|exists:grupos,id'
        ]);

        // Verificar que todos los grupos estén asignados al ciclo
        $gruposAsignados = $ciclo->grupos()->pluck('grupo_id')->toArray();
        $gruposSolicitados = $request->nuevo_orden;

        if (count($gruposSolicitados) !== count($gruposAsignados) ||
            array_diff($gruposSolicitados, $gruposAsignados)) {
            return response()->json([
                'message' => 'El nuevo orden debe incluir todos los grupos asignados al ciclo.',
            ], 422);
        }

        $ciclo->reordenarGrupos($request->nuevo_orden);

        // Si el cálculo automático está habilitado, recalcular fecha de fin
        if ($ciclo->fecha_fin_automatica) {
            $ciclo->actualizarFechaFin();
            $ciclo->save();
        }

        $ciclo->load(['sede', 'curso', 'grupos']);

        return response()->json([
            'message' => 'Grupos reordenados exitosamente.',
            'data' => new CicloResource($ciclo),
        ]);
    }

    /**
     * Obtiene el cronograma detallado del ciclo.
     *
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function cronograma(Ciclo $ciclo): JsonResponse
    {
        $ciclo->load(['grupos.modulo', 'grupos.horarios']);

        $cronograma = $ciclo->cronograma;

        return response()->json([
            'data' => [
                'ciclo' => [
                    'id' => $ciclo->id,
                    'nombre' => $ciclo->nombre,
                    'fecha_inicio' => $ciclo->fecha_inicio,
                    'fecha_fin' => $ciclo->fecha_fin,
                    'fecha_fin_automatica' => $ciclo->fecha_fin_automatica,
                ],
                'cronograma' => $cronograma,
                'resumen' => [
                    'total_grupos' => count($cronograma),
                    'duracion_total_dias' => $ciclo->duracion_dias,
                    'total_horas' => $ciclo->getTotalHorasAttribute(),
                    'horas_por_semana_promedio' => count($cronograma) > 0
                        ? round($ciclo->getHorasPorSemanaAttribute() / count($cronograma), 2)
                        : 0
                ]
            ]
        ]);
    }

    /**
     * Obtiene el siguiente orden disponible para un nuevo grupo.
     *
     * @param Ciclo $ciclo
     * @return JsonResponse
     */
    public function siguienteOrden(Ciclo $ciclo): JsonResponse
    {
        return response()->json([
            'data' => [
                'siguiente_orden' => $ciclo->getSiguienteOrden()
            ]
        ]);
    }
}
