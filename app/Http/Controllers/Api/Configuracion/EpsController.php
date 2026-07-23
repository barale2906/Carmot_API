<?php

namespace App\Http\Controllers\Api\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Configuracion\ImportarEpsRequest;
use App\Http\Requests\Api\Configuracion\StoreEpsRequest;
use App\Http\Requests\Api\Configuracion\UpdateEpsRequest;
use App\Http\Resources\Api\Configuracion\EpsResource;
use App\Models\Configuracion\Eps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para la gestión de EPS (Entidades Promotoras de Salud).
 *
 * Administra el ciclo de vida de las EPS, incluyendo CRUD, soft delete,
 * restauración, eliminación permanente y carga masiva desde CSV.
 *
 * @package App\Http\Controllers\Api\Configuracion
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
     *
     * @param Request $request
     * @return JsonResponse
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
                'last_page'    => $eps->lastPage(),
                'per_page'     => $eps->perPage(),
                'total'        => $eps->total(),
                'from'         => $eps->firstItem(),
                'to'           => $eps->lastItem(),
            ],
        ]);
    }

    /**
     * Lista todas las EPS activas sin paginación, pensado para selectores en formularios.
     *
     * @return JsonResponse
     */
    public function activas(): JsonResponse
    {
        $eps = Eps::where('status', 1)->orderBy('nombre')->get();

        return response()->json([
            'data' => EpsResource::collection($eps),
            'meta' => [
                'total'       => $eps->count(),
                'scope'       => 'activas',
                'descripcion' => 'EPS activas y sin eliminación lógica.',
            ],
        ]);
    }

    /**
     * Almacena una nueva EPS en la base de datos.
     *
     * @param StoreEpsRequest $request
     * @return JsonResponse
     */
    public function store(StoreEpsRequest $request): JsonResponse
    {
        $eps = Eps::create([
            'nombre'    => $request->nombre,
            'direccion' => $request->direccion,
            'status'    => $request->status ?? 1,
        ]);

        return response()->json([
            'message' => 'EPS creada exitosamente.',
            'data'    => new EpsResource($eps),
        ], 201);
    }

    /**
     * Muestra la EPS especificada.
     *
     * @param Eps $ep
     * @return JsonResponse
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
     *
     * @param UpdateEpsRequest $request
     * @param Eps $ep
     * @return JsonResponse
     */
    public function update(UpdateEpsRequest $request, Eps $ep): JsonResponse
    {
        $ep->update($request->only(['nombre', 'direccion', 'status']));

        return response()->json([
            'message' => 'EPS actualizada exitosamente.',
            'data'    => new EpsResource($ep),
        ]);
    }

    /**
     * Elimina la EPS especificada de la base de datos (soft delete).
     * No permite eliminar si tiene matrículas activas asociadas.
     *
     * @param Eps $ep
     * @return JsonResponse
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
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $eps = Eps::onlyTrashed()->findOrFail($id);
        $eps->restore();

        return response()->json([
            'message' => 'EPS restaurada exitosamente.',
            'data'    => new EpsResource($eps),
        ]);
    }

    /**
     * Elimina permanentemente una EPS (solo si está en soft delete y sin matrículas).
     *
     * @param int $id
     * @return JsonResponse
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
     *
     * @param Request $request
     * @return JsonResponse
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
                'last_page'    => $eps->lastPage(),
                'per_page'     => $eps->perPage(),
                'total'        => $eps->total(),
                'from'         => $eps->firstItem(),
                'to'           => $eps->lastItem(),
            ],
        ]);
    }

    /**
     * Obtiene las opciones de filtros disponibles para el módulo EPS.
     *
     * @return JsonResponse
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
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total'      => Eps::withTrashed()->count(),
                'activas'    => Eps::where('status', 1)->count(),
                'inactivas'  => Eps::where('status', 0)->count(),
                'eliminadas' => Eps::onlyTrashed()->count(),
            ],
            'con_matriculas' => [
                'con_matriculas'    => Eps::has('matriculas')->count(),
                'sin_matriculas'    => Eps::doesntHave('matriculas')->count(),
            ],
            'top_eps' => Eps::withCount('matriculas')
                ->orderByDesc('matriculas_count')
                ->limit(10)
                ->get()
                ->map(fn ($e) => [
                    'id'                => $e->id,
                    'nombre'            => $e->nombre,
                    'matriculas_count'  => $e->matriculas_count,
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
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_eps.csv"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            // BOM para compatibilidad con Excel en Windows
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['nombre', 'direccion', 'status']);
            fputcsv($handle, ['EPS Ejemplo S.A.', 'Calle 123 # 45-67, Bogotá', '1']);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Procesa la carga masiva de EPS desde un archivo CSV.
     *
     * El archivo debe tener encabezados: nombre, direccion, status
     * Las filas con nombre duplicado (ignorando eliminadas) se omiten.
     *
     * @param ImportarEpsRequest $request
     * @return JsonResponse
     */
    public function importar(ImportarEpsRequest $request): JsonResponse
    {
        $archivo = $request->file('archivo');
        $handle  = fopen($archivo->getRealPath(), 'r');

        // Detectar y omitir BOM UTF-8
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $encabezados = array_map('strtolower', array_map('trim', fgetcsv($handle) ?: []));

        $columnasPoblacion = ['nombre', 'direccion', 'status'];
        $columnasFaltantes = array_diff($columnasPoblacion, $encabezados);

        if (!empty($columnasFaltantes)) {
            fclose($handle);
            return response()->json([
                'message' => 'El archivo CSV no tiene el formato correcto. Columnas faltantes: ' . implode(', ', $columnasFaltantes),
            ], 422);
        }

        $resumen = ['insertadas' => 0, 'omitidas' => 0, 'errores' => []];
        $fila = 1;

        DB::beginTransaction();
        try {
            while (($fila_datos = fgetcsv($handle)) !== false) {
                $fila++;
                if (count($fila_datos) < count($encabezados)) {
                    $resumen['errores'][] = "Fila {$fila}: datos insuficientes.";
                    $resumen['omitidas']++;
                    continue;
                }

                $datos = array_combine($encabezados, array_map('trim', $fila_datos));

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
                    'nombre'    => $datos['nombre'],
                    'direccion' => $datos['direccion'] ?? null,
                    'status'    => $status,
                ]);

                $resumen['insertadas']++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            return response()->json([
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 500);
        }

        fclose($handle);

        return response()->json([
            'message' => "Carga masiva completada. Insertadas: {$resumen['insertadas']}, Omitidas: {$resumen['omitidas']}.",
            'data'    => $resumen,
        ]);
    }
}
