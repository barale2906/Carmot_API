<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\StoreBibliotecaRequest;
use App\Http\Requests\Api\Academico\UpdateBibliotecaRequest;
use App\Http\Resources\Api\Academico\BibliotecaResource;
use App\Models\Academico\Biblioteca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Controlador para la gestión de documentos de la Biblioteca académica.
 *
 * Maneja el ciclo de vida completo de los documentos: subida de archivos,
 * listado con filtros avanzados, descarga, sincronización de cursos asociados
 * y operaciones de soft delete / restore / force delete.
 *
 * @package App\Http\Controllers\Api\Academico
 */
class BibliotecaController extends Controller
{
    /**
     * Registra los middlewares de autenticación y permisos del módulo.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:ac_biblioteca')->only(['index', 'show', 'filters', 'statistics', 'download']);
        $this->middleware('permission:ac_bibliotecaCrear')->only(['store']);
        $this->middleware('permission:ac_bibliotecaEditar')->only(['update', 'syncCursos']);
        $this->middleware('permission:ac_bibliotecaInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    // -------------------------------------------------------------------------
    // CRUD principal
    // -------------------------------------------------------------------------

    /**
     * Lista los documentos de la biblioteca con paginación, filtros y ordenamiento.
     *
     * @queryParam page int Número de página. Ejemplo: 1
     * @queryParam per_page int Registros por página (máx. 100). Ejemplo: 15
     * @queryParam search string Búsqueda por nombre. Ejemplo: Manual
     * @queryParam status int Filtrar por status (0=inactivo, 1=activo). Ejemplo: 1
     * @queryParam tipo_archivo string Filtrar por tipo de archivo. Ejemplo: pdf
     * @queryParam curso_id int Filtrar por curso asociado. Ejemplo: 3
     * @queryParam vigentes bool Solo documentos vigentes. Ejemplo: true
     * @queryParam obsoletos bool Solo documentos obsoletos. Ejemplo: true
     * @queryParam fecha_carga_desde date Fecha de carga desde. Ejemplo: 2026-01-01
     * @queryParam fecha_carga_hasta date Fecha de carga hasta. Ejemplo: 2026-12-31
     * @queryParam sort_by string Campo de ordenamiento. Ejemplo: nombre
     * @queryParam sort_direction string Dirección del ordenamiento (asc|desc). Ejemplo: asc
     * @queryParam with string Relaciones separadas por coma. Ejemplo: cursos
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search',
                'status',
                'tipo_archivo',
                'curso_id',
                'vigentes',
                'obsoletos',
                'fecha_carga_desde',
                'fecha_carga_hasta',
                'include_trashed',
                'only_trashed',
            ]);

            $relations = $request->filled('with')
                ? explode(',', $request->with)
                : ['cursos'];

            $perPage = min((int) $request->get('per_page', 15), 100);

            $documentos = Biblioteca::withFilters($filters)
                ->withRelationsAndCounts($relations, true)
                ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
                ->paginate($perPage);

            return response()->json([
                'data' => BibliotecaResource::collection($documentos),
                'meta' => [
                    'current_page' => $documentos->currentPage(),
                    'last_page'    => $documentos->lastPage(),
                    'per_page'     => $documentos->perPage(),
                    'total'        => $documentos->total(),
                    'from'         => $documentos->firstItem(),
                    'to'           => $documentos->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar documentos de biblioteca', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al obtener los documentos de la biblioteca.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Almacena un nuevo documento de biblioteca (sube el archivo al disco público).
     */
    public function store(StoreBibliotecaRequest $request): JsonResponse
    {
        try {
            $archivo     = $request->file('archivo');
            $rutaDestino = $this->generarRutaArchivo($request->nombre, $archivo->getClientOriginalExtension());

            $archivo->storeAs('', $rutaDestino, 'public');

            $documento = Biblioteca::create([
                'nombre'              => $request->nombre,
                'fecha_carga'         => $request->fecha_carga,
                'fecha_obsolescencia' => $request->fecha_obsolescencia,
                'ruta'                => $rutaDestino,
                'tipo_archivo'        => $archivo->getClientOriginalExtension(),
                'tamanio'             => $archivo->getSize(),
            ]);

            if ($request->filled('cursos')) {
                $documento->cursos()->sync($request->cursos);
            }

            $documento->load('cursos');

            return response()->json([
                'message' => 'Documento agregado a la biblioteca exitosamente.',
                'data'    => new BibliotecaResource($documento),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear documento de biblioteca', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al crear el documento.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra el detalle de un documento de biblioteca.
     *
     * @queryParam with string Relaciones separadas por coma. Ejemplo: cursos
     */
    public function show(Request $request, Biblioteca $biblioteca): JsonResponse
    {
        $relations = $request->filled('with')
            ? explode(',', $request->with)
            : ['cursos'];

        $biblioteca->load($relations);
        $biblioteca->loadCount(['cursos']);

        return response()->json([
            'data' => new BibliotecaResource($biblioteca),
        ]);
    }

    /**
     * Actualiza un documento de biblioteca. Si se adjunta un nuevo archivo,
     * el anterior es eliminado del almacenamiento.
     */
    public function update(UpdateBibliotecaRequest $request, Biblioteca $biblioteca): JsonResponse
    {
        try {
            $datos = $request->only([
                'nombre',
                'fecha_carga',
                'fecha_obsolescencia',
                'status',
            ]);

            if ($request->hasFile('archivo')) {
                $this->eliminarArchivo($biblioteca->ruta);

                $archivo     = $request->file('archivo');
                $nombreBase  = $datos['nombre'] ?? $biblioteca->nombre;
                $rutaDestino = $this->generarRutaArchivo($nombreBase, $archivo->getClientOriginalExtension());

                $archivo->storeAs('', $rutaDestino, 'public');

                $datos['ruta']         = $rutaDestino;
                $datos['tipo_archivo'] = $archivo->getClientOriginalExtension();
                $datos['tamanio']      = $archivo->getSize();
            }

            $biblioteca->update($datos);

            if ($request->has('cursos')) {
                $biblioteca->cursos()->sync($request->cursos ?? []);
            }

            $biblioteca->load('cursos');
            $biblioteca->loadCount(['cursos']);

            return response()->json([
                'message' => 'Documento de biblioteca actualizado exitosamente.',
                'data'    => new BibliotecaResource($biblioteca),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar documento de biblioteca', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al actualizar el documento.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina el documento de manera lógica (soft delete).
     * El archivo físico se conserva hasta un posible forceDelete.
     */
    public function destroy(Biblioteca $biblioteca): JsonResponse
    {
        $biblioteca->delete();

        return response()->json([
            'message' => 'Documento eliminado de la biblioteca.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Acciones adicionales
    // -------------------------------------------------------------------------

    /**
     * Restaura un documento previamente eliminado.
     */
    public function restore(int $id): JsonResponse
    {
        $documento = Biblioteca::onlyTrashed()->findOrFail($id);
        $documento->restore();

        return response()->json([
            'message' => 'Documento restaurado exitosamente.',
            'data'    => new BibliotecaResource($documento->load('cursos')),
        ]);
    }

    /**
     * Elimina permanentemente el documento y su archivo físico.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $documento = Biblioteca::onlyTrashed()->findOrFail($id);

        $this->eliminarArchivo($documento->ruta);

        $documento->cursos()->detach();
        $documento->forceDelete();

        return response()->json([
            'message' => 'Documento eliminado permanentemente de la biblioteca.',
        ]);
    }

    /**
     * Lista los documentos eliminados (soft delete).
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'tipo_archivo', 'curso_id']);

        $documentos = Biblioteca::onlyTrashed()
            ->withFilters($filters)
            ->withRelationsAndCounts(['cursos'], true)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => BibliotecaResource::collection($documentos),
            'meta' => [
                'current_page' => $documentos->currentPage(),
                'last_page'    => $documentos->lastPage(),
                'per_page'     => $documentos->perPage(),
                'total'        => $documentos->total(),
                'from'         => $documentos->firstItem(),
                'to'           => $documentos->lastItem(),
            ],
        ]);
    }

    /**
     * Sincroniza los cursos asociados a un documento (reemplaza la lista completa).
     *
     * @bodyParam cursos array required Lista de IDs de cursos. Ejemplo: [1, 2, 3]
     */
    public function syncCursos(Request $request, Biblioteca $biblioteca): JsonResponse
    {
        $request->validate([
            'cursos'   => ['required', 'array'],
            'cursos.*' => ['integer', 'exists:cursos,id'],
        ]);

        $biblioteca->cursos()->sync($request->cursos);
        $biblioteca->load('cursos');

        return response()->json([
            'message' => 'Cursos sincronizados correctamente.',
            'data'    => new BibliotecaResource($biblioteca),
        ]);
    }

    /**
     * Descarga o devuelve la URL temporal del archivo.
     */
    public function download(Biblioteca $biblioteca): JsonResponse
    {
        if (! Storage::disk('public')->exists($biblioteca->ruta)) {
            return response()->json([
                'message' => 'El archivo no se encontró en el servidor.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'url'          => asset('storage/' . $biblioteca->ruta),
                'nombre'       => $biblioteca->nombre,
                'tipo_archivo' => $biblioteca->tipo_archivo,
                'tamanio'      => $biblioteca->tamanio,
            ],
        ]);
    }

    /**
     * Devuelve las opciones disponibles para los filtros del listado.
     */
    public function filters(): JsonResponse
    {
        $tiposArchivo = Biblioteca::selectRaw('tipo_archivo')
            ->whereNotNull('tipo_archivo')
            ->distinct()
            ->orderBy('tipo_archivo')
            ->pluck('tipo_archivo');

        return response()->json([
            'data' => [
                'tipos_archivo' => $tiposArchivo,
                'status'        => [
                    ['value' => 1, 'label' => 'Activo'],
                    ['value' => 0, 'label' => 'Inactivo'],
                ],
            ],
        ]);
    }

    /**
     * Devuelve estadísticas generales del módulo Biblioteca.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'totales' => [
                'total'      => Biblioteca::count(),
                'activos'    => Biblioteca::where('status', 1)->count(),
                'inactivos'  => Biblioteca::where('status', 0)->count(),
                'eliminados' => Biblioteca::onlyTrashed()->count(),
                'vigentes'   => Biblioteca::vigentes()->count(),
                'obsoletos'  => Biblioteca::obsoletos()->count(),
            ],
            'por_tipo_archivo' => Biblioteca::selectRaw('tipo_archivo, count(*) as total')
                ->whereNotNull('tipo_archivo')
                ->groupBy('tipo_archivo')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => [
                    'tipo'  => $row->tipo_archivo,
                    'total' => $row->total,
                ]),
            'por_curso' => Biblioteca::with('cursos')
                ->get()
                ->flatMap(fn ($doc) => $doc->cursos->map(fn ($curso) => $curso->nombre))
                ->countBy()
                ->sortDesc()
                ->take(10)
                ->map(fn ($count, $nombre) => ['curso' => $nombre, 'total' => $count])
                ->values(),
        ];

        return response()->json([
            'data' => $stats,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Genera la ruta de destino del archivo con el formato:
     *   biblioteca/YYYY/MM/YYYYMMDDHHmm_nombre_del_documento.ext
     *
     * El prefijo de fecha y hora garantiza unicidad sin colisiones.
     * Los caracteres no alfanuméricos del nombre se reemplazan por guión bajo
     * y el resultado se pasa a minúsculas para uniformidad.
     */
    private function generarRutaArchivo(string $nombre, string $extension): string
    {
        $prefijo          = now()->format('YmdHi');
        $nombreSanitizado = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($nombre)));
        $nombreSanitizado = trim($nombreSanitizado, '_');

        return 'biblioteca/' . "{$prefijo}_{$nombreSanitizado}.{$extension}";
    }

    /**
     * Elimina el archivo físico del disco público si existe.
     */
    private function eliminarArchivo(string $ruta): void
    {
        if (Storage::disk('public')->exists($ruta)) {
            Storage::disk('public')->delete($ruta);
        }
    }
}
