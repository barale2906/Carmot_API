<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreTemaRequest;
use App\Http\Requests\Api\Academico\UpdateTemaRequest;
use App\Http\Resources\Api\Academico\TemaResource;
use App\Models\Academico\Tema;
use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para la gestión de temas académicos.
 *
 * Este controlador maneja todas las operaciones CRUD relacionadas con los temas,
 * incluyendo listado, creación, actualización, eliminación (soft delete),
 * restauración y eliminación permanente.
 */
class TemaController extends Controller
{
    use HasActiveStatus, HasActiveStatusValidation;

    /**
     * Constructor del controlador.
     * Define los middlewares de permisos para cada acción.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_temas')->only(['index', 'show', 'filters', 'statistics']);
        $this->middleware('permission:aca_temaCrear')->only(['store']);
        $this->middleware('permission:aca_temaEditar')->only(['update']);
        $this->middleware('permission:aca_temaInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista de los temas.
     *
     * @param Request $request Solicitud HTTP con parámetros de filtrado, ordenamiento y paginación
     * @return JsonResponse Respuesta JSON con la lista paginada de temas
     */
    public function index(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'status', 'include_trashed', 'only_trashed']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['topicos'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && str_contains($request->with, 'topicos');

        // Construir query usando scopes
        $temas = Tema::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => TemaResource::collection($temas),
            'meta' => [
                'current_page' => $temas->currentPage(),
                'last_page' => $temas->lastPage(),
                'per_page' => $temas->perPage(),
                'total' => $temas->total(),
                'from' => $temas->firstItem(),
                'to' => $temas->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena un nuevo tema en la base de datos.
     *
     * @param StoreTemaRequest $request Solicitud validada con los datos del tema
     * @return JsonResponse Respuesta JSON con el tema creado
     */
    public function store(StoreTemaRequest $request): JsonResponse
    {
        $tema = Tema::create([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'duracion' => $request->duracion,
            'status' => $request->status ?? 1, // Por defecto estado "Activo"
        ]);

        // Asociar tópicos si se proporcionan
        if ($request->has('topico_ids') && is_array($request->topico_ids)) {
            $tema->topicos()->attach($request->topico_ids);
        }

        $tema->load(['topicos']);

        return response()->json([
            'message' => 'Tema creado exitosamente.',
            'data' => new TemaResource($tema),
        ], 201);
    }

    /**
     * Muestra el tema especificado.
     *
     * @param Request $request Solicitud HTTP con parámetros opcionales
     * @param Tema $tema Instancia del tema a mostrar
     * @return JsonResponse Respuesta JSON con los datos del tema
     */
    public function show(Request $request, Tema $tema): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['topicos'];

        // Cargar relaciones y contadores usando el modelo
        $tema->load($relations);
        $tema->loadCount(['topicos']);

        return response()->json([
            'data' => new TemaResource($tema),
        ]);
    }

    /**
     * Actualiza el tema especificado en la base de datos.
     *
     * @param UpdateTemaRequest $request Solicitud validada con los datos actualizados
     * @param Tema $tema Instancia del tema a actualizar
     * @return JsonResponse Respuesta JSON con el tema actualizado
     */
    public function update(UpdateTemaRequest $request, Tema $tema): JsonResponse
    {
        $tema->update($request->only([
            'nombre',
            'descripcion',
            'duracion',
            'status',
        ]));

        // Actualizar tópicos si se proporcionan
        if ($request->has('topico_ids') && is_array($request->topico_ids)) {
            $tema->topicos()->sync($request->topico_ids);
        }

        $tema->load(['topicos']);

        return response()->json([
            'message' => 'Tema actualizado exitosamente.',
            'data' => new TemaResource($tema),
        ]);
    }

    /**
     * Elimina el tema especificado de la base de datos (soft delete).
     *
     * @param Tema $tema Instancia del tema a eliminar
     * @return JsonResponse Respuesta JSON con mensaje de confirmación
     */
    public function destroy(Tema $tema): JsonResponse
    {
        // Verificar si tiene tópicos asociados
        if ($tema->topicos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el tema porque tiene tópicos asociados.',
            ], 422);
        }

        $tema->delete(); // Soft delete

        return response()->json([
            'message' => 'Tema eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un tema eliminado (soft delete).
     *
     * @param int $id ID del tema a restaurar
     * @return JsonResponse Respuesta JSON con el tema restaurado
     */
    public function restore(int $id): JsonResponse
    {
        $tema = Tema::onlyTrashed()->findOrFail($id);
        $tema->restore();

        return response()->json([
            'message' => 'Tema restaurado exitosamente.',
            'data' => new TemaResource($tema->load(['topicos'])),
        ]);
    }

    /**
     * Elimina permanentemente un tema.
     *
     * @param int $id ID del tema a eliminar permanentemente
     * @return JsonResponse Respuesta JSON con mensaje de confirmación
     */
    public function forceDelete(int $id): JsonResponse
    {
        $tema = Tema::onlyTrashed()->findOrFail($id);

        // Verificar si tiene tópicos asociados
        if ($tema->topicos()->withTrashed()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el tema porque tiene tópicos asociados.',
            ], 422);
        }

        $tema->forceDelete();

        return response()->json([
            'message' => 'Tema eliminado permanentemente.',
        ]);
    }

    /**
     * Obtiene solo los temas eliminados (soft delete).
     *
     * @param Request $request Solicitud HTTP con parámetros de filtrado y paginación
     * @return JsonResponse Respuesta JSON con la lista paginada de temas eliminados
     */
    public function trashed(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'status']);
        $filters['only_trashed'] = true;

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['topicos'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && str_contains($request->with, 'topicos');

        // Construir query usando scopes (solo eliminados)
        $temas = Tema::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => TemaResource::collection($temas),
            'meta' => [
                'current_page' => $temas->currentPage(),
                'last_page' => $temas->lastPage(),
                'per_page' => $temas->perPage(),
                'total' => $temas->total(),
                'from' => $temas->firstItem(),
                'to' => $temas->lastItem(),
            ],
        ]);
    }

    /**
     * Obtiene las opciones de filtros disponibles.
     *
     * @return JsonResponse Respuesta JSON con las opciones de filtros
     */
    public function filters(): JsonResponse
    {
        $temas = Tema::select('id', 'nombre')->get();

        return response()->json([
            'data' => [
                'status_options' => self::getActiveStatusOptions(),
                'temas' => $temas,
            ],
        ]);
    }

    /**
     * Obtiene estadísticas de temas.
     *
     * @return JsonResponse Respuesta JSON con las estadísticas de temas
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Tema::count(),
                'activos' => Tema::whereNull('deleted_at')->count(),
                'eliminados' => Tema::onlyTrashed()->count(),
            ],
            'por_status' => [
                'activos' => Tema::where('status', 1)->count(),
                'inactivos' => Tema::where('status', 0)->count(),
            ],
            'con_topicos' => Tema::with('topicos')
                ->selectRaw('id, count(tema_topico.topico_id) as total_topicos')
                ->leftJoin('tema_topico', 'temas.id', '=', 'tema_topico.tema_id')
                ->groupBy('temas.id')
                ->having('total_topicos', '>', 0)
                ->get(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}
