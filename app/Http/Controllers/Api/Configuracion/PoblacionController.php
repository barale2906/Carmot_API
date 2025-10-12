<?php

namespace App\Http\Controllers\Api\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Configuracion\PoblacionResource;
use App\Models\Configuracion\Poblacion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PoblacionController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:co_poblaciones')->only(['index', 'show']);
    }

    /**
     * Muestra una lista de las poblaciones.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'pais', 'provincia']);

        // Preparar relaciones (poblaciones no tienen relaciones complejas)
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : [];

        // Construir query usando scopes
        $poblaciones = Poblacion::withFilters($filters)
            ->withRelationsAndCounts($relations, false)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => PoblacionResource::collection($poblaciones),
            'meta' => [
                'current_page' => $poblaciones->currentPage(),
                'last_page' => $poblaciones->lastPage(),
                'per_page' => $poblaciones->perPage(),
                'total' => $poblaciones->total(),
                'from' => $poblaciones->firstItem(),
                'to' => $poblaciones->lastItem(),
            ],
        ]);
    }

    /**
     * Muestra la población especificada.
     *
     * @param Request $request
     * @param Poblacion $poblacion
     * @return JsonResponse
     */
    public function show(Request $request, Poblacion $poblacion): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : [];

        // Cargar relaciones si es necesario
        if (!empty($relations)) {
            $poblacion->load($relations);
        }

        return response()->json([
            'data' => new PoblacionResource($poblacion),
        ]);
    }

    /**
     * Obtiene las opciones de filtros disponibles.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        $paises = Poblacion::distinct()->pluck('pais')->filter()->sort()->values();
        $provincias = Poblacion::distinct()->pluck('provincia')->filter()->sort()->values();

        return response()->json([
            'data' => [
                'paises' => $paises,
                'provincias' => $provincias,
            ],
        ]);
    }

    /**
     * Obtiene estadísticas de poblaciones.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Poblacion::count(),
            ],
            'por_pais' => Poblacion::selectRaw('pais, count(*) as total')
                ->groupBy('pais')
                ->orderBy('total', 'desc')
                ->get(),
            'por_provincia' => Poblacion::selectRaw('provincia, count(*) as total')
                ->groupBy('provincia')
                ->orderBy('total', 'desc')
                ->get(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}
