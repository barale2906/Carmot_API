<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreAsistenciaConfiguracionRequest;
use App\Http\Requests\Api\Academico\UpdateAsistenciaConfiguracionRequest;
use App\Http\Resources\Api\Academico\AsistenciaConfiguracionResource;
use App\Models\Academico\AsistenciaConfiguracion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsistenciaConfiguracionController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_configuracionAsistencia')->only(['index', 'show']);
        $this->middleware('permission:aca_configuracionAsistencia')->only(['store', 'update', 'destroy']);
    }

    /**
     * Muestra una lista de configuraciones.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'curso_id', 'modulo_id', 'fecha_inicio_vigencia', 'fecha_fin_vigencia', 'include_trashed', 'only_trashed'
        ]);

        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['curso', 'modulo'];

        $configuraciones = AsistenciaConfiguracion::withFilters($filters)
            ->withRelationsAndCounts($relations, false)
            ->withSorting($request->get('sort_by', 'created_at'), $request->get('sort_direction', 'desc'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => AsistenciaConfiguracionResource::collection($configuraciones),
            'meta' => [
                'current_page' => $configuraciones->currentPage(),
                'last_page' => $configuraciones->lastPage(),
                'per_page' => $configuraciones->perPage(),
                'total' => $configuraciones->total(),
                'from' => $configuraciones->firstItem(),
                'to' => $configuraciones->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena una nueva configuración.
     *
     * @param StoreAsistenciaConfiguracionRequest $request
     * @return JsonResponse
     */
    public function store(StoreAsistenciaConfiguracionRequest $request): JsonResponse
    {
        try {
            $configuracion = AsistenciaConfiguracion::create($request->validated());

            return response()->json([
                'message' => 'Configuración creada exitosamente.',
                'data' => new AsistenciaConfiguracionResource($configuracion->load(['curso', 'modulo'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la configuración.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra una configuración específica.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $configuracion = AsistenciaConfiguracion::withRelations(['curso', 'modulo'])
            ->findOrFail($id);

        return response()->json([
            'data' => new AsistenciaConfiguracionResource($configuracion),
        ]);
    }

    /**
     * Actualiza una configuración específica.
     *
     * @param UpdateAsistenciaConfiguracionRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateAsistenciaConfiguracionRequest $request, int $id): JsonResponse
    {
        try {
            $configuracion = AsistenciaConfiguracion::findOrFail($id);

            $configuracion->update($request->validated());

            return response()->json([
                'message' => 'Configuración actualizada exitosamente.',
                'data' => new AsistenciaConfiguracionResource($configuracion->load(['curso', 'modulo'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la configuración.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina una configuración.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $configuracion = AsistenciaConfiguracion::findOrFail($id);
            $configuracion->delete();

            return response()->json([
                'message' => 'Configuración eliminada exitosamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la configuración.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
