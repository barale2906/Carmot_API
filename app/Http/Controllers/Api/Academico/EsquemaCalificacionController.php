<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreEsquemaCalificacionRequest;
use App\Http\Requests\Api\Academico\UpdateEsquemaCalificacionRequest;
use App\Http\Resources\Api\Academico\EsquemaCalificacionResource;
use App\Models\Academico\EsquemaCalificacion;
use App\Models\Academico\TipoNotaEsquema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EsquemaCalificacionController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_esquemas')->only(['index', 'show', 'getByModuloGrupo']);
        $this->middleware('permission:aca_esquemaCrear')->only(['store']);
        $this->middleware('permission:aca_esquemaEditar')->only(['update']);
        $this->middleware('permission:aca_esquemaInactivar')->only(['destroy', 'restore']);
    }

    /**
     * Muestra una lista de esquemas de calificación.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'status', 'modulo_id', 'grupo_id', 'profesor_id',
            'include_trashed', 'only_trashed'
        ]);

        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['modulo', 'profesor', 'tiposNota'];

        $includeCounts = $request->has('with') && (
            str_contains($request->with, 'tiposNota') ||
            str_contains($request->with, 'notasEstudiantes')
        );

        $esquemas = EsquemaCalificacion::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => EsquemaCalificacionResource::collection($esquemas),
            'meta' => [
                'current_page' => $esquemas->currentPage(),
                'last_page' => $esquemas->lastPage(),
                'per_page' => $esquemas->perPage(),
                'total' => $esquemas->total(),
                'from' => $esquemas->firstItem(),
                'to' => $esquemas->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena un nuevo esquema de calificación.
     *
     * @param StoreEsquemaCalificacionRequest $request
     * @return JsonResponse
     */
    public function store(StoreEsquemaCalificacionRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Crear el esquema
            $esquema = EsquemaCalificacion::create([
                'modulo_id' => $request->modulo_id,
                'grupo_id' => $request->grupo_id,
                'profesor_id' => auth()->id(),
                'nombre_esquema' => $request->nombre_esquema,
                'descripcion' => $request->descripcion,
                'condicion_aplicacion' => $request->condicion_aplicacion,
                'status' => $request->status ?? 1,
            ]);

            // Crear los tipos de nota
            foreach ($request->tipos_nota as $tipoNota) {
                TipoNotaEsquema::create([
                    'esquema_calificacion_id' => $esquema->id,
                    'nombre_tipo' => $tipoNota['nombre_tipo'],
                    'peso' => $tipoNota['peso'],
                    'orden' => $tipoNota['orden'],
                    'nota_minima' => $tipoNota['nota_minima'] ?? 0,
                    'nota_maxima' => $tipoNota['nota_maxima'] ?? 5,
                    'descripcion' => $tipoNota['descripcion'] ?? null,
                ]);
            }

            DB::commit();

            $esquema->load(['modulo', 'grupo', 'profesor', 'tiposNota']);

            return response()->json([
                'message' => 'Esquema de calificación creado exitosamente.',
                'data' => new EsquemaCalificacionResource($esquema),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el esquema de calificación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra el esquema de calificación especificado.
     *
     * @param EsquemaCalificacion $esquemaCalificacion
     * @param Request $request
     * @return JsonResponse
     */
    public function show(EsquemaCalificacion $esquemaCalificacion, Request $request): JsonResponse
    {
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['modulo', 'grupo', 'profesor', 'tiposNota'];

        $esquemaCalificacion->load($relations);

        return response()->json([
            'data' => new EsquemaCalificacionResource($esquemaCalificacion),
        ]);
    }

    /**
     * Actualiza el esquema de calificación especificado.
     *
     * @param UpdateEsquemaCalificacionRequest $request
     * @param EsquemaCalificacion $esquemaCalificacion
     * @return JsonResponse
     */
    public function update(UpdateEsquemaCalificacionRequest $request, EsquemaCalificacion $esquemaCalificacion): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Actualizar el esquema
            $esquemaCalificacion->update($request->only([
                'modulo_id', 'grupo_id', 'nombre_esquema', 'descripcion',
                'condicion_aplicacion', 'status'
            ]));

            // Si se proporcionan tipos de nota, actualizarlos
            if ($request->has('tipos_nota')) {
                $tiposIds = collect($request->tipos_nota)->pluck('id')->filter();

                // Eliminar tipos que no están en la lista
                $esquemaCalificacion->tiposNota()
                    ->whereNotIn('id', $tiposIds)
                    ->delete();

                // Actualizar o crear tipos
                foreach ($request->tipos_nota as $tipoNota) {
                    if (isset($tipoNota['id'])) {
                        // Actualizar existente
                        TipoNotaEsquema::where('id', $tipoNota['id'])
                            ->where('esquema_calificacion_id', $esquemaCalificacion->id)
                            ->update([
                                'nombre_tipo' => $tipoNota['nombre_tipo'],
                                'peso' => $tipoNota['peso'],
                                'orden' => $tipoNota['orden'],
                                'nota_minima' => $tipoNota['nota_minima'] ?? 0,
                                'nota_maxima' => $tipoNota['nota_maxima'] ?? 5,
                                'descripcion' => $tipoNota['descripcion'] ?? null,
                            ]);
                    } else {
                        // Crear nuevo
                        TipoNotaEsquema::create([
                            'esquema_calificacion_id' => $esquemaCalificacion->id,
                            'nombre_tipo' => $tipoNota['nombre_tipo'],
                            'peso' => $tipoNota['peso'],
                            'orden' => $tipoNota['orden'],
                            'nota_minima' => $tipoNota['nota_minima'] ?? 0,
                            'nota_maxima' => $tipoNota['nota_maxima'] ?? 5,
                            'descripcion' => $tipoNota['descripcion'] ?? null,
                        ]);
                    }
                }
            }

            DB::commit();

            $esquemaCalificacion->load(['modulo', 'grupo', 'profesor', 'tiposNota']);

            return response()->json([
                'message' => 'Esquema de calificación actualizado exitosamente.',
                'data' => new EsquemaCalificacionResource($esquemaCalificacion),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar el esquema de calificación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina el esquema de calificación especificado (soft delete).
     *
     * @param EsquemaCalificacion $esquemaCalificacion
     * @return JsonResponse
     */
    public function destroy(EsquemaCalificacion $esquemaCalificacion): JsonResponse
    {
        // Verificar si tiene notas registradas
        if ($esquemaCalificacion->notasEstudiantes()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el esquema porque tiene notas registradas.',
            ], 422);
        }

        $esquemaCalificacion->delete();

        return response()->json([
            'message' => 'Esquema de calificación eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un esquema eliminado (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $esquema = EsquemaCalificacion::onlyTrashed()->findOrFail($id);
        $esquema->restore();

        return response()->json([
            'message' => 'Esquema de calificación restaurado exitosamente.',
            'data' => new EsquemaCalificacionResource($esquema->load(['modulo', 'profesor', 'tiposNota'])),
        ]);
    }

    /**
     * Obtiene el esquema activo para un módulo y grupo específicos.
     *
     * @param Request $request
     * @param int $moduloId
     * @param int|null $grupoId
     * @return JsonResponse
     */
    public function getByModuloGrupo(Request $request, int $moduloId, ?int $grupoId = null): JsonResponse
    {
        $esquema = EsquemaCalificacion::activoParaModuloGrupo($moduloId, $grupoId)->first();

        if (!$esquema) {
            return response()->json([
                'message' => 'No se encontró un esquema activo para este módulo y grupo.',
                'data' => null,
            ], 404);
        }

        $esquema->load(['modulo', 'grupo', 'profesor', 'tiposNota']);

        return response()->json([
            'data' => new EsquemaCalificacionResource($esquema),
        ]);
    }
}
