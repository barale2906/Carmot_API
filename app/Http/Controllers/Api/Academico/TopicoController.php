<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreTopicoRequest;
use App\Http\Requests\Api\Academico\UpdateTopicoRequest;
use App\Http\Resources\Api\Academico\TopicoResource;
use App\Models\Academico\Topico;
use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TopicoController extends Controller
{
    use HasActiveStatus;

    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_topicos')->only(['index', 'show', 'filters', 'statistics']);
        $this->middleware('permission:aca_topicoCrear')->only(['store']);
        $this->middleware('permission:aca_topicoEditar')->only(['update']);
        $this->middleware('permission:aca_topicoInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista de los tópicos.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'status', 'include_trashed', 'only_trashed']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['modulos'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && str_contains($request->with, 'modulos');

        // Construir query usando scopes
        $topicos = Topico::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => TopicoResource::collection($topicos),
            'meta' => [
                'current_page' => $topicos->currentPage(),
                'last_page' => $topicos->lastPage(),
                'per_page' => $topicos->perPage(),
                'total' => $topicos->total(),
                'from' => $topicos->firstItem(),
                'to' => $topicos->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena un nuevo tópico en la base de datos.
     *
     * @param StoreTopicoRequest $request
     * @return JsonResponse
     */
    public function store(StoreTopicoRequest $request): JsonResponse
    {
        // Calcular duración automáticamente si se proporcionan temas
        $duracion = $request->duracion;
        if ($request->has('tema_ids') && is_array($request->tema_ids) && count($request->tema_ids) > 0) {
            // Calcular duración sumando las duraciones de los temas
            $temas = \App\Models\Academico\Tema::whereIn('id', $request->tema_ids)->get();
            $duracion = $temas->sum('duracion');
        } elseif ($duracion === null) {
            // Si no se proporciona duración ni temas, establecer en 0
            $duracion = 0;
        }

        $topico = Topico::create([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'duracion' => $duracion,
            'status' => $request->status ?? 1, // Por defecto estado "Activo"
        ]);

        // Asociar temas si se proporcionan
        if ($request->has('tema_ids') && is_array($request->tema_ids)) {
            $topico->temas()->attach($request->tema_ids);
        }

        // Asociar módulos si se proporcionan
        if ($request->has('modulo_ids') && is_array($request->modulo_ids)) {
            $topico->modulos()->attach($request->modulo_ids);
        }

        $topico->load(['modulos', 'temas']);

        return response()->json([
            'message' => 'Tópico creado exitosamente.',
            'data' => new TopicoResource($topico),
        ], 201);
    }

    /**
     * Muestra el tópico especificado.
     *
     * @param Request $request
     * @param Topico $topico
     * @return JsonResponse
     */
    public function show(Request $request, Topico $topico): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['modulos'];

        // Cargar relaciones y contadores usando el modelo
        $topico->load($relations);
        $topico->loadCount(['modulos']);

        return response()->json([
            'data' => new TopicoResource($topico),
        ]);
    }

    /**
     * Actualiza el tópico especificado en la base de datos.
     *
     * @param UpdateTopicoRequest $request
     * @param Topico $topico
     * @return JsonResponse
     */
    public function update(UpdateTopicoRequest $request, Topico $topico): JsonResponse
    {
        // Determinar si se actualizarán los temas (incluye array vacío)
        $actualizarTemas = $request->has('tema_ids');

        // Si se actualizan los temas (incluso si es array vacío), recalcular la duración automáticamente
        if ($actualizarTemas) {
            $temaIds = is_array($request->tema_ids) ? $request->tema_ids : [];

            if (count($temaIds) > 0) {
                // Si hay temas, calcular duración sumando las duraciones de los temas
                $temas = \App\Models\Academico\Tema::whereIn('id', $temaIds)->get();
                $duracionCalculada = $temas->sum('duracion');
            } else {
                // Si no hay temas (array vacío), establecer duración en 0
                $duracionCalculada = 0;
            }

            // Actualizar con la duración calculada (ignorar la duración enviada si existe)
            $topico->update([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'duracion' => $duracionCalculada,
                'status' => $request->status ?? $topico->status,
            ]);

            // Sincronizar temas (puede ser array vacío para eliminar todos los temas)
            $topico->temas()->sync($temaIds);
        } else {
            // Si no se actualizan los temas, usar la duración proporcionada o mantener la actual
            $datosActualizacion = $request->only([
                'nombre',
                'descripcion',
                'status',
            ]);

            // Solo actualizar duración si se proporciona explícitamente
            if ($request->has('duracion')) {
                $datosActualizacion['duracion'] = $request->duracion;
            }

            $topico->update($datosActualizacion);
        }

        // Actualizar módulos si se proporcionan
        if ($request->has('modulo_ids') && is_array($request->modulo_ids)) {
            $topico->modulos()->sync($request->modulo_ids);
        }

        $topico->load(['modulos', 'temas']);

        return response()->json([
            'message' => 'Tópico actualizado exitosamente.',
            'data' => new TopicoResource($topico),
        ]);
    }

    /**
     * Elimina el tópico especificado de la base de datos (soft delete).
     *
     * @param Topico $topico
     * @return JsonResponse
     */
    public function destroy(Topico $topico): JsonResponse
    {
        // Verificar si tiene módulos asociados
        if ($topico->modulos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el tópico porque tiene módulos asociados.',
            ], 422);
        }

        $topico->delete(); // Soft delete

        return response()->json([
            'message' => 'Tópico eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un tópico eliminado (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $topico = Topico::onlyTrashed()->findOrFail($id);
        $topico->restore();

        return response()->json([
            'message' => 'Tópico restaurado exitosamente.',
            'data' => new TopicoResource($topico->load(['modulos'])),
        ]);
    }

    /**
     * Elimina permanentemente un tópico.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $topico = Topico::onlyTrashed()->findOrFail($id);

        // Verificar si tiene módulos asociados
        if ($topico->modulos()->withTrashed()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el tópico porque tiene módulos asociados.',
            ], 422);
        }

        $topico->forceDelete();

        return response()->json([
            'message' => 'Tópico eliminado permanentemente.',
        ]);
    }

    /**
     * Obtiene solo los tópicos eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'status']);
        $filters['only_trashed'] = true;

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['modulos'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && str_contains($request->with, 'modulos');

        // Construir query usando scopes (solo eliminados)
        $topicos = Topico::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => TopicoResource::collection($topicos),
            'meta' => [
                'current_page' => $topicos->currentPage(),
                'last_page' => $topicos->lastPage(),
                'per_page' => $topicos->perPage(),
                'total' => $topicos->total(),
                'from' => $topicos->firstItem(),
                'to' => $topicos->lastItem(),
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
        $topicos = Topico::select('id', 'nombre')->get();

        return response()->json([
            'data' => [
                'status_options' => self::getActiveStatusOptions(),
                'topicos' => $topicos,
            ],
        ]);
    }

    /**
     * Obtiene estadísticas de tópicos.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Topico::count(),
                'activos' => Topico::whereNull('deleted_at')->count(),
                'eliminados' => Topico::onlyTrashed()->count(),
            ],
            'por_status' => [
                'activos' => Topico::where('status', 1)->count(),
                'inactivos' => Topico::where('status', 0)->count(),
            ],
            'con_modulos' => Topico::with('modulos')
                ->selectRaw('id, count(topico_modulo.modulo_id) as total_modulos')
                ->leftJoin('topico_modulo', 'topicos.id', '=', 'topico_modulo.topico_id')
                ->groupBy('topicos.id')
                ->having('total_modulos', '>', 0)
                ->get(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}
