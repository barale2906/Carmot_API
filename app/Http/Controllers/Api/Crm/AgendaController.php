<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\StoreAgendaRequest;
use App\Http\Requests\Api\Crm\UpdateAgendaRequest;
use App\Http\Resources\Api\Crm\AgendaResource;
use App\Models\Crm\Agenda;
use App\Models\Crm\Referido;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AgendaController extends Controller
{
    /**
     * Constructor del controlador.
     */
    public function __construct()
    {
        $this->middleware('permission:crm_agendas')->only(['index', 'show', 'filters', 'statistics']);
        $this->middleware('permission:crm_agendaCrear')->only(['store']);
        $this->middleware('permission:crm_agendaEditar')->only(['update']);
        $this->middleware('permission:crm_agendaInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista de las agendas.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'referido_id', 'agendador_id', 'status', 'fecha_desde', 'fecha_hasta']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['referido', 'agendador'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && str_contains($request->with, 'seguimientos');

        // Construir query usando scopes
        $agendas = Agenda::withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => AgendaResource::collection($agendas),
            'meta' => [
                'current_page' => $agendas->currentPage(),
                'last_page' => $agendas->lastPage(),
                'per_page' => $agendas->perPage(),
                'total' => $agendas->total(),
                'from' => $agendas->firstItem(),
                'to' => $agendas->lastItem(),
            ],
        ]);
    }

    /**
     * Almacena una nueva agenda en la base de datos.
     *
     * @param StoreAgendaRequest $request
     * @return JsonResponse
     */
    public function store(StoreAgendaRequest $request): JsonResponse
    {
        $agenda = Agenda::create([
            'referido_id' => $request->referido_id,
            'agendador_id' => $request->agendador_id,
            'fecha' => $request->fecha,
            'hora' => $request->hora,
            'jornada' => $request->jornada,
            'status' => $request->status ?? 0, // Por defecto estado "Agendado"
        ]);

        $agenda->load(['referido', 'agendador']);

        return response()->json([
            'message' => 'Agenda creada exitosamente.',
            'data' => new AgendaResource($agenda),
        ], 201);
    }

    /**
     * Muestra la agenda especificada.
     *
     * @param Request $request
     * @param Agenda $agenda
     * @return JsonResponse
     */
    public function show(Request $request, Agenda $agenda): JsonResponse
    {
        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['referido', 'agendador'];

        // Cargar relaciones y contadores usando el modelo
        $agenda->load($relations);

        return response()->json([
            'data' => new AgendaResource($agenda),
        ]);
    }

    /**
     * Actualiza la agenda especificada en la base de datos.
     *
     * @param UpdateAgendaRequest $request
     * @param Agenda $agenda
     * @return JsonResponse
     */
    public function update(UpdateAgendaRequest $request, Agenda $agenda): JsonResponse
    {
        $agenda->update($request->only([
            'referido_id',
            'agendador_id',
            'fecha',
            'hora',
            'jornada',
            'status',
        ]));

        $agenda->load(['referido', 'agendador']);

        return response()->json([
            'message' => 'Agenda actualizada exitosamente.',
            'data' => new AgendaResource($agenda),
        ]);
    }

    /**
     * Elimina la agenda especificada de la base de datos (soft delete).
     *
     * @param Agenda $agenda
     * @return JsonResponse
     */
    public function destroy(Agenda $agenda): JsonResponse
    {
        $agenda->delete(); // Soft delete

        return response()->json([
            'message' => 'Agenda eliminada exitosamente.',
        ]);
    }

    /**
     * Restaura una agenda eliminada (soft delete).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $agenda = Agenda::onlyTrashed()->findOrFail($id);
        $agenda->restore();

        return response()->json([
            'message' => 'Agenda restaurada exitosamente.',
            'data' => new AgendaResource($agenda->load(['referido', 'agendador'])),
        ]);
    }

    /**
     * Elimina permanentemente una agenda.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $agenda = Agenda::onlyTrashed()->findOrFail($id);
        $agenda->forceDelete();

        return response()->json([
            'message' => 'Agenda eliminada permanentemente.',
        ]);
    }

    /**
     * Obtiene solo las agendas eliminadas (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        // Preparar filtros
        $filters = $request->only(['search', 'referido_id', 'agendador_id', 'status', 'fecha_desde', 'fecha_hasta']);

        // Preparar relaciones
        $relations = $request->has('with')
            ? explode(',', $request->with)
            : ['referido', 'agendador'];

        // Verificar si incluir contadores
        $includeCounts = $request->has('with') && str_contains($request->with, 'seguimientos');

        // Construir query usando scopes (solo eliminados)
        $agendas = Agenda::onlyTrashed()
            ->withFilters($filters)
            ->withRelationsAndCounts($relations, $includeCounts)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => AgendaResource::collection($agendas),
            'meta' => [
                'current_page' => $agendas->currentPage(),
                'last_page' => $agendas->lastPage(),
                'per_page' => $agendas->perPage(),
                'total' => $agendas->total(),
                'from' => $agendas->firstItem(),
                'to' => $agendas->lastItem(),
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
        $referidos = Referido::select('id', 'nombre', 'celular')->get();
        $agendadores = User::select('id', 'name', 'email')->get();

        return response()->json([
            'data' => [
                'status_options' => [
                    0 => 'Agendado',
                    1 => 'Asistió',
                    2 => 'No asistió',
                    3 => 'Reprogramó',
                    4 => 'Canceló',
                ],
                'jornada_options' => [
                    'am' => 'AM',
                    'pm' => 'PM',
                ],
                'referidos' => $referidos,
                'agendadores' => $agendadores,
            ],
        ]);
    }

    /**
     * Obtiene estadísticas de agendas.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Agenda::count(),
                'activos' => Agenda::whereNull('deleted_at')->count(),
                'eliminados' => Agenda::onlyTrashed()->count(),
            ],
            'por_status' => [
                'agendados' => Agenda::where('status', 0)->count(),
                'asistieron' => Agenda::where('status', 1)->count(),
                'no_asistieron' => Agenda::where('status', 2)->count(),
                'reprogramaron' => Agenda::where('status', 3)->count(),
                'cancelaron' => Agenda::where('status', 4)->count(),
            ],
            'por_jornada' => Agenda::selectRaw('jornada, count(*) as total')
                ->groupBy('jornada')
                ->orderBy('total', 'desc')
                ->get(),
            'por_agendador' => Agenda::with('agendador')
                ->selectRaw('agendador_id, count(*) as total')
                ->groupBy('agendador_id')
                ->orderBy('total', 'desc')
                ->get(),
            'por_fecha' => Agenda::selectRaw('DATE(fecha) as fecha, count(*) as total')
                ->groupBy('fecha')
                ->orderBy('fecha', 'desc')
                ->limit(30)
                ->get(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }
}
