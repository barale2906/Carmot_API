<?php

namespace App\Http\Controllers\Api\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Configuracion\StoreAreaRequest;
use App\Http\Requests\Api\Configuracion\UpdateAreaRequest;
use App\Http\Resources\Api\Configuracion\AreaResource;
use App\Models\Configuracion\Area;
use App\Models\Configuracion\Sede;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AreaController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:co_areas')->only(['index', 'show', 'filters', 'statistics']);
        $this->middleware('permission:co_areaCrear')->only(['store']);
        $this->middleware('permission:co_areaEditar')->only(['update']);
        $this->middleware('permission:co_areaInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista de las áreas.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'nombre', 'sede_id', 'poblacion_id']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sedes'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && str_contains($request->with, 'sedes');

        // Construir query usando scopes
        $areas = Area::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => AreaResource::collection($areas),
            'meta' => [
                'current_page' => $areas->currentPage(),
                'last_page' => $areas->lastPage(),
                'per_page' => $areas->perPage(),
                'total' => $areas->total(),
                'from' => $areas->firstItem(),
                'to' => $areas->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena una nueva área en la base de datos.
     *
     * @param StoreAreaRequest $request
     * @return JsonResponse
     */
    public function store(StoreAreaRequest $request): JsonResponse
    {
        $area = Area::create([
            'nombre' => $request->nombre,
            'status' => $request->status ?? 1,
        ]);

        // Asignar sedes si se proporcionan
        if ($request->has('sedes') && is_array($request->sedes)) {
            $area->sedes()->attach($request->sedes);
        }

        $area->load(['sedes']);

        return response()->json([
            'message' => 'Área creada exitosamente.',
            'data' => new AreaResource($area),
        ], 201);
    }

    /**
     * Muestra el área especificada.
     *
     * @param Request $request
     * @param Area $area
     * @return JsonResponse
     */
    public function show(Request $request, Area $area): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sedes'];

        // Cargar relaciones y contadores usando el modelo
        $area->load($relations);

        return response()->json([
            'data' => new AreaResource($area),
        ]);
    }

    /**
     * Actualiza el área especificada en la base de datos.
     *
     * @param UpdateAreaRequest $request
     * @param Area $area
     * @return JsonResponse
     */
    public function update(UpdateAreaRequest $request, Area $area): JsonResponse
    {
        $area->update($request->only([
            'nombre',
            'status',
        ]));

        // Sincronizar sedes si se proporcionan
        if ($request->has('sedes')) {
            if (is_array($request->sedes)) {
                $area->sedes()->sync($request->sedes);
            } else {
                $area->sedes()->detach();
            }
        }

        $area->load(['sedes']);

        return response()->json([
            'message' => 'Área actualizada exitosamente.',
            'data' => new AreaResource($area),
        ]);
    }

    /**
     * Elimina el área especificada de la base de datos (soft delete).
     *
     * @param Area $area
     * @return JsonResponse
     */
    public function destroy(Area $area): JsonResponse
    {
        $area->delete(); // Soft delete

        return response()->json([
            'message' => 'Área eliminada exitosamente.',
        ]);
    }

    /**
     * Restaura un área eliminada (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $area = Area::onlyTrashed()->findOrFail($id);
        $area->restore();

        return response()->json([
            'message' => 'Área restaurada exitosamente.',
            'data' => new AreaResource($area->load(['sedes'])),
        ]);
    }

    /**
     * Elimina permanentemente un área.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $area = Area::onlyTrashed()->findOrFail($id);
        $area->forceDelete();

        return response()->json([
            'message' => 'Área eliminada permanentemente.',
        ]);
    }

    /**
     * Obtiene solo las áreas eliminadas (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'nombre', 'sede_id', 'poblacion_id']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['sedes'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && str_contains($request->with, 'sedes');

        // Construir query usando scopes (solo eliminados)
        $areas = Area::onlyTrashed()
            ->withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => AreaResource::collection($areas),
            'meta' => [
                'current_page' => $areas->currentPage(),
                'last_page' => $areas->lastPage(),
                'per_page' => $areas->perPage(),
                'total' => $areas->total(),
                'from' => $areas->firstItem(),
                'to' => $areas->lastItem(),
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
        $sedes = Sede::select('id', 'nombre', 'direccion')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => [
                'sedes' => $sedes,
            ],
        ]);
    }

    /**
     * Obtiene estadísticas de áreas.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Area::count(),
                'activos' => Area::whereNull('deleted_at')->count(),
                'eliminados' => Area::onlyTrashed()->count(),
            ],
            'por_estado' => [
                'activos' => Area::where('status', 1)->count(),
                'inactivos' => Area::where('status', 0)->count(),
            ],
            'con_sedes' => [
                'con_sedes' => Area::has('sedes')->count(),
                'sin_sedes' => Area::doesntHave('sedes')->count(),
            ],
            'por_sede' => Area::with('sedes')
                ->selectRaw('areas.id, areas.nombre, count(area_sede.sede_id) as total_sedes')
                ->leftJoin('area_sede', 'areas.id', '=', 'area_sede.area_id')
                ->groupBy('areas.id', 'areas.nombre')
                ->orderBy('total_sedes', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'area' => $item->nombre,
                        'total_sedes' => $item->total_sedes,
                    ];
                }),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}
