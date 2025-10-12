<?php

namespace App\Http\Controllers\Api\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Configuracion\StoreSedeRequest;
use App\Http\Requests\Api\Configuracion\UpdateSedeRequest;
use App\Http\Resources\Api\Configuracion\SedeResource;
use App\Http\Resources\Api\Configuracion\HorarioResource;
use App\Models\Configuracion\Sede;
use App\Models\Configuracion\Poblacion;
use App\Models\Configuracion\Horario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SedeController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:co_sedes')->only(['index', 'show', 'filters', 'statistics']);
        $this->middleware('permission:co_sedeCrear')->only(['store']);
        $this->middleware('permission:co_sedeEditar')->only(['update']);
        $this->middleware('permission:co_sedeInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista de las sedes.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'poblacion_id', 'nombre', 'direccion', 'telefono', 'email']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['poblacion', 'areas', 'horarios'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (str_contains($request->with, 'poblacion') || str_contains($request->with, 'areas') || str_contains($request->with, 'horarios'));

        // Construir query usando scopes
        $sedes = Sede::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => SedeResource::collection($sedes),
            'meta' => [
                'current_page' => $sedes->currentPage(),
                'last_page' => $sedes->lastPage(),
                'per_page' => $sedes->perPage(),
                'total' => $sedes->total(),
                'from' => $sedes->firstItem(),
                'to' => $sedes->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena una nueva sede en la base de datos.
     *
     * @param StoreSedeRequest $request
     * @return JsonResponse
     */
    public function store(StoreSedeRequest $request): JsonResponse
    {
        $sede = Sede::create([
            'nombre' => $request->nombre,
            'direccion' => $request->direccion,
            'telefono' => $request->telefono,
            'email' => $request->email,
            'hora_inicio' => $request->hora_inicio,
            'hora_fin' => $request->hora_fin,
            'poblacion_id' => $request->poblacion_id,
        ]);

        // Asignar áreas si se proporcionan
        if ($request->has('areas') && is_array($request->areas)) {
            $sede->areas()->attach($request->areas);
        }

        // Crear horarios si se proporcionan
        if ($request->has('horarios') && is_array($request->horarios)) {
            foreach ($request->horarios as $horarioData) {
                $sede->horarios()->create([
                    'area_id' => $horarioData['area_id'],
                    'dia' => $horarioData['dia'],
                    'hora' => $horarioData['hora'],
                    'tipo' => $horarioData['tipo'] ?? true,
                    'periodo' => $horarioData['periodo'] ?? true,
                    'grupo_id' => $horarioData['grupo_id'] ?? null,
                    'grupo_nombre' => $horarioData['grupo_nombre'] ?? null,
                    'status' => 1,
                ]);
            }
        }

        $sede->load(['poblacion', 'areas', 'horarios']);

        return response()->json([
            'message' => 'Sede creada exitosamente.',
            'data' => new SedeResource($sede),
        ], 201);
    }

    /**
     * Muestra la sede especificada.
     *
     * @param Request $request
     * @param Sede $sede
     * @return JsonResponse
     */
    public function show(Request $request, Sede $sede): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['poblacion', 'areas', 'horarios'];

        // Cargar relaciones y contadores usando el modelo
        $sede->load($relations);

        return response()->json([
            'data' => new SedeResource($sede),
        ]);
    }

    /**
     * Actualiza la sede especificada en la base de datos.
     *
     * @param UpdateSedeRequest $request
     * @param Sede $sede
     * @return JsonResponse
     */
    public function update(UpdateSedeRequest $request, Sede $sede): JsonResponse
    {
        $sede->update($request->only([
            'nombre',
            'direccion',
            'telefono',
            'email',
            'hora_inicio',
            'hora_fin',
            'poblacion_id',
        ]));

        // Actualizar áreas si se proporcionan
        if ($request->has('areas')) {
            if (is_array($request->areas)) {
                $sede->areas()->sync($request->areas);
            } else {
                // Si se envía null o vacío, eliminar todas las áreas
                $sede->areas()->detach();
            }
        }

        // Actualizar horarios si se proporcionan
        if ($request->has('horarios')) {
            if (is_array($request->horarios)) {
                // Eliminar horarios existentes
                $sede->horarios()->delete();

                // Crear nuevos horarios
                foreach ($request->horarios as $horarioData) {
                    $sede->horarios()->create([
                        'area_id' => $horarioData['area_id'],
                        'dia' => $horarioData['dia'],
                        'hora' => $horarioData['hora'],
                        'tipo' => $horarioData['tipo'] ?? true,
                        'periodo' => $horarioData['periodo'] ?? true,
                        'grupo_id' => $horarioData['grupo_id'] ?? null,
                        'grupo_nombre' => $horarioData['grupo_nombre'] ?? null,
                        'status' => 1,
                    ]);
                }
            } else {
                // Si se envía null o vacío, eliminar todos los horarios
                $sede->horarios()->delete();
            }
        }

        $sede->load(['poblacion', 'areas', 'horarios']);

        return response()->json([
            'message' => 'Sede actualizada exitosamente.',
            'data' => new SedeResource($sede),
        ]);
    }

    /**
     * Elimina la sede especificada de la base de datos (soft delete).
     *
     * @param Sede $sede
     * @return JsonResponse
     */
    public function destroy(Sede $sede): JsonResponse
    {
        $sede->delete(); // Soft delete

        return response()->json([
            'message' => 'Sede eliminada exitosamente.',
        ]);
    }

    /**
     * Restaura una sede eliminada (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $sede = Sede::onlyTrashed()->findOrFail($id);
        $sede->restore();

        return response()->json([
            'message' => 'Sede restaurada exitosamente.',
            'data' => new SedeResource($sede->load(['poblacion', 'areas', 'horarios'])),
        ]);
    }

    /**
     * Elimina permanentemente una sede.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $sede = Sede::onlyTrashed()->findOrFail($id);
        $sede->forceDelete();

        return response()->json([
            'message' => 'Sede eliminada permanentemente.',
        ]);
    }

    /**
     * Obtiene solo las sedes eliminadas (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'poblacion_id', 'nombre', 'direccion', 'telefono', 'email']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['poblacion', 'areas', 'horarios'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && (str_contains($request->with, 'poblacion') || str_contains($request->with, 'areas') || str_contains($request->with, 'horarios'));

        // Construir query usando scopes (solo eliminados)
        $sedes = Sede::onlyTrashed()
            ->withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => SedeResource::collection($sedes),
            'meta' => [
                'current_page' => $sedes->currentPage(),
                'last_page' => $sedes->lastPage(),
                'per_page' => $sedes->perPage(),
                'total' => $sedes->total(),
                'from' => $sedes->firstItem(),
                'to' => $sedes->lastItem(),
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
        $poblaciones = Poblacion::select('id', 'nombre', 'pais', 'provincia')
            ->where('pais', 'Colombia')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => [
                'poblaciones' => $poblaciones,
            ],
        ]);
    }

    /**
     * Obtiene estadísticas de sedes.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Sede::count(),
                'activos' => Sede::whereNull('deleted_at')->count(),
                'eliminados' => Sede::onlyTrashed()->count(),
            ],
            'por_poblacion' => Sede::with('poblacion')
                ->selectRaw('poblacion_id, count(*) as total')
                ->groupBy('poblacion_id')
                ->orderBy('total', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'poblacion' => $item->poblacion ? $item->poblacion->nombre : 'Sin población',
                        'total' => $item->total,
                    ];
                }),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}
