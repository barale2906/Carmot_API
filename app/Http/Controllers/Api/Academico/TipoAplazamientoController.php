<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreTipoAplazamientoRequest;
use App\Http\Requests\Api\Academico\UpdateTipoAplazamientoRequest;
use App\Http\Resources\Api\Academico\TipoAplazamientoResource;
use App\Models\Academico\TipoAplazamiento;
use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipoAplazamientoController extends Controller
{
    use HasActiveStatus, HasActiveStatusValidation;

    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:aca_tiposAplazamiento')->only(['index', 'show', 'filters']);
        $this->middleware('permission:aca_tipoAplazamientoCrear')->only(['store']);
        $this->middleware('permission:aca_tipoAplazamientoEditar')->only(['update']);
        $this->middleware('permission:aca_tipoAplazamientoInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Lista paginada de tipos de aplazamiento.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters   = $request->only(['search', 'status', 'include_trashed', 'only_trashed']);
        $relations = $request->has('with') ? explode(',', $request->with) : [];

        $tipos = TipoAplazamiento::withFilters($filters)
            ->withRelationsAndCounts($relations, in_array('aplazamientos', $relations))
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => TipoAplazamientoResource::collection($tipos),
            'meta' => [
                'current_page' => $tipos->currentPage(),
                'last_page'    => $tipos->lastPage(),
                'per_page'     => $tipos->perPage(),
                'total'        => $tipos->total(),
                'from'         => $tipos->firstItem(),
                'to'           => $tipos->lastItem(),
            ],
        ]);
    }

    /**
     * Crea un nuevo tipo de aplazamiento.
     *
     * @param StoreTipoAplazamientoRequest $request
     * @return JsonResponse
     */
    public function store(StoreTipoAplazamientoRequest $request): JsonResponse
    {
        $tipo = TipoAplazamiento::create([
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion,
            'status'      => $request->status ?? 1,
        ]);

        return response()->json([
            'message' => 'Tipo de aplazamiento creado exitosamente.',
            'data'    => new TipoAplazamientoResource($tipo),
        ], 201);
    }

    /**
     * Muestra un tipo de aplazamiento específico.
     *
     * @param TipoAplazamiento $tipoAplazamiento
     * @return JsonResponse
     */
    public function show(TipoAplazamiento $tipoAplazamiento): JsonResponse
    {
        return response()->json([
            'data' => new TipoAplazamientoResource($tipoAplazamiento),
        ]);
    }

    /**
     * Actualiza un tipo de aplazamiento.
     *
     * @param UpdateTipoAplazamientoRequest $request
     * @param TipoAplazamiento              $tipoAplazamiento
     * @return JsonResponse
     */
    public function update(UpdateTipoAplazamientoRequest $request, TipoAplazamiento $tipoAplazamiento): JsonResponse
    {
        $tipoAplazamiento->update($request->only(['nombre', 'descripcion', 'status']));

        return response()->json([
            'message' => 'Tipo de aplazamiento actualizado exitosamente.',
            'data'    => new TipoAplazamientoResource($tipoAplazamiento->fresh()),
        ]);
    }

    /**
     * Elimina (soft delete) un tipo de aplazamiento.
     *
     * @param TipoAplazamiento $tipoAplazamiento
     * @return JsonResponse
     */
    public function destroy(TipoAplazamiento $tipoAplazamiento): JsonResponse
    {
        if ($tipoAplazamiento->aplazamientos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el tipo de aplazamiento porque tiene aplazamientos asociados.',
            ], 422);
        }

        $tipoAplazamiento->delete();

        return response()->json([
            'message' => 'Tipo de aplazamiento eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un tipo de aplazamiento eliminado.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $tipo = TipoAplazamiento::onlyTrashed()->findOrFail($id);
        $tipo->restore();

        return response()->json([
            'message' => 'Tipo de aplazamiento restaurado exitosamente.',
            'data'    => new TipoAplazamientoResource($tipo),
        ]);
    }

    /**
     * Elimina permanentemente un tipo de aplazamiento.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $tipo = TipoAplazamiento::onlyTrashed()->findOrFail($id);

        if ($tipo->aplazamientos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el tipo porque tiene aplazamientos asociados.',
            ], 422);
        }

        $tipo->forceDelete();

        return response()->json([
            'message' => 'Tipo de aplazamiento eliminado permanentemente.',
        ]);
    }

    /**
     * Lista los tipos de aplazamiento eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters              = $request->only(['search', 'status']);
        $filters['only_trashed'] = true;

        $tipos = TipoAplazamiento::withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => TipoAplazamientoResource::collection($tipos),
            'meta' => [
                'current_page' => $tipos->currentPage(),
                'last_page'    => $tipos->lastPage(),
                'per_page'     => $tipos->perPage(),
                'total'        => $tipos->total(),
                'from'         => $tipos->firstItem(),
                'to'           => $tipos->lastItem(),
            ],
        ]);
    }

    /**
     * Opciones de filtros disponibles.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status_options' => self::getActiveStatusOptions(),
            ],
        ]);
    }
}
