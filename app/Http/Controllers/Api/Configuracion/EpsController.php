<?php

namespace App\Http\Controllers\Api\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Configuracion\ImportarEpsRequest;
use App\Http\Requests\Api\Configuracion\StoreEpsRequest;
use App\Http\Requests\Api\Configuracion\UpdateEpsRequest;
use App\Http\Resources\Api\Configuracion\EpsResource;
use App\Models\Configuracion\Eps;
use App\Services\ArchivoTabularService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para la gestión de EPS (Entidades Promotoras de Salud).
 *
 * Administra el ciclo de vida de las EPS, incluyendo CRUD, soft delete,
 * restauración, eliminación permanente y carga masiva desde CSV.
 */
class EpsController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:co_eps')->only(['index', 'show', 'filters', 'statistics', 'activas']);
        $this->middleware('permission:co_epsCrear')->only(['store']);
        $this->middleware('permission:co_epsEditar')->only(['update']);
        $this->middleware('permission:co_epsInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
        $this->middleware('permission:co_epsImportar')->only(['importar', 'plantilla']);
    }

    /**
     * Muestra una lista paginada de EPS con filtros y ordenamiento.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'nombre', 'status']);

        $eps = Eps::withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->withCount('matriculas')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => EpsResource::collection($eps),
            'meta' => [
                'current_page' => $eps->currentPage(),
                'last_page' => $eps->lastPage(),
                'per_page' => $eps->perPage(),
                'total' => $eps->total(),
                'from' => $eps->firstItem(),
                'to' => $eps->lastItem(),
            ],
        ]);
    }

    /**
     * Lista todas las EPS activas sin paginación, pensado para selectores en formularios.
     */
    public function activas(): JsonResponse
    {
        $eps = Eps::where('status', 1)->orderBy('nombre')->get();

        return response()->json([
            'data' => EpsResource::collection($eps),
            'meta' => [
                'total' => $eps->count(),
                'scope' => 'activas',
                'descripcion' => 'EPS activas y sin eliminación lógica.',
            ],
        ]);
    }

    /**
     * Almacena una nueva EPS en la base de datos.
     */
    public function store(StoreEpsRequest $request): JsonResponse
    {
        $eps = Eps::create([
            'nombre' => $request->nombre,
            'direccion' => $request->direccion,
            'status' => $request->status ?? 1,
        ]);

        return response()->json([
            'message' => 'EPS creada exitosamente.',
            'data' => new EpsResource($eps),
        ], 201);
    }

    /**
     * Muestra la EPS especificada.
     */
    public function show(Eps $ep): JsonResponse
    {
        $ep->loadCount('matriculas');

        return response()->json([
            'data' => new EpsResource($ep),
        ]);
    }

    /**
     * Actualiza la EPS especificada en la base de datos.
     */
    public function update(UpdateEpsRequest $request, Eps $ep): JsonResponse
    {
        $ep->update($request->only(['nombre', 'direccion', 'status']));

        return response()->json([
            'message' => 'EPS actualizada exitosamente.',
            'data' => new EpsResource($ep),
        ]);
    }

    /**
     * Elimina la EPS especificada de la base de datos (soft delete).
     * No permite eliminar si tiene matrículas activas asociadas.
     */
    public function destroy(Eps $ep): JsonResponse
    {
        if ($ep->matriculas()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la EPS porque tiene matrículas asociadas.',
            ], 422);
        }

        $ep->delete();

        return response()->json([
            'message' => 'EPS eliminada exitosamente.',
        ]);
    }

    /**
     * Restaura una EPS eliminada (soft delete).
     */
    public function restore(int $id): JsonResponse
    {
        $eps = Eps::onlyTrashed()->findOrFail($id);
        $eps->restore();

        return response()->json([
            'message' => 'EPS restaurada exitosamente.',
            'data' => new EpsResource($eps),
        ]);
    }

    /**
     * Elimina permanentemente una EPS (solo si está en soft delete y sin matrículas).
     */
    public function forceDelete(int $id): JsonResponse
    {
        $eps = Eps::onlyTrashed()->findOrFail($id);

        if ($eps->matriculas()->withTrashed()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente la EPS porque tiene matrículas asociadas.',
            ], 422);
        }

        $eps->forceDelete();

        return response()->json([
            'message' => 'EPS eliminada permanentemente.',
        ]);
    }

    /**
     * Obtiene solo las EPS eliminadas (soft delete).
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'nombre', 'status']);

        $eps = Eps::onlyTrashed()
            ->withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->withCount('matriculas')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => EpsResource::collection($eps),
            'meta' => [
                'current_page' => $eps->currentPage(),
                'last_page' => $eps->lastPage(),
                'per_page' => $eps->perPage(),
                'total' => $eps->total(),
                'from' => $eps->firstItem(),
                'to' => $eps->lastItem(),
            ],
        ]);
    }

    /**
     * Obtiene las opciones de filtros disponibles para el módulo EPS.
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => Eps::getActiveStatusOptions(),
            ],
        ]);
    }

    /**
     * Obtiene estadísticas del catálogo de EPS.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total' => Eps::withTrashed()->count(),
                'activas' => Eps::where('status', 1)->count(),
                'inactivas' => Eps::where('status', 0)->count(),
                'eliminadas' => Eps::onlyTrashed()->count(),
            ],
            'con_matriculas' => [
                'con_matriculas' => Eps::has('matriculas')->count(),
                'sin_matriculas' => Eps::doesntHave('matriculas')->count(),
            ],
            'top_eps' => Eps::withCount('matriculas')
                ->orderByDesc('matriculas_count')
                ->limit(10)
                ->get()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'nombre' => $e->nombre,
                    'matriculas_count' => $e->matriculas_count,
                ]),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Devuelve la plantilla CSV con el formato esperado para la carga masiva.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function plantilla()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_eps.csv"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            // BOM para compatibilidad con Excel en Windows
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['nombre', 'direccion', 'status']);
            fputcsv($handle, ['EPS Ejemplo S.A.', 'Calle 123 # 45-67, Bogotá', '1']);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Procesa la carga masiva de EPS desde un archivo Excel (.xlsx, .xls) o CSV.
     *
     * El archivo debe tener encabezados: nombre, direccion, status
     * Las filas con nombre duplicado (ignorando eliminadas) se omiten.
     */
    public function importar(ImportarEpsRequest $request): JsonResponse
    {
        ['encabezados' => $encabezados, 'filas' => $filas] = app(ArchivoTabularService::class)
            ->leer($request->file('archivo')->getRealPath());

        $columnasFaltantes = array_diff(['nombre', 'direccion', 'status'], $encabezados);

        if (! empty($columnasFaltantes)) {
            return response()->json([
                'message' => 'El archivo no tiene el formato correcto. Columnas faltantes: '.implode(', ', $columnasFaltantes),
            ], 422);
        }

        $resumen = ['insertadas' => 0, 'omitidas' => 0, 'errores' => []];

        DB::beginTransaction();
        try {
            foreach ($filas as $fila => $valores) {
                $datos = array_combine($encabezados, array_slice(array_pad($valores, count($encabezados), null), 0, count($encabezados)));

                if (empty($datos['nombre'])) {
                    $resumen['errores'][] = "Fila {$fila}: el nombre es obligatorio.";
                    $resumen['omitidas']++;

                    continue;
                }

                // Verificar duplicado por nombre (ignorando eliminadas lógicamente)
                $existe = Eps::whereNull('deleted_at')
                    ->whereRaw('LOWER(nombre) = ?', [strtolower($datos['nombre'])])
                    ->exists();

                if ($existe) {
                    $resumen['errores'][] = "Fila {$fila}: la EPS '{$datos['nombre']}' ya existe.";
                    $resumen['omitidas']++;

                    continue;
                }

                $status = isset($datos['status']) && in_array((int) $datos['status'], [0, 1])
                    ? (int) $datos['status']
                    : 1;

                Eps::create([
                    'nombre' => $datos['nombre'],
                    'direccion' => $datos['direccion'] ?? null,
                    'status' => $status,
                ]);

                $resumen['insertadas']++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al procesar el archivo: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => "Carga masiva completada. Insertadas: {$resumen['insertadas']}, Omitidas: {$resumen['omitidas']}.",
            'data' => $resumen,
        ]);
    }
}
