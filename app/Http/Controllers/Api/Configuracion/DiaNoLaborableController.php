<?php

namespace App\Http\Controllers\Api\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Configuracion\GenerarFestivosRequest;
use App\Http\Requests\Api\Configuracion\StoreDiaNoLaborableRequest;
use App\Http\Requests\Api\Configuracion\StoreRangoDiaNoLaborableRequest;
use App\Http\Requests\Api\Configuracion\UpdateDiaNoLaborableRequest;
use App\Http\Resources\Api\Configuracion\DiaNoLaborableResource;
use App\Models\Configuracion\DiaNoLaborable;
use App\Services\Academico\RecalculoCalendarioService;
use App\Services\Configuracion\DiaNoLaborableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador del calendario de días no laborables.
 *
 * Todo cambio que afecte el calendario (crear, editar, eliminar, restaurar,
 * alta por rango o generación de festivos) recalcula automáticamente los
 * ciclos afectados: fechas de grupos, fecha fin automática y clases
 * programadas pendientes. Los ciclos recalculados se informan en
 * meta.ciclos_recalculados.
 *
 * @package App\Http\Controllers\Api\Configuracion
 */
class DiaNoLaborableController extends Controller
{
    /** Campos permitidos en store/update. */
    private const FILLABLE_FIELDS = ['fecha', 'nombre', 'tipo', 'sede_id', 'observaciones', 'status'];

    /**
     * Registra los middlewares de permisos del módulo.
     */
    public function __construct(
        private readonly RecalculoCalendarioService $recalculo,
        private readonly DiaNoLaborableService $diasNoLaborables,
    ) {
        $this->middleware('permission:co_diasNoLaborables')->only(['index', 'show', 'filters', 'statistics', 'calendario']);
        $this->middleware('permission:co_diaNoLaborableCrear')->only(['store', 'rango', 'generarFestivos']);
        $this->middleware('permission:co_diaNoLaborableEditar')->only(['update']);
        $this->middleware('permission:co_diaNoLaborableInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Muestra una lista paginada de días no laborables con filtros y ordenamiento.
     * Con sede_id incluye también los días generales (todas las sedes).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'tipo', 'sede_id', 'solo_generales', 'anio', 'fecha_desde', 'fecha_hasta', 'status']);

        $dias = DiaNoLaborable::withFilters($filters)
            ->with('sede')
            ->withSorting($request->get('sort_by', 'fecha'), $request->get('sort_direction', 'asc'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => DiaNoLaborableResource::collection($dias),
            'meta' => [
                'current_page' => $dias->currentPage(),
                'last_page'    => $dias->lastPage(),
                'per_page'     => $dias->perPage(),
                'total'        => $dias->total(),
                'from'         => $dias->firstItem(),
                'to'           => $dias->lastItem(),
            ],
        ]);
    }

    /**
     * Devuelve todos los días no laborables activos de un año, sin paginar,
     * para la vista de calendario. Con sede_id incluye los días generales.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calendario(Request $request): JsonResponse
    {
        $request->validate([
            'anio'    => 'required|integer|between:2000,2100',
            'sede_id' => 'nullable|integer|exists:sedes,id',
        ]);

        $dias = DiaNoLaborable::withFilters($request->only(['anio', 'sede_id']))
            ->active()
            ->with('sede')
            ->orderBy('fecha')
            ->get();

        return response()->json([
            'data' => DiaNoLaborableResource::collection($dias),
            'meta' => [
                'anio'     => (int) $request->anio,
                'sede_id'  => $request->filled('sede_id') ? (int) $request->sede_id : null,
                'total'    => $dias->count(),
                'por_tipo' => $dias->countBy('tipo'),
            ],
        ]);
    }

    /**
     * Almacena un nuevo día no laborable y recalcula los ciclos afectados.
     *
     * @param StoreDiaNoLaborableRequest $request
     * @return JsonResponse
     */
    public function store(StoreDiaNoLaborableRequest $request): JsonResponse
    {
        [$dia, $recalculados] = DB::transaction(function () use ($request) {
            $data           = $request->only(self::FILLABLE_FIELDS);
            $data['status'] = $data['status'] ?? 1;
            $dia            = DiaNoLaborable::create($data);

            return [$dia, $this->recalculo->recalcularPorDias([$this->clave($dia)])];
        });

        return response()->json([
            'message' => 'Día no laborable creado exitosamente.',
            'data'    => new DiaNoLaborableResource($dia->load('sede')),
            'meta'    => ['ciclos_recalculados' => $recalculados],
        ], 201);
    }

    /**
     * Registra un día no laborable por cada fecha de un rango (ej. vacaciones
     * colectivas). Las fechas ya registradas se omiten y se reportan.
     *
     * @param StoreRangoDiaNoLaborableRequest $request
     * @return JsonResponse
     */
    public function rango(StoreRangoDiaNoLaborableRequest $request): JsonResponse
    {
        return $this->respuestaAltaMasiva(
            fn () => $this->diasNoLaborables->registrarRango($request->validated()),
            'Rango registrado.'
        );
    }

    /**
     * Genera los festivos nacionales de Colombia del año indicado. Los festivos
     * ya registrados o eliminados previamente se omiten; luego pueden editarse
     * o eliminarse como cualquier otro día.
     *
     * @param GenerarFestivosRequest $request
     * @return JsonResponse
     */
    public function generarFestivos(GenerarFestivosRequest $request): JsonResponse
    {
        return $this->respuestaAltaMasiva(
            fn () => $this->diasNoLaborables->generarFestivos(
                (int) $request->anio,
                $request->filled('sede_id') ? (int) $request->sede_id : null
            ),
            "Festivos de {$request->anio} generados."
        );
    }

    /**
     * Muestra el día no laborable especificado.
     *
     * @param DiaNoLaborable $diaNoLaborable
     * @return JsonResponse
     */
    public function show(DiaNoLaborable $diaNoLaborable): JsonResponse
    {
        return response()->json([
            'data' => new DiaNoLaborableResource($diaNoLaborable->load('sede')),
        ]);
    }

    /**
     * Actualiza el día no laborable y recalcula los ciclos afectados por la
     * fecha/sede anterior y la nueva (cubre cambios de fecha, sede o estado).
     *
     * @param UpdateDiaNoLaborableRequest $request
     * @param DiaNoLaborable $diaNoLaborable
     * @return JsonResponse
     */
    public function update(UpdateDiaNoLaborableRequest $request, DiaNoLaborable $diaNoLaborable): JsonResponse
    {
        $recalculados = DB::transaction(function () use ($request, $diaNoLaborable) {
            $anterior = $this->clave($diaNoLaborable);
            $diaNoLaborable->update($request->only(self::FILLABLE_FIELDS));

            return $this->recalculo->recalcularPorDias([$anterior, $this->clave($diaNoLaborable)]);
        });

        return response()->json([
            'message' => 'Día no laborable actualizado exitosamente.',
            'data'    => new DiaNoLaborableResource($diaNoLaborable->fresh('sede')),
            'meta'    => ['ciclos_recalculados' => $recalculados],
        ]);
    }

    /**
     * Elimina el día no laborable (soft delete) y recalcula los ciclos afectados.
     *
     * @param DiaNoLaborable $diaNoLaborable
     * @return JsonResponse
     */
    public function destroy(DiaNoLaborable $diaNoLaborable): JsonResponse
    {
        $recalculados = DB::transaction(function () use ($diaNoLaborable) {
            $diaNoLaborable->delete();

            return $this->recalculo->recalcularPorDias([$this->clave($diaNoLaborable)]);
        });

        return response()->json([
            'message' => 'Día no laborable eliminado exitosamente.',
            'meta'    => ['ciclos_recalculados' => $recalculados],
        ]);
    }

    /**
     * Restaura un día no laborable eliminado y recalcula los ciclos afectados.
     * Rechaza la restauración si la fecha ya fue registrada de nuevo.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $dia = DiaNoLaborable::onlyTrashed()->findOrFail($id);

        $motivo = DiaNoLaborable::motivoDuplicado($dia->fecha->toDateString(), $dia->sede_id);
        if ($motivo) {
            return response()->json(['message' => $motivo], 422);
        }

        $recalculados = DB::transaction(function () use ($dia) {
            $dia->restore();

            return $this->recalculo->recalcularPorDias([$this->clave($dia)]);
        });

        return response()->json([
            'message' => 'Día no laborable restaurado exitosamente.',
            'data'    => new DiaNoLaborableResource($dia->load('sede')),
            'meta'    => ['ciclos_recalculados' => $recalculados],
        ]);
    }

    /**
     * Elimina permanentemente un día no laborable que ya está en soft delete.
     * No recalcula: un día eliminado ya no afecta el calendario.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        DiaNoLaborable::onlyTrashed()->findOrFail($id)->forceDelete();

        return response()->json([
            'message' => 'Día no laborable eliminado permanentemente.',
        ]);
    }

    /**
     * Obtiene solo los días no laborables eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'tipo', 'sede_id', 'solo_generales', 'anio', 'fecha_desde', 'fecha_hasta', 'status']);

        $dias = DiaNoLaborable::onlyTrashed()
            ->withFilters($filters)
            ->with('sede')
            ->withSorting($request->get('sort_by', 'fecha'), $request->get('sort_direction', 'asc'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => DiaNoLaborableResource::collection($dias),
            'meta' => [
                'current_page' => $dias->currentPage(),
                'last_page'    => $dias->lastPage(),
                'per_page'     => $dias->perPage(),
                'total'        => $dias->total(),
                'from'         => $dias->firstItem(),
                'to'           => $dias->lastItem(),
            ],
        ]);
    }

    /**
     * Obtiene las opciones de filtros (tipos y estados) para el frontend.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'tipos'  => DiaNoLaborable::TIPOS,
                'status' => DiaNoLaborable::getActiveStatusOptions(),
            ],
        ]);
    }

    /**
     * Obtiene estadísticas del calendario de días no laborables.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $hoy = now()->toDateString();

        return response()->json([
            'data' => [
                'totales' => [
                    'total'      => DiaNoLaborable::withTrashed()->count(),
                    'activos'    => DiaNoLaborable::where('status', 1)->count(),
                    'inactivos'  => DiaNoLaborable::where('status', 0)->count(),
                    'eliminados' => DiaNoLaborable::onlyTrashed()->count(),
                ],
                'por_tipo'  => DiaNoLaborable::active()->selectRaw('tipo, COUNT(*) as total')->groupBy('tipo')->pluck('total', 'tipo'),
                'alcance'   => [
                    'generales' => DiaNoLaborable::active()->whereNull('sede_id')->count(),
                    'por_sede'  => DiaNoLaborable::active()->whereNotNull('sede_id')->count(),
                ],
                'proximos'  => DiaNoLaborableResource::collection(
                    DiaNoLaborable::active()->with('sede')->where('fecha', '>=', $hoy)->orderBy('fecha')->limit(5)->get()
                ),
            ],
        ]);
    }

    /**
     * Ejecuta un alta masiva, recalcula los ciclos afectados por los días creados
     * y arma la respuesta con creados, omitidos y ciclos recalculados.
     *
     * @param \Closure $alta  Devuelve ['creados' => Collection, 'omitidos' => array]
     * @param string   $mensaje
     * @return JsonResponse
     */
    private function respuestaAltaMasiva(\Closure $alta, string $mensaje): JsonResponse
    {
        [$resultado, $recalculados] = DB::transaction(function () use ($alta) {
            $resultado = $alta();

            return [$resultado, $this->recalculo->recalcularPorDias(
                $resultado['creados']->map(fn ($dia) => $this->clave($dia))->all()
            )];
        });

        $creados = $resultado['creados']->count();

        return response()->json([
            'message' => "{$mensaje} Creados: {$creados}, omitidos: " . count($resultado['omitidos']) . '.',
            'data'    => [
                'creados'  => DiaNoLaborableResource::collection($resultado['creados']),
                'omitidos' => $resultado['omitidos'],
            ],
            'meta'    => ['ciclos_recalculados' => $recalculados],
        ], $creados > 0 ? 201 : 200);
    }

    /**
     * Fecha y sede de un día, en el formato que espera el recálculo.
     *
     * @return array{fecha: string, sede_id: int|null}
     */
    private function clave(DiaNoLaborable $dia): array
    {
        return ['fecha' => $dia->fecha->toDateString(), 'sede_id' => $dia->sede_id];
    }
}
