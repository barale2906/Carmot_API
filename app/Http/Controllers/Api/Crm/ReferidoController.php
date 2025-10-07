<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\StoreReferidoRequest;
use App\Http\Requests\Api\Crm\UpdateReferidoRequest;
use App\Http\Resources\Api\Crm\ReferidoResource;
use App\Models\Crm\Referido;
use App\Models\Academico\Curso;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReferidoController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:crm_referidos')->only(['index', 'show', 'filters', 'statistics']);
        $this->middleware('permission:crm_referidoCrear')->only(['store']);
        $this->middleware('permission:crm_referidoEditar')->only(['update']);
        $this->middleware('permission:crm_referidoInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista de los referidos.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'ciudad', 'status', 'curso_id', 'gestor_id']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['curso', 'gestor'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && str_contains($request->with, 'seguimientos');

        // Construir query usando scopes
        $referidos = Referido::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ReferidoResource::collection($referidos),
            'meta' => [
                'current_page' => $referidos->currentPage(),
                'last_page' => $referidos->lastPage(),
                'per_page' => $referidos->perPage(),
                'total' => $referidos->total(),
                'from' => $referidos->firstItem(),
                'to' => $referidos->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena un nuevo referido en la base de datos.
     *
     * @param StoreReferidoRequest $request
     * @return JsonResponse
     */
    public function store(StoreReferidoRequest $request): JsonResponse
    {
        $referido = Referido::create([
            'curso_id' => $request->curso_id,
            'gestor_id' => $request->gestor_id,
            'nombre' => $request->nombre,
            'celular' => $request->celular,
            'ciudad' => $request->ciudad,
            'status' => $request->status ?? 0, // Por defecto estado "Creado"
        ]);

        $referido->load(['curso', 'gestor']);

        return response()->json([
            'message' => 'Referido creado exitosamente.',
            'data' => new ReferidoResource($referido),
        ], 201);
    }

    /**
     * Muestra el referido especificado.
     *
     * @param Request $request
     * @param Referido $referido
     * @return JsonResponse
     */
    public function show(Request $request, Referido $referido): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['curso', 'gestor', 'seguimientos'];

        // Cargar relaciones y contadores usando el modelo
        $referido->load($relations);
        $referido->loadCount('seguimientos');

        return response()->json([
            'data' => new ReferidoResource($referido),
        ]);
    }

    /**
     * Actualiza el referido especificado en la base de datos.
     *
     * @param UpdateReferidoRequest $request
     * @param Referido $referido
     * @return JsonResponse
     */
    public function update(UpdateReferidoRequest $request, Referido $referido): JsonResponse
    {
        $referido->update($request->only([
            'curso_id',
            'gestor_id',
            'nombre',
            'celular',
            'ciudad',
            'status',
        ]));

        $referido->load(['curso', 'gestor']);

        return response()->json([
            'message' => 'Referido actualizado exitosamente.',
            'data' => new ReferidoResource($referido),
        ]);
    }

    /**
     * Elimina el referido especificado de la base de datos (soft delete).
     *
     * @param Referido $referido
     * @return JsonResponse
     */
    public function destroy(Referido $referido): JsonResponse
    {
        // Verificar si tiene seguimientos asociados
        if ($referido->seguimientos()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el referido porque tiene seguimientos asociados.',
            ], 422);
        }

        $referido->delete(); // Soft delete

        return response()->json([
            'message' => 'Referido eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un referido eliminado (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $referido = Referido::onlyTrashed()->findOrFail($id);
        $referido->restore();

        return response()->json([
            'message' => 'Referido restaurado exitosamente.',
            'data' => new ReferidoResource($referido->load(['curso', 'gestor'])),
        ]);
    }

    /**
     * Elimina permanentemente un referido.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $referido = Referido::onlyTrashed()->findOrFail($id);

        // Verificar si tiene seguimientos asociados
        if ($referido->seguimientos()->withTrashed()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el referido porque tiene seguimientos asociados.',
            ], 422);
        }

        $referido->forceDelete();

        return response()->json([
            'message' => 'Referido eliminado permanentemente.',
        ]);
    }

    /**
     * Obtiene solo los referidos eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'ciudad', 'status', 'curso_id', 'gestor_id']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['curso', 'gestor'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && str_contains($request->with, 'seguimientos');

        // Construir query usando scopes (solo eliminados)
        $referidos = Referido::onlyTrashed()
            ->withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ReferidoResource::collection($referidos),
            'meta' => [
                'current_page' => $referidos->currentPage(),
                'last_page' => $referidos->lastPage(),
                'per_page' => $referidos->perPage(),
                'total' => $referidos->total(),
                'from' => $referidos->firstItem(),
                'to' => $referidos->lastItem(),
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
        $ciudades = Referido::distinct()->pluck('ciudad')->filter()->sort()->values();
        $cursos = Curso::select('id', 'nombre')->get();
        $gestores = User::select('id', 'name', 'email')->get();

        return response()->json([
            'data' => [
                'status_options' => [
                    0 => 'Creado',
                    1 => 'Interesado',
                    2 => 'Pendiente por matricular',
                    3 => 'Matriculado',
                    4 => 'Declinado',
                ],
                'ciudades' => $ciudades,
                'cursos' => $cursos,
                'gestores' => $gestores,
            ],
        ]);
    }

    /**
     * Obtiene estadísticas de referidos.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Referido::count(),
                'activos' => Referido::whereNull('deleted_at')->count(),
                'eliminados' => Referido::onlyTrashed()->count(),
            ],
            'por_status' => [
                'creados' => Referido::where('status', 0)->count(),
                'interesados' => Referido::where('status', 1)->count(),
                'pendientes' => Referido::where('status', 2)->count(),
                'matriculados' => Referido::where('status', 3)->count(),
                'declinados' => Referido::where('status', 4)->count(),
            ],
            'por_ciudad' => Referido::selectRaw('ciudad, count(*) as total')
                ->groupBy('ciudad')
                ->orderBy('total', 'desc')
                ->get(),
            'por_curso' => Referido::with('curso')
                ->selectRaw('curso_id, count(*) as total')
                ->groupBy('curso_id')
                ->orderBy('total', 'desc')
                ->get(),
            'por_gestor' => Referido::with('gestor')
                ->selectRaw('gestor_id, count(*) as total')
                ->groupBy('gestor_id')
                ->orderBy('total', 'desc')
                ->get(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}
