<?php

namespace App\Http\Controllers\Api\Activos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Activos\CambiarEstadoActActivoRequest;
use App\Http\Requests\Api\Activos\ImagenActActivoRequest;
use App\Http\Requests\Api\Activos\ImportarActActivosRequest;
use App\Http\Requests\Api\Activos\StoreActActivoRequest;
use App\Http\Requests\Api\Activos\TrasladarActActivoRequest;
use App\Http\Requests\Api\Activos\UpdateActActivoRequest;
use App\Http\Resources\Api\Activos\ActActivoResource;
use App\Http\Resources\Api\Activos\ActMovimientoResource;
use App\Models\Activos\ActActivo;
use App\Models\Activos\ActCategoria;
use App\Models\Activos\ActMovimiento;
use App\Models\Configuracion\Sede;
use App\Models\User;
use App\Services\Activos\ActActivoImportacionService;
use App\Services\Activos\ActActivoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Activos fijos por sede y área: registro, edición, imagen, envío de traslados, cambios de
 * estado e historial. Cada usuario solo ve y opera los activos de sus sedes asignadas. La
 * recepción y cancelación de traslados están en ActTrasladoController.
 */
class ActActivoController extends Controller
{
    private const RELACIONES = ['categoria', 'sede', 'area', 'responsable', 'trasladoPendiente.sedeDestino', 'trasladoPendiente.areaDestino'];

    /**
     * Registra los middlewares de permisos del módulo.
     */
    public function __construct(
        private readonly ActActivoService $activos,
        private readonly ActActivoImportacionService $importacion,
    ) {
        $this->middleware('permission:act_activos')->only(['index', 'show', 'statistics', 'movimientos']);
        // Quien solo recibe traslados también necesita el catálogo (sus sedes y los responsables).
        $this->middleware('permission:act_activos|act_activoRecibir')->only(['filters']);
        $this->middleware('permission:act_activoCrear')->only(['store', 'plantilla', 'importar']);
        $this->middleware('permission:act_activoEditar')->only(['update', 'cambiarEstado']);
        $this->middleware('permission:act_activoCrear|act_activoEditar')->only(['subirImagen', 'eliminarImagen']);
        $this->middleware('permission:act_activoTrasladar')->only(['trasladar']);
        $this->middleware('permission:act_activoInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Lista paginada de activos de las sedes del usuario. Oculta los de baja salvo que se filtre por ese estado.
     */
    public function index(Request $request): JsonResponse
    {
        $activos = ActActivo::deSedes($request->user()->idsSedesAccesibles())
            ->withFilters($request->only(['search', 'sede_id', 'area_id', 'categoria_id', 'responsable_id', 'estado']))
            ->with(self::RELACIONES)
            ->withSorting($request->get('sort_by', 'id'), $request->get('sort_direction', 'asc'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ActActivoResource::collection($activos),
            'meta' => [
                'current_page' => $activos->currentPage(),
                'last_page' => $activos->lastPage(),
                'per_page' => $activos->perPage(),
                'total' => $activos->total(),
                'from' => $activos->firstItem(),
                'to' => $activos->lastItem(),
            ],
        ]);
    }

    /**
     * Registra un activo y su movimiento de ingreso.
     */
    public function store(StoreActActivoRequest $request): JsonResponse
    {
        $activo = $this->activos->registrar($request->validated(), $request->user()->id);

        return response()->json([
            'message' => 'Activo registrado exitosamente.',
            'data' => new ActActivoResource($activo->load(self::RELACIONES)),
        ], 201);
    }

    /**
     * Descarga la plantilla Excel de la carga masiva, con las categorías, sedes (y sus áreas),
     * estados y responsables vigentes como listas desplegables, instrucciones y un ejemplo.
     */
    public function plantilla(Request $request): StreamedResponse
    {
        $spreadsheet = $this->importacion->plantilla($request->user());

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'plantilla-activos.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Carga masiva de activos desde la plantilla. Todo o nada: si alguna fila tiene errores no
     * se registra ninguno y se responde 422 con `errores` (número de fila del archivo y sus mensajes).
     */
    public function importar(ImportarActActivosRequest $request): JsonResponse
    {
        $resultado = $this->importacion->importar($request->file('archivo')->getRealPath(), $request->user());

        if ($resultado['errores']) {
            return response()->json([
                'message' => 'El archivo tiene errores; no se registró ningún activo.',
                'errores' => $resultado['errores'],
            ], 422);
        }

        $activos = $resultado['activos'];

        return response()->json([
            'message' => "Se registraron {$activos->count()} activos.",
            'data' => ActActivoResource::collection($activos->each->load(self::RELACIONES)),
            'meta' => ['total' => $activos->count()],
        ], 201);
    }

    /**
     * Ficha del activo con su historial.
     */
    public function show(Request $request, ActActivo $activo): JsonResponse
    {
        $this->autorizarSede($request, $activo);

        $activo->load([...self::RELACIONES, 'movimientos' => fn ($q) => $q->with(ActMovimiento::RELACIONES)]);

        return response()->json([
            'data' => new ActActivoResource($activo),
        ]);
    }

    /**
     * Actualiza los datos descriptivos del activo (no su ubicación ni su estado).
     */
    public function update(UpdateActActivoRequest $request, ActActivo $activo): JsonResponse
    {
        $this->autorizarSede($request, $activo);

        $activo->update($request->validated());

        return response()->json([
            'message' => 'Activo actualizado exitosamente.',
            'data' => new ActActivoResource($activo->load(self::RELACIONES)),
        ]);
    }

    /**
     * Carga o reemplaza la imagen del activo (multipart, campo `imagen`). La anterior se borra.
     */
    public function subirImagen(ImagenActActivoRequest $request, ActActivo $activo): JsonResponse
    {
        $this->autorizarSede($request, $activo);

        $this->activos->guardarImagen($activo, $request->file('imagen'), $request->user()->id);

        return response()->json([
            'message' => 'Imagen del activo actualizada.',
            'data' => new ActActivoResource($activo->load(self::RELACIONES)),
        ]);
    }

    /**
     * Quita la imagen del activo.
     */
    public function eliminarImagen(Request $request, ActActivo $activo): JsonResponse
    {
        $this->autorizarSede($request, $activo);

        $this->activos->eliminarImagen($activo);

        return response()->json([
            'message' => 'Imagen del activo eliminada.',
            'data' => new ActActivoResource($activo->load(self::RELACIONES)),
        ]);
    }

    /**
     * Envía el activo a otra sede o área. El traslado queda pendiente y el activo sigue en su
     * ubicación actual hasta que en el destino se registre la recepción.
     */
    public function trasladar(TrasladarActActivoRequest $request, ActActivo $activo): JsonResponse
    {
        $this->autorizarSede($request, $activo);

        $this->activos->trasladar($activo, $request->validated(), $request->user()->id);

        return response()->json([
            'message' => 'Traslado enviado: queda pendiente de recepción en el destino.',
            'data' => new ActActivoResource($activo->fresh(self::RELACIONES)),
        ]);
    }

    /**
     * Cambia el estado del activo (operativo, mantenimiento, disponible o baja definitiva).
     */
    public function cambiarEstado(CambiarEstadoActActivoRequest $request, ActActivo $activo): JsonResponse
    {
        $this->autorizarSede($request, $activo);

        $this->activos->cambiarEstado($activo, $request->estado, $request->fecha, $request->motivo, $request->user()->id);

        return response()->json([
            'message' => 'Estado del activo actualizado.',
            'data' => new ActActivoResource($activo->fresh(self::RELACIONES)),
        ]);
    }

    /**
     * Historial de ingresos, traslados y cambios de estado del activo.
     */
    public function movimientos(Request $request, ActActivo $activo): JsonResponse
    {
        $this->autorizarSede($request, $activo);

        $movimientos = $activo->movimientos()
            ->with(ActMovimiento::RELACIONES)
            ->get();

        return response()->json([
            'data' => ActMovimientoResource::collection($movimientos),
        ]);
    }

    /**
     * Elimina el activo (soft delete). Para retirarlo del inventario conviene darlo de baja;
     * eliminar es para registros creados por error.
     */
    public function destroy(Request $request, ActActivo $activo): JsonResponse
    {
        $this->autorizarSede($request, $activo);

        $activo->delete();

        return response()->json(['message' => 'Activo eliminado exitosamente.']);
    }

    /**
     * Restaura un activo eliminado.
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $activo = ActActivo::onlyTrashed()->findOrFail($id);
        $this->autorizarSede($request, $activo);
        $activo->restore();

        return response()->json([
            'message' => 'Activo restaurado exitosamente.',
            'data' => new ActActivoResource($activo->load(self::RELACIONES)),
        ]);
    }

    /**
     * Elimina permanentemente un activo de la papelera (y su historial).
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
     */
    public function trashed(Request $request): JsonResponse
    {
        $activos = ActActivo::onlyTrashed()
            ->deSedes($request->user()->idsSedesAccesibles())
            ->with(self::RELACIONES)
            ->orderBy('id')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => ActActivoResource::collection($activos),
            'meta' => [
                'current_page' => $activos->currentPage(),
                'last_page' => $activos->lastPage(),
                'per_page' => $activos->perPage(),
                'total' => $activos->total(),
                'from' => $activos->firstItem(),
                'to' => $activos->lastItem(),
            ],
        ]);
    }

    /**
     * Opciones de los filtros y formularios: sedes del usuario con sus áreas, categorías activas,
     * estados y posibles responsables.
     */
    public function filters(Request $request): JsonResponse
    {
        $sedes = Sede::whereIn('id', $request->user()->idsSedesAccesibles())
            ->with(['areas' => fn ($q) => $q->where('areas.status', 1)->orderBy('nombre')])
            ->orderBy('nombre')
            ->get()
            ->map(fn (Sede $sede) => [
                'id' => $sede->id,
                'nombre' => $sede->nombre,
                'areas' => $sede->areas->map(fn ($area) => ['id' => $area->id, 'nombre' => $area->nombre])->values(),
            ]);

        return response()->json([
            'data' => [
                'sedes' => $sedes,
                'categorias' => ActCategoria::where('status', 1)->orderBy('nombre')->get(['id', 'nombre']),
                'estados' => ActActivo::ESTADOS,
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
     * opcionalmente de una sola sede: cantidad, valor, en tránsito (traslado sin recibir),
     * distribución por estado, área y categoría.
     */
    public function statistics(Request $request): JsonResponse
    {
        $base = fn () => ActActivo::deSedes($request->user()->idsSedesAccesibles())
            ->when($request->filled('sede_id'), fn ($q) => $q->where('sede_id', $request->sede_id))
            ->where('estado', '!=', ActActivo::ESTADO_BAJA);

        return response()->json([
            'data' => [
                'total' => $base()->count(),
                'valor_total' => (float) $base()->sum('valor'),
                'de_baja' => ActActivo::deSedes($request->user()->idsSedesAccesibles())
                    ->when($request->filled('sede_id'), fn ($q) => $q->where('sede_id', $request->sede_id))
                    ->where('estado', ActActivo::ESTADO_BAJA)
                    ->count(),
                'en_transito' => $base()->has('trasladoPendiente')->count(),
                'por_estado' => $base()->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado'),
                'por_area' => $base()->with('area')
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
     */
    private function autorizarSede(Request $request, ActActivo $activo): void
    {
        abort_unless($request->user()->tieneAccesoSede($activo->sede_id), 403, 'No tiene asignada la sede de este activo.');
    }
}
