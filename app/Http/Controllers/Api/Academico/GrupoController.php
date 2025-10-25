<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreGrupoRequest;
use App\Http\Requests\Api\Academico\UpdateGrupoRequest;
use App\Http\Requests\Api\Academico\StoreGrupoHorarioRequest;
use App\Http\Requests\Api\Academico\UpdateGrupoHorarioRequest;
use App\Http\Resources\Api\Academico\GrupoResource;
use App\Models\Academico\Grupo;
use App\Models\Configuracion\Horario;
use App\Traits\HasActiveStatus;
use App\Traits\HasGrupoHorarios;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GrupoController extends Controller
{
    use HasActiveStatus, HasGrupoHorarios;

    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_grupos')->only(['index', 'show', 'filters', 'statistics', 'getHorarios', 'getHorariosEstadisticas']);
        $this->middleware('permission:aca_grupoCrear')->only(['store', 'storeHorarios']);
        $this->middleware('permission:aca_grupoEditar')->only(['update', 'updateHorarios']);
        $this->middleware('permission:aca_grupoInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed', 'destroyHorarios']);
    }

    /**
     * Muestra una lista de los grupos.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only([
            'search', 'status', 'sede_id', 'modulo_id', 'profesor_id',
            'jornada', 'inscritos_min', 'inscritos_max', 'include_trashed', 'only_trashed'
        ]);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sede', 'modulo', 'profesor', 'horarios'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (
            str_contains($request->with, 'sede') ||
            str_contains($request->with, 'modulo') ||
            str_contains($request->with, 'profesor') ||
            str_contains($request->with, 'horarios')
        );

        // Construir query usando scopes
        $grupos = Grupo::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => GrupoResource::collection($grupos),
            'meta' => [
                'current_page' => $grupos->currentPage(),
                'last_page' => $grupos->lastPage(),
                'per_page' => $grupos->perPage(),
                'total' => $grupos->total(),
                'from' => $grupos->firstItem(),
                'to' => $grupos->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena un nuevo grupo en la base de datos.
     *
     * @param StoreGrupoRequest $request
     * @return JsonResponse
     */
    public function store(StoreGrupoRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Crear el grupo
            $grupo = Grupo::create([
                'sede_id' => $request->sede_id,
                'modulo_id' => $request->modulo_id,
                'profesor_id' => $request->profesor_id,
                'nombre' => $request->nombre,
                'inscritos' => $request->inscritos,
                'jornada' => $request->jornada,
                'status' => $request->status ?? 1, // Por defecto estado "Activo"
            ]);

            // Si se proporcionan horarios, asignarlos al grupo
            if ($request->has('horarios') && !empty($request->horarios)) {
                // Validar que no haya solapamientos
                $validacion = $this->validarSolapamientoHorarios($request->horarios);
                if (!$validacion['valido']) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Error en la validación de horarios.',
                        'errors' => $validacion['errores'],
                    ], 422);
                }

                // Asignar horarios al grupo
                if (!$this->asignarHorariosAGrupo($grupo, $request->horarios)) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Error al asignar horarios al grupo.',
                    ], 500);
                }
            }

            DB::commit();

            // Cargar relaciones
            $grupo->load(['sede', 'modulo', 'profesor', 'horarios.area']);

            $message = 'Grupo creado exitosamente.';
            if ($request->has('horarios') && !empty($request->horarios)) {
                $message .= ' Horarios asignados correctamente.';
            }

            return response()->json([
                'message' => $message,
                'data' => new GrupoResource($grupo),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al crear el grupo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra el grupo especificado.
     *
     * @param Request $request
     * @param Grupo $grupo
     * @return JsonResponse
     */
    public function show(Request $request, Grupo $grupo): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sede', 'modulo', 'profesor', 'horarios'];

        // Cargar relaciones y contadores usando el modelo
        $grupo->load($relations);
        $grupo->loadCount(['sede', 'modulo', 'profesor', 'horarios']);

        return response()->json([
            'data' => new GrupoResource($grupo),
        ]);
    }

    /**
     * Actualiza el grupo especificado en la base de datos.
     *
     * @param UpdateGrupoRequest $request
     * @param Grupo $grupo
     * @return JsonResponse
     */
    public function update(UpdateGrupoRequest $request, Grupo $grupo): JsonResponse
    {
        $grupo->update($request->only([
            'sede_id',
            'modulo_id',
            'profesor_id',
            'nombre',
            'inscritos',
            'jornada',
            'status',
        ]));

        $grupo->load(['sede', 'modulo', 'profesor']);

        return response()->json([
            'message' => 'Grupo actualizado exitosamente.',
            'data' => new GrupoResource($grupo),
        ]);
    }

    /**
     * Elimina el grupo especificado de la base de datos (soft delete).
     *
     * @param Grupo $grupo
     * @return JsonResponse
     */
    public function destroy(Grupo $grupo): JsonResponse
    {
        // Verificar si tiene inscritos
        if ($grupo->inscritos > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el grupo porque tiene estudiantes inscritos.',
            ], 422);
        }

        $grupo->delete(); // Soft delete

        return response()->json([
            'message' => 'Grupo eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un grupo eliminado (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $grupo = Grupo::onlyTrashed()->findOrFail($id);
        $grupo->restore();

        return response()->json([
            'message' => 'Grupo restaurado exitosamente.',
            'data' => new GrupoResource($grupo->load(['sede', 'modulo', 'profesor'])),
        ]);
    }

    /**
     * Elimina permanentemente un grupo.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $grupo = Grupo::onlyTrashed()->findOrFail($id);

        // Verificar si tiene inscritos
        if ($grupo->inscritos > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el grupo porque tiene estudiantes inscritos.',
            ], 422);
        }

        $grupo->forceDelete();

        return response()->json([
            'message' => 'Grupo eliminado permanentemente.',
        ]);
    }

    /**
     * Obtiene solo los grupos eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only([
            'search', 'status', 'sede_id', 'modulo_id', 'profesor_id',
            'jornada', 'inscritos_min', 'inscritos_max'
        ]);
        $filters['only_trashed'] = true;

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sede', 'modulo', 'profesor', 'horarios'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (
            str_contains($request->with, 'sede') ||
            str_contains($request->with, 'modulo') ||
            str_contains($request->with, 'profesor') ||
            str_contains($request->with, 'horarios')
        );

        // Construir query usando scopes (solo eliminados)
        $grupos = Grupo::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => GrupoResource::collection($grupos),
            'meta' => [
                'current_page' => $grupos->currentPage(),
                'last_page' => $grupos->lastPage(),
                'per_page' => $grupos->perPage(),
                'total' => $grupos->total(),
                'from' => $grupos->firstItem(),
                'to' => $grupos->lastItem(),
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
        $modulos = \App\Models\Academico\Modulo::select('id', 'nombre')->get();
        $profesores = \App\Models\User::role('profesor')->select('id', 'name')->get();

        return response()->json([
            'data' => [
                'status_options' => self::getActiveStatusOptions(),
                'jornada_options' => [
                    ['value' => 0, 'label' => 'Mañana'],
                    ['value' => 1, 'label' => 'Tarde'],
                    ['value' => 2, 'label' => 'Noche'],
                    ['value' => 3, 'label' => 'Fin de semana'],
                ],
                'sedes' => $sedes,
                'modulos' => $modulos,
                'profesores' => $profesores,
            ],
        ]);
    }

    /**
     * Obtiene estadísticas de grupos.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Grupo::count(),
                'activos' => Grupo::whereNull('deleted_at')->count(),
                'eliminados' => Grupo::onlyTrashed()->count(),
            ],
            'por_status' => [
                'activos' => Grupo::where('status', 1)->count(),
                'inactivos' => Grupo::where('status', 0)->count(),
            ],
            'por_jornada' => [
                'manana' => Grupo::where('jornada', 0)->count(),
                'tarde' => Grupo::where('jornada', 1)->count(),
                'noche' => Grupo::where('jornada', 2)->count(),
                'fin_semana' => Grupo::where('jornada', 3)->count(),
            ],
            'por_inscritos' => [
                'pocos' => Grupo::where('inscritos', '<=', 10)->count(),
                'medios' => Grupo::whereBetween('inscritos', [11, 20])->count(),
                'muchos' => Grupo::where('inscritos', '>=', 21)->count(),
            ],
            'total_inscritos' => Grupo::sum('inscritos'),
            'promedio_inscritos' => round(Grupo::avg('inscritos'), 2),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Asigna horarios a un grupo específico.
     *
     * @param StoreGrupoHorarioRequest $request
     * @param Grupo $grupo
     * @return JsonResponse
     */
    public function storeHorarios(StoreGrupoHorarioRequest $request, Grupo $grupo): JsonResponse
    {
        // Validar que no haya solapamientos
        $validacion = $this->validarSolapamientoHorarios($request->horarios);
        if (!$validacion['valido']) {
            return response()->json([
                'message' => 'Error en la validación de horarios.',
                'errors' => $validacion['errores'],
            ], 422);
        }

        if ($this->asignarHorariosAGrupo($grupo, $request->horarios)) {
            // Recargar el grupo con sus horarios
            $grupo->load(['horarios.area']);

            return response()->json([
                'message' => 'Horarios asignados exitosamente al grupo.',
                'data' => new GrupoResource($grupo),
            ], 201);
        }

        return response()->json([
            'message' => 'Error al asignar horarios al grupo.',
        ], 500);
    }

    /**
     * Actualiza los horarios de un grupo específico.
     *
     * @param UpdateGrupoHorarioRequest $request
     * @param Grupo $grupo
     * @return JsonResponse
     */
    public function updateHorarios(UpdateGrupoHorarioRequest $request, Grupo $grupo): JsonResponse
    {
        // Si se proporcionan nuevos horarios, validar y actualizar
        if ($request->has('horarios')) {
            // Validar que no haya solapamientos
            $validacion = $this->validarSolapamientoHorarios($request->horarios);
            if (!$validacion['valido']) {
                return response()->json([
                    'message' => 'Error en la validación de horarios.',
                    'errors' => $validacion['errores'],
                ], 422);
            }

            if (!$this->actualizarHorariosDeGrupo($grupo, $request->horarios)) {
                return response()->json([
                    'message' => 'Error al actualizar horarios del grupo.',
                ], 500);
            }
        }

        // Recargar el grupo con sus horarios
        $grupo->load(['horarios.area']);

        return response()->json([
            'message' => 'Horarios del grupo actualizados exitosamente.',
            'data' => new GrupoResource($grupo),
        ]);
    }

    /**
     * Elimina todos los horarios de un grupo específico.
     *
     * @param Grupo $grupo
     * @return JsonResponse
     */
    public function destroyHorarios(Grupo $grupo): JsonResponse
    {
        if ($this->eliminarHorariosDeGrupo($grupo)) {
            return response()->json([
                'message' => 'Horarios del grupo eliminados exitosamente.',
            ]);
        }

        return response()->json([
            'message' => 'Error al eliminar horarios del grupo.',
        ], 500);
    }

    /**
     * Obtiene los horarios de un grupo específico.
     *
     * @param Request $request
     * @param Grupo $grupo
     * @return JsonResponse
     */
    public function getHorarios(Request $request, Grupo $grupo): JsonResponse
    {
        $filtros = $request->only(['status', 'dia']);
        $horarios = $this->obtenerHorariosDeGrupo($grupo, $filtros);

        return response()->json([
            'data' => $horarios->map(function ($horario) {
                return [
                    'id' => $horario->id,
                    'dia' => $horario->dia,
                    'hora' => $horario->hora?->format('H:i:s'),
                    'area' => [
                        'id' => $horario->area->id,
                        'nombre' => $horario->area->nombre,
                    ],
                    'status' => $horario->status,
                    'status_text' => self::getActiveStatusText($horario->status),
                    'created_at' => $horario->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $horario->updated_at?->format('Y-m-d H:i:s'),
                ];
            }),
        ]);
    }

    /**
     * Obtiene estadísticas de horarios de un grupo específico.
     *
     * @param Grupo $grupo
     * @return JsonResponse
     */
    public function getHorariosEstadisticas(Grupo $grupo): JsonResponse
    {
        $estadisticas = $this->obtenerEstadisticasHorariosGrupo($grupo);

        return response()->json([
            'data' => $estadisticas,
        ]);
    }
}
