<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreAsistenciaClaseProgramadaRequest;
use App\Http\Requests\Api\Academico\UpdateAsistenciaClaseProgramadaRequest;
use App\Http\Resources\Api\Academico\AsistenciaClaseProgramadaResource;
use App\Models\Academico\AsistenciaClaseProgramada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsistenciaClaseProgramadaController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_claseProgramar')->only(['index', 'show', 'generarAutomaticas']);
        $this->middleware('permission:aca_claseProgramar')->only(['store', 'update', 'destroy']);
    }

    /**
     * Muestra una lista de clases programadas.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'grupo_id', 'ciclo_id', 'fecha_clase', 'estado', 'include_trashed', 'only_trashed'
        ]);

        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['grupo', 'ciclo'];

        $clases = AsistenciaClaseProgramada::withFilters($filters)
            ->withRelationsAndCounts($relations, false)
            ->withSorting($request->get('sort_by', 'fecha_clase'), $request->get('sort_direction', 'desc'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => AsistenciaClaseProgramadaResource::collection($clases),
            'meta' => [
                'current_page' => $clases->currentPage(),
                'last_page' => $clases->lastPage(),
                'per_page' => $clases->perPage(),
                'total' => $clases->total(),
                'from' => $clases->firstItem(),
                'to' => $clases->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena una nueva clase programada manualmente.
     *
     * @param StoreAsistenciaClaseProgramadaRequest $request
     * @return JsonResponse
     */
    public function store(StoreAsistenciaClaseProgramadaRequest $request): JsonResponse
    {
        try {
            $clase = AsistenciaClaseProgramada::create([
                'grupo_id' => $request->grupo_id,
                'ciclo_id' => $request->ciclo_id,
                'fecha_clase' => $request->fecha_clase,
                'hora_inicio' => $request->hora_inicio,
                'hora_fin' => $request->hora_fin,
                'duracion_horas' => $request->duracion_horas,
                'estado' => $request->estado ?? 'programada',
                'observaciones' => $request->observaciones,
                'creado_por_id' => auth()->id(),
                'fecha_programacion' => now(),
            ]);

            return response()->json([
                'message' => 'Clase programada creada exitosamente.',
                'data' => new AsistenciaClaseProgramadaResource($clase->load(['grupo', 'ciclo'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la clase programada.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Genera clases automáticamente para un grupo y ciclo.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generarAutomaticas(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'grupo_id' => 'required|integer|exists:grupos,id',
                'ciclo_id' => 'required|integer|exists:ciclos,id',
            ]);

            // Este método será implementado en el servicio GenerarClasesProgramadasService
            // Por ahora retornamos un mensaje indicando que está pendiente
            return response()->json([
                'message' => 'La generación automática de clases será implementada en el servicio GenerarClasesProgramadasService.',
                'note' => 'Este endpoint requiere la implementación del servicio para generar clases basadas en horarios y fechas del grupo.',
            ], 501);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al generar las clases automáticas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra una clase programada específica.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $clase = AsistenciaClaseProgramada::withRelations(['grupo', 'ciclo', 'creadoPor', 'asistencias'])
            ->findOrFail($id);

        return response()->json([
            'data' => new AsistenciaClaseProgramadaResource($clase),
        ]);
    }

    /**
     * Actualiza una clase programada específica.
     *
     * @param UpdateAsistenciaClaseProgramadaRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateAsistenciaClaseProgramadaRequest $request, int $id): JsonResponse
    {
        try {
            $clase = AsistenciaClaseProgramada::findOrFail($id);

            $clase->update($request->validated());

            return response()->json([
                'message' => 'Clase programada actualizada exitosamente.',
                'data' => new AsistenciaClaseProgramadaResource($clase->load(['grupo', 'ciclo'])),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la clase programada.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina una clase programada.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $clase = AsistenciaClaseProgramada::findOrFail($id);
            $clase->delete();

            return response()->json([
                'message' => 'Clase programada eliminada exitosamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la clase programada.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
