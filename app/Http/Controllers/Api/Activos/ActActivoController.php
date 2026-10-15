<?php

namespace App\Http\Controllers\Api\Activos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Activos\CambiarEstadoActActivoRequest;
use App\Http\Requests\Api\Activos\StoreActActivoRequest;
use App\Http\Requests\Api\Activos\TrasladarActActivoRequest;
use App\Http\Requests\Api\Activos\UpdateActActivoRequest;
use App\Http\Resources\Api\Activos\ActActivoResource;
use App\Http\Resources\Api\Activos\ActMovimientoResource;
use App\Models\Activos\ActActivo;
use App\Models\Activos\ActCategoria;
use App\Models\Configuracion\Sede;
use App\Models\User;
use App\Services\Activos\ActActivoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Activos fijos por sede y área: registro, edición, traslados, cambios de estado e historial.
 * Cada usuario solo ve y opera los activos de sus sedes asignadas.
 */
class ActActivoController extends Controller
{
    private const RELACIONES = ['categoria', 'sede', 'area', 'responsable'];

    /**
     * Registra los middlewares de permisos del módulo.
     *
     * @param ActActivoService $activos
     */
    public function __construct(private readonly ActActivoService $activos)
    {
        $this->middleware('permission:act_activos')->only(['index', 'show', 'filters', 'statistics', 'movimientos']);
        $this->middleware('permission:act_activoCrear')->only(['store']);
        $this->middleware('permission:act_activoEditar')->only(['update', 'cambiarEstado']);
        $this->middleware('permission:act_activoTrasladar')->only(['trasladar']);
        $this->middleware('permission:act_activoInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Lista paginada de activos de las sedes del usuario. Oculta los de baja salvo que se filtre por ese estado.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $activos = ActActivo::deSedes($request->user()->idsSedesAccesibles())
            ->withFilters($request->only(['search', 'sede_id', 'area_id', 'categoria_id', 'responsable_id', 'estado']))
            ->with(self::RELACIONES)
            ->withSorting($request->get('sort_by', 'codigo'), $request->get('sort_direction', 'asc'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ActActivoResource::collection($activos),
            'meta' => [
                'current_page' => $activos->currentPage(),
                'last_page'    => $activos->lastPage(),
                'per_page'     => $activos->perPage(),
                'total'        => $activos->total(),
                'from'         => $activos->firstItem(),
                'to'           => $activos->lastItem(),
            ],
        ]);
    }

    /**
     * Registra un activo y su movimiento de ingreso.
     *
     * @param StoreActActivoRequest $request
     * @return JsonResponse
     */
    public function store(StoreActActivoRequest $request): JsonResponse
    {
        $activo = $this->activos->registrar($request->validated(), $request->user()->id);

        return response()->json([
            'message' => 'Activo registrado exitosamente.',
            'data'    => new ActActivoResource($activo->load(self::RELACIONES)),
        ], 201);
    }

    /**
     * Ficha del activo con su historial.
     *
     * @param Request $request
     * @param ActActivo $activo
     * @return JsonResponse
     */
    public function show(Request $request, ActActivo $activo): JsonResponse
    {
        $this->autorizarSede($request, $activo);

        $activo->load([...self::RELACIONES, 'movimientos.sedeOrigen', 'movimientos.areaOrigen', 'movimientos.sedeDestino', 'movimientos.areaDestino', 'movimientos.usuario']);

        return response()->json([
            'data' => new ActActivoResource($activo),
        ]);
    }

    /**
     * Actualiza los datos descriptivos del activo (no su ubicación ni su estado).
     *
     * @param UpdateActActivoRequest $request
     * @param ActActivo $activo
     * @return JsonResponse
     */
    public function update(UpdateActActivoRequest $request, ActActivo $activo): JsonResponse
    {
        $this->autorizarSede($request, $activo);

        $activo->update($request->validated());

        return response()->json([
            'message' => 'Activo actualizado exitosamente.',
            'data'    => new ActActivoResource($activo->load(self::RELACIONES)),
        ]);
    }

    /**
     * Traslada el activo a otra sede o área.
     *
     * @param TrasladarActActivoRequest $request
     * @param ActActivo $activo
     * @return JsonResponse
     */
    public function trasladar(TrasladarActActivoRequest $request, ActActivo $activo): JsonResponse
    {
        $this->autorizarSede($request, $activo);

        $this->activos->trasladar($activo, $request->validated(), $request->user()->id);

        return response()->json([
            'message' => 'Activo trasladado exitosamente.',
            'data'    => new ActActivoResource($activo->fresh(self::RELACIONES)),
        ]);
    }

    /**
     * Cambia el estado del activo (operativo, mantenimiento, disponible o baja definitiva).
     *
     * @param CambiarEstadoActActivoRequest $request
     * @param ActActivo $activo
     * @return JsonResponse
     */
    public function cambiarEstado(CambiarEstadoActActivoRequest $request, ActActivo $activo): JsonResponse
    {
        $this->autorizarSede($request, $activo);

        $this->activos->cambiarEstado($activo, $request->estado, $request->fecha, $request->motivo, $request->user()->id);

        return response()->json([
            'message' => 'Estado del activo actualizado.',
            'data'    => new ActActivoResource($activo->fresh(self::RELACIONES)),
        ]);
    }

    /**
     * Historial de ingresos, traslados y cambios de estado del activo.
     *
     * @param Request $request
     * @param ActActivo $activo
     * @return JsonResponse
     */
    public function movimientos(Request $request, ActActivo $activo): JsonResponse
    {
        $this->autorizarSede($request, $activo);

        $movimientos = $activo->movimientos()
            ->with(['sedeOrigen', 'areaOrigen', 'sedeDestino', 'areaDestino', 'usuario'])
            ->get();

        return response()->json([
            'data' => ActMovimientoResource::collection($movimientos),
        ]);
    }

    /**
     * Elimina el activo (soft delete). Para retirarlo del inventario conviene darlo de baja;
     * eliminar es para registros creados por error.
     *
     * @param Request $request
     * @param ActActivo $activo
     * @return JsonResponse
     */
    public function destroy(Request $request, ActActivo $activo): JsonResponse
    {
        $this->autorizarSede($request, $activo);

        $activo->delete();

        return response()->json(['message' => 'Activo eliminado exitosamente.']);
    }

    /**
     * Restaura un activo eliminado.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $activo = ActActivo::onlyTrashed()->findOrFail($id);
        $this->autorizarSede($request, $activo);
        $activo->restore();

        return response()->json([
            'message' => 'Activo restaurado exitosamente.',
            'data'    => new ActActivoResource($activo->load(self::RELACIONES)),
        ]);
    }

    /**
     * Elimina permanentemente un activo de la papelera (y su historial).
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(Request $request, int $id): JsonResponse
    {
        $activo = ActActivo::onlyTrashed()->findOrFail($id);
        $this->autorizarSede($request, $activo);
        $activo->forceDelete();

        return response()->json(['message' => 'Activo eliminado permanentemente.']);
    }

    /**
     * Activos en la papelera de las sedes del usuario.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $activos = ActActivo::onlyTrashed()
            ->deSedes($request->user()->idsSedesAccesibles())
            ->with(self::RELACIONES)
            ->orderBy('codigo')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ActActivoResource::collection($activos),
            'meta' => [
                'current_page' => $activos->currentPage(),
                'last_page'    => $activos->lastPage(),
                'per_page'     => $activos->perPage(),
                'total'        => $activos->total(),
                'from'         => $activos->firstItem(),
                'to'           => $activos->lastItem(),
            ],
        ]);
    }

    /**
     * Opciones de los filtros y formularios: sedes del usuario con sus áreas, categorías activas,
     * estados y posibles responsables.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function filters(Request $request): JsonResponse
    {
        $sedes = Sede::whereIn('id', $request->user()->idsSedesAccesibles())
            ->with(['areas' => fn ($q) => $q->where('areas.status', 1)->orderBy('nombre')])
            ->orderBy('nombre')
            ->get()
            ->map(fn (Sede $sede) => [
                'id'     => $sede->id,
                'nombre' => $sede->nombre,
                'areas'  => $sede->areas->map(fn ($area) => ['id' => $area->id, 'nombre' => $area->nombre])->values(),
            ]);

        return response()->json([
            'data' => [
                'sedes'        => $sedes,
                'categorias'   => ActCategoria::where('status', 1)->orderBy('nombre')->get(['id', 'nombre']),
                'estados'      => ActActivo::ESTADOS,
                // `name` es un accessor (nombre compuesto): se arma en PHP.
                // Solo personal: antes se listaban también los estudiantes (F15)
                'responsables' => User::personal()->select('id', 'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido')
                    ->orderBy('primer_nombre')
                    ->get()
                    ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name]),
            ],
        ]);
    }

    /**
     * Totales del inventario de activos (sin los de baja) de las sedes del usuario,
     * opcionalmente de una sola sede: cantidad, valor, distribución por estado, área y categoría.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        $base = fn () => ActActivo::deSedes($request->user()->idsSedesAccesibles())
            ->when($request->filled('sede_id'), fn ($q) => $q->where('sede_id', $request->sede_id))
            ->where('estado', '!=', ActActivo::ESTADO_BAJA);

        return response()->json([
            'data' => [
                'total'         => $base()->count(),
                'valor_total'   => (float) $base()->sum('valor'),
                'de_baja'       => ActActivo::deSedes($request->user()->idsSedesAccesibles())
                    ->when($request->filled('sede_id'), fn ($q) => $q->where('sede_id', $request->sede_id))
                    ->where('estado', ActActivo::ESTADO_BAJA)
                    ->count(),
                'por_estado'    => $base()->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado'),
                'por_area'      => $base()->with('area')
                    ->selectRaw('area_id, COUNT(*) as total')
                    ->groupBy('area_id')
                    ->get()
                    ->map(fn ($fila) => ['area_id' => $fila->area_id, 'area' => $fila->area?->nombre ?? 'Sin área', 'total' => (int) $fila->total])
                    ->sortByDesc('total')
                    ->values(),
                'por_categoria' => $base()->with('categoria')
                    ->selectRaw('categoria_id, COUNT(*) as total')
                    ->groupBy('categoria_id')
                    ->get()
                    ->map(fn ($fila) => ['categoria' => $fila->categoria?->nombre, 'total' => (int) $fila->total])
                    ->values(),
            ],
        ]);
    }

    /**
     * Responde 403 si el activo es de una sede que el usuario no tiene asignada.
     *
     * @param Request $request
     * @param ActActivo $activo
     * @return void
     */
    private function autorizarSede(Request $request, ActActivo $activo): void
    {
        abort_unless($request->user()->tieneAccesoSede($activo->sede_id), 403, 'No tiene asignada la sede de este activo.');
    }
}
