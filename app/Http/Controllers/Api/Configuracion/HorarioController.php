<?php

namespace App\Http\Controllers\Api\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Configuracion\StoreHorarioRequest;
use App\Http\Requests\Api\Configuracion\UpdateHorarioRequest;
use App\Http\Resources\Api\Configuracion\HorarioResource;
use App\Models\Configuracion\Horario;
use App\Models\Configuracion\Sede;
use App\Models\Configuracion\Area;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HorarioController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:co_horarios')->only(['index', 'show', 'filters', 'statistics']);
        $this->middleware('permission:co_horarioCrear')->only(['store']);
        $this->middleware('permission:co_horarioEditar')->only(['update']);
        $this->middleware('permission:co_horarioInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista de los horarios.
     *
     * Permite filtrar horarios por sede, área, día, tipo, período y estado.
     * Soporta paginación y ordenamiento personalizado.
     * Puede incluir relaciones con sede y área.
     *
     * @param Request $request Parámetros de consulta:
     *   - search: Búsqueda general
     *   - sede_id: Filtrar por sede específica
     *   - area_id: Filtrar por área específica
     *   - dia: Filtrar por día de la semana
     *   - tipo: Filtrar por tipo (true: sede, false: grupo)
     *   - periodo: Filtrar por período (true: inicio, false: fin)
     *   - status: Filtrar por estado (1: activo, 0: inactivo)
     *   - with: Relaciones a incluir (sede,area)
     *   - sort_by: Campo para ordenar
     *   - sort_direction: Dirección del ordenamiento (asc,desc)
     *   - per_page: Elementos por página
     * @return JsonResponse Respuesta con lista paginada de horarios
     */
    public function index(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'sede_id', 'area_id', 'dia', 'tipo', 'periodo', 'status']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sede', 'area'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (str_contains($request->with, 'sede') || str_contains($request->with, 'area'));

        // Construir query usando scopes
        $horarios = Horario::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => HorarioResource::collection($horarios),
            'meta' => [
                'current_page' => $horarios->currentPage(),
                'last_page' => $horarios->lastPage(),
                'per_page' => $horarios->perPage(),
                'total' => $horarios->total(),
                'from' => $horarios->firstItem(),
                'to' => $horarios->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena un nuevo horario en la base de datos.
     *
     * Crea un nuevo registro de horario con los datos proporcionados.
     * El horario se crea con estado activo por defecto.
     * Incluye validación de datos mediante StoreHorarioRequest.
     *
     * @param StoreHorarioRequest $request Datos del horario:
     *   - sede_id: ID de la sede (requerido)
     *   - area_id: ID del área (requerido)
     *   - grupo_id: ID del grupo (opcional)
     *   - grupo_nombre: Nombre del grupo (opcional)
     *   - tipo: Tipo de horario (true: sede, false: grupo)
     *   - periodo: Período (true: inicio, false: fin)
     *   - dia: Día de la semana
     *   - hora: Hora del horario
     * @return JsonResponse Respuesta con el horario creado
     */
    public function store(StoreHorarioRequest $request): JsonResponse
    {
        $horario = Horario::create([
            'sede_id' => $request->sede_id,
            'area_id' => $request->area_id,
            'grupo_id' => $request->grupo_id,
            'grupo_nombre' => $request->grupo_nombre,
            'tipo' => $request->tipo,
            'periodo' => $request->periodo,
            'dia' => $request->dia,
            'hora' => $request->hora,
            'status' => 1,
        ]);

        $horario->load(['sede', 'area']);

        return response()->json([
            'message' => 'Horario creado exitosamente.',
            'data' => new HorarioResource($horario),
        ], 201);
    }

    /**
     * Muestra el horario especificado.
     *
     * Obtiene los detalles de un horario específico.
     * Puede incluir relaciones con sede y área.
     *
     * @param Request $request Parámetros de consulta:
     *   - with: Relaciones a incluir (sede,area)
     * @param Horario $horario Horario a mostrar
     * @return JsonResponse Respuesta con los datos del horario
     */
    public function show(Request $request, Horario $horario): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sede', 'area'];

        // Cargar relaciones y contadores usando el modelo
        $horario->load($relations);

        return response()->json([
            'data' => new HorarioResource($horario),
        ]);
    }

    /**
     * Actualiza el horario especificado en la base de datos.
     *
     * Modifica los datos de un horario existente.
     * Incluye validación de datos mediante UpdateHorarioRequest.
     * No permite modificar el estado (status) directamente.
     *
     * @param UpdateHorarioRequest $request Datos a actualizar:
     *   - sede_id: ID de la sede
     *   - area_id: ID del área
     *   - grupo_id: ID del grupo
     *   - grupo_nombre: Nombre del grupo
     *   - tipo: Tipo de horario
     *   - periodo: Período del horario
     *   - dia: Día de la semana
     *   - hora: Hora del horario
     * @param Horario $horario Horario a actualizar
     * @return JsonResponse Respuesta con el horario actualizado
     */
    public function update(UpdateHorarioRequest $request, Horario $horario): JsonResponse
    {
        $horario->update($request->only([
            'sede_id',
            'area_id',
            'grupo_id',
            'grupo_nombre',
            'tipo',
            'periodo',
            'dia',
            'hora',
        ]));

        $horario->load(['sede', 'area']);

        return response()->json([
            'message' => 'Horario actualizado exitosamente.',
            'data' => new HorarioResource($horario),
        ]);
    }

    /**
     * Elimina el horario especificado de la base de datos (soft delete).
     *
     * @param Horario $horario
     * @return JsonResponse
     */
    public function destroy(Horario $horario): JsonResponse
    {
        $horario->delete(); // Soft delete

        return response()->json([
            'message' => 'Horario eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un horario eliminado (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $horario = Horario::onlyTrashed()->findOrFail($id);
        $horario->restore();

        return response()->json([
            'message' => 'Horario restaurado exitosamente.',
            'data' => new HorarioResource($horario->load(['sede', 'area'])),
        ]);
    }

    /**
     * Elimina permanentemente un horario.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $horario = Horario::onlyTrashed()->findOrFail($id);
        $horario->forceDelete();

        return response()->json([
            'message' => 'Horario eliminado permanentemente.',
        ]);
    }

    /**
     * Obtiene solo los horarios eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'sede_id', 'area_id', 'dia', 'tipo', 'periodo', 'status']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sede', 'area'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (str_contains($request->with, 'sede') || str_contains($request->with, 'area'));

        // Construir query usando scopes (solo eliminados)
        $horarios = Horario::onlyTrashed()
            ->withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => HorarioResource::collection($horarios),
            'meta' => [
                'current_page' => $horarios->currentPage(),
                'last_page' => $horarios->lastPage(),
                'per_page' => $horarios->perPage(),
                'total' => $horarios->total(),
                'from' => $horarios->firstItem(),
                'to' => $horarios->lastItem(),
            ],
        ]);
    }

    /**
     * Obtiene las opciones de filtros disponibles.
     *
     * Proporciona las opciones disponibles para filtrar horarios.
     * Incluye listas de sedes, áreas, días de la semana, tipos y períodos.
     *
     * @return JsonResponse Respuesta con opciones de filtros:
     *   - sedes: Lista de sedes disponibles
     *   - areas: Lista de áreas activas
     *   - dias: Días de la semana disponibles
     *   - tipos: Tipos de horario (sede/grupo)
     *   - periodos: Períodos disponibles (inicio/fin)
     */
    public function filters(): JsonResponse
    {
        $sedes = Sede::select('id', 'nombre', 'direccion')
            ->orderBy('nombre')
            ->get();

        $areas = Area::select('id', 'nombre')
            ->where('status', 1)
            ->orderBy('nombre')
            ->get();

        $dias = [
            'lunes' => 'Lunes',
            'martes' => 'Martes',
            'miércoles' => 'Miércoles',
            'jueves' => 'Jueves',
            'viernes' => 'Viernes',
            'sábado' => 'Sábado',
            'domingo' => 'Domingo',
        ];

        return response()->json([
            'data' => [
                'sedes' => $sedes,
                'areas' => $areas,
                'dias' => $dias,
                'tipos' => [
                    ['value' => true, 'label' => 'Horario de Sede'],
                    ['value' => false, 'label' => 'Horario de Grupo'],
                ],
                'periodos' => [
                    ['value' => true, 'label' => 'Inicio'],
                    ['value' => false, 'label' => 'Fin'],
                ],
            ],
        ]);
    }

    /**
     * Obtiene estadísticas de horarios.
     *
     * Proporciona estadísticas detalladas sobre los horarios registrados.
     * Incluye totales, distribución por sede, día y tipo.
     *
     * @return JsonResponse Respuesta con estadísticas:
     *   - totales: Conteos generales (total, activos, eliminados)
     *   - por_sede: Distribución de horarios por sede
     *   - por_dia: Distribución de horarios por día de la semana
     *   - por_tipo: Distribución de horarios por tipo (sede/grupo)
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Horario::count(),
                'activos' => Horario::whereNull('deleted_at')->count(),
                'eliminados' => Horario::onlyTrashed()->count(),
            ],
            'por_sede' => Horario::with('sede')
                ->selectRaw('sede_id, count(*) as total')
                ->groupBy('sede_id')
                ->orderBy('total', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'sede' => $item->sede ? $item->sede->nombre : 'Sin sede',
                        'total' => $item->total,
                    ];
                }),
            'por_dia' => Horario::selectRaw('dia, count(*) as total')
                ->groupBy('dia')
                ->orderBy('total', 'desc')
                ->get(),
            'por_tipo' => Horario::selectRaw('tipo, count(*) as total')
                ->groupBy('tipo')
                ->get()
                ->map(function ($item) {
                    return [
                        'tipo' => $item->tipo ? 'Sede' : 'Grupo',
                        'total' => $item->total,
                    ];
                }),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}
