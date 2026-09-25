<?php

namespace App\Http\Controllers\Api\Academico\Documentacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\Documentacion\ActivarDocPlantillaRequest;
use App\Http\Requests\Api\Academico\Documentacion\CloneDocPlantillaRequest;
use App\Http\Requests\Api\Academico\Documentacion\PrevisualizarDocPlantillaRequest;
use App\Http\Requests\Api\Academico\Documentacion\StoreDocPlantillaRequest;
use App\Http\Requests\Api\Academico\Documentacion\SyncDocPlantillaBloquesRequest;
use App\Http\Requests\Api\Academico\Documentacion\UpdateDocPlantillaRequest;
use App\Http\Resources\Api\Academico\Documentacion\DocPlantillaResource;
use App\Models\Academico\Documentacion\DocPlantilla;
use App\Services\Academico\Documentacion\DocBloqueRenderService;
use App\Services\Academico\Documentacion\DocGeneracionService;
use App\Services\Academico\Documentacion\DocPlantillaPublicacionService;
use App\Services\Academico\Documentacion\DocVariableResolverService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para la gestión de versiones de plantilla de documentos.
 *
 * Cada plantilla es una versión del contenido de un tipo de documento y sigue
 * el flujo En Proceso → Aprobada → Activa. Al activar una versión se cierra la
 * vigencia de la anterior, conservando el histórico necesario para volver a
 * generar un documento con el contenido que aplicaba en su momento.
 *
 * @package App\Http\Controllers\Api\Academico\Documentacion
 */
class DocPlantillaController extends Controller
{
    /** Columnas del listado; excluye el contenido para no arrastrar el HTML completo. */
    private const COLUMNAS_LISTADO = [
        'id', 'tipo_documento_id', 'nombre', 'version', 'status',
        'fecha_inicio', 'fecha_fin', 'version_anterior_id', 'creado_por',
        'created_at', 'updated_at', 'deleted_at',
    ];

    /**
     * Registra los middlewares de permisos del módulo.
     *
     * @param DocPlantillaPublicacionService $publicacion Publicación y cierre de vigencias.
     * @param DocVariableResolverService     $variables   Catálogo de variables del sistema.
     * @param DocBloqueRenderService         $bloques     Catálogo y armado de los bloques.
     * @param DocGeneracionService           $generacion  Previsualización del contenido.
     */
    public function __construct(
        private DocPlantillaPublicacionService $publicacion,
        private DocVariableResolverService $variables,
        private DocBloqueRenderService $bloques,
        private DocGeneracionService $generacion
    ) {
        $this->middleware('permission:aca_docPlantillas')
            ->only(['index', 'show', 'filters', 'bloquesDisponibles', 'previsualizar']);
        $this->middleware('permission:aca_docPlantillaCrear')->only(['store']);
        $this->middleware('permission:aca_docPlantillaEditar')->only(['update', 'sincronizarBloques']);
        $this->middleware('permission:aca_docPlantillaAprobar')->only(['aprobar', 'activar']);
        $this->middleware('permission:aca_docPlantillaClonar')->only(['clonar']);
        $this->middleware('permission:aca_docPlantillaInactivar')
            ->only(['inactivar', 'destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Lista paginada de versiones de plantilla con filtros opcionales.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'include_trashed', 'only_trashed']);

        $plantillas = DocPlantilla::withFilters($filters)
            ->when(
                $request->filled('tipo_documento_id'),
                fn ($q) => $q->where('tipo_documento_id', $request->integer('tipo_documento_id'))
            )
            ->with('tipoDocumento')
            ->select(self::COLUMNAS_LISTADO)
            ->withSorting($request->get('sort_by', 'version'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => DocPlantillaResource::collection($plantillas),
            'meta' => [
                'current_page' => $plantillas->currentPage(),
                'last_page'    => $plantillas->lastPage(),
                'per_page'     => $plantillas->perPage(),
                'total'        => $plantillas->total(),
                'from'         => $plantillas->firstItem(),
                'to'           => $plantillas->lastItem(),
            ],
        ]);
    }

    /**
     * Crea una nueva versión de plantilla en estado En Proceso.
     *
     * El número de versión se asigna automáticamente como el siguiente
     * consecutivo dentro del tipo de documento.
     *
     * @param StoreDocPlantillaRequest $request
     * @return JsonResponse
     */
    public function store(StoreDocPlantillaRequest $request): JsonResponse
    {
        $plantilla = DocPlantilla::create([
            'tipo_documento_id' => $request->integer('tipo_documento_id'),
            'nombre'            => $request->string('nombre'),
            'contenido_html'    => $request->input('contenido_html'),
            'version'           => DocPlantilla::siguienteVersion($request->integer('tipo_documento_id')),
            'status'            => DocPlantilla::STATUS_EN_PROCESO,
            'creado_por'        => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Versión de plantilla creada exitosamente.',
            'data'    => new DocPlantillaResource($plantilla->load('tipoDocumento')),
        ], 201);
    }

    /**
     * Muestra el detalle y el contenido de una versión de plantilla.
     *
     * @param DocPlantilla $plantilla
     * @return JsonResponse
     */
    public function show(DocPlantilla $plantilla): JsonResponse
    {
        return response()->json([
            'data' => new DocPlantillaResource($plantilla->load(['tipoDocumento', 'creador'])),
        ]);
    }

    /**
     * Actualiza una versión de plantilla.
     *
     * Solo se permite modificar versiones en estado En Proceso: una vez
     * aprobada, el contenido queda congelado y los cambios deben hacerse
     * clonando la versión.
     *
     * @param UpdateDocPlantillaRequest $request
     * @param DocPlantilla              $plantilla
     * @return JsonResponse
     */
    public function update(UpdateDocPlantillaRequest $request, DocPlantilla $plantilla): JsonResponse
    {
        if ($plantilla->status !== DocPlantilla::STATUS_EN_PROCESO) {
            return response()->json([
                'message' => 'Solo se pueden editar versiones en estado "En Proceso".',
            ], 422);
        }

        $plantilla->update($request->only(['nombre', 'contenido_html']));

        return response()->json([
            'message' => 'Versión de plantilla actualizada exitosamente.',
            'data'    => new DocPlantillaResource($plantilla->fresh()->load('tipoDocumento')),
        ]);
    }

    /**
     * Aprueba una versión de plantilla pasando de En Proceso → Aprobada.
     *
     * Antes de aprobar se revalida el contenido contra las variables
     * habilitadas del tipo, porque la selección pudo cambiar mientras la
     * versión estaba en borrador.
     *
     * @param DocPlantilla $plantilla
     * @return JsonResponse
     */
    public function aprobar(DocPlantilla $plantilla): JsonResponse
    {
        if ($plantilla->status !== DocPlantilla::STATUS_EN_PROCESO) {
            return response()->json([
                'message' => 'Solo se pueden aprobar versiones en estado "En Proceso".',
            ], 422);
        }

        $noHabilitadas = $this->variables->variablesNoHabilitadas(
            $plantilla->contenido_html,
            $plantilla->tipoDocumento
        );

        if ($noHabilitadas) {
            return response()->json([
                'message'   => 'El contenido usa variables que no están habilitadas para este tipo de documento.',
                'variables' => $noHabilitadas,
            ], 422);
        }

        $plantilla->update(['status' => DocPlantilla::STATUS_APROBADA]);

        return response()->json([
            'message' => 'Versión de plantilla aprobada exitosamente.',
            'data'    => new DocPlantillaResource($plantilla->fresh()->load('tipoDocumento')),
        ]);
    }

    /**
     * Activa una versión de plantilla pasando de Aprobada → Activa.
     *
     * La vigencia arranca en la fecha indicada (hoy por defecto) y debe ser
     * posterior al inicio de la versión activa actual, que queda cerrada el día
     * anterior para que las ventanas de vigencia no se solapen.
     *
     * @param ActivarDocPlantillaRequest $request
     * @param DocPlantilla               $plantilla
     * @return JsonResponse
     */
    public function activar(ActivarDocPlantillaRequest $request, DocPlantilla $plantilla): JsonResponse
    {
        if ($plantilla->status !== DocPlantilla::STATUS_APROBADA) {
            return response()->json([
                'message' => 'Solo se pueden activar versiones en estado "Aprobada".',
            ], 422);
        }

        $fechaInicio = $request->filled('fecha_inicio')
            ? Carbon::parse($request->input('fecha_inicio'))->startOfDay()
            : Carbon::today();

        $vigente = $this->publicacion->versionActiva($plantilla);

        if ($vigente && $vigente->fecha_inicio && $fechaInicio->lessThanOrEqualTo($vigente->fecha_inicio)) {
            return response()->json([
                'message' => 'La fecha de inicio de vigencia debe ser posterior al inicio de la versión activa actual ('
                    . $vigente->fecha_inicio->toDateString() . ').',
            ], 422);
        }

        $plantilla = $this->publicacion->activar($plantilla, $fechaInicio);

        return response()->json([
            'message' => 'Versión de plantilla activada exitosamente.',
            'data'    => new DocPlantillaResource($plantilla->load('tipoDocumento')),
        ]);
    }

    /**
     * Inactiva una versión de plantilla cerrando su vigencia.
     *
     * @param DocPlantilla $plantilla
     * @return JsonResponse
     */
    public function inactivar(DocPlantilla $plantilla): JsonResponse
    {
        $plantilla = $this->publicacion->inactivar($plantilla);

        return response()->json([
            'message' => 'Versión de plantilla inactivada exitosamente.',
            'data'    => new DocPlantillaResource($plantilla->load('tipoDocumento')),
        ]);
    }

    /**
     * Clona una versión de plantilla en una nueva versión En Proceso.
     *
     * Es la vía para modificar un documento ya publicado: la versión original
     * queda intacta y la nueva arranca como borrador editable.
     *
     * @param CloneDocPlantillaRequest $request
     * @param DocPlantilla             $plantilla
     * @return JsonResponse
     */
    public function clonar(CloneDocPlantillaRequest $request, DocPlantilla $plantilla): JsonResponse
    {
        $nueva = DB::transaction(function () use ($request, $plantilla) {
            $nueva = DocPlantilla::create([
                'tipo_documento_id'   => $plantilla->tipo_documento_id,
                'nombre'              => $request->string('nombre'),
                'contenido_html'      => $request->input('contenido_html', $plantilla->contenido_html),
                'version'             => DocPlantilla::siguienteVersion($plantilla->tipo_documento_id),
                'status'              => DocPlantilla::STATUS_EN_PROCESO,
                'version_anterior_id' => $plantilla->id,
                'creado_por'          => $request->user()?->id,
            ]);

            foreach ($plantilla->bloques as $bloque) {
                $nueva->bloques()->create($bloque->only(['bloque_key', 'columnas', 'titulos', 'mostrar_resumen']));
            }

            return $nueva;
        });

        return response()->json([
            'message' => 'Versión de plantilla clonada exitosamente.',
            'data'    => new DocPlantillaResource($nueva->load('tipoDocumento')),
        ], 201);
    }

    /**
     * Bloques disponibles para la versión, con sus columnas y la configuración guardada.
     *
     * Es la fuente del selector de bloques del editor: por cada bloque del
     * catálogo indica qué columnas existen y, si ya se configuró, cuáles se
     * imprimen, con qué títulos y si lleva fila de resumen.
     *
     * @param DocPlantilla $plantilla
     * @return JsonResponse
     */
    public function bloquesDisponibles(DocPlantilla $plantilla): JsonResponse
    {
        $plantilla->loadMissing(['tipoDocumento', 'bloques']);

        $configuradas = $plantilla->bloques->keyBy('bloque_key');

        $catalogo = array_map(function (array $bloque) use ($configuradas) {
            $config = $configuradas->get($bloque['clave']);

            return $bloque + [
                'configurado'     => $config !== null,
                'columnas_activas' => $config?->columnas ?? array_column($bloque['columnas'], 'clave'),
                'titulos'          => $config?->titulos ?? [],
                'mostrar_resumen'  => $config?->mostrar_resumen ?? true,
            ];
        }, $this->bloques->catalogo($plantilla->tipoDocumento->entidad_type));

        return response()->json([
            'data' => $catalogo,
        ]);
    }

    /**
     * Define cómo se imprimen los bloques de una versión de plantilla.
     *
     * Reemplaza la configuración completa. Solo se permite en versiones En
     * Proceso: cambiar las columnas de una tabla es un cambio de diseño y debe
     * pasar por el mismo flujo de aprobación que cambiar el texto.
     *
     * @param SyncDocPlantillaBloquesRequest $request
     * @param DocPlantilla                   $plantilla
     * @return JsonResponse
     */
    public function sincronizarBloques(SyncDocPlantillaBloquesRequest $request, DocPlantilla $plantilla): JsonResponse
    {
        if ($plantilla->status !== DocPlantilla::STATUS_EN_PROCESO) {
            return response()->json([
                'message' => 'Solo se pueden configurar los bloques de versiones en estado "En Proceso".',
            ], 422);
        }

        DB::transaction(function () use ($request, $plantilla) {
            $plantilla->bloques()->delete();

            foreach ($request->input('bloques', []) as $config) {
                $plantilla->bloques()->create([
                    'bloque_key'      => $config['bloque'],
                    'columnas'        => array_values($config['columnas']),
                    'titulos'         => $config['titulos'] ?? null,
                    'mostrar_resumen' => $config['mostrar_resumen'] ?? true,
                ]);
            }
        });

        return response()->json([
            'message' => 'Bloques de la plantilla actualizados exitosamente.',
            'data'    => new DocPlantillaResource($plantilla->fresh()->load(['tipoDocumento', 'bloques'])),
        ]);
    }

    /**
     * Previsualiza el contenido de una versión con datos reales.
     *
     * Devuelve el HTML con variables y bloques ya resueltos, sin registrar la
     * impresión en la bitácora, para revisar el diseño antes de publicar.
     *
     * @param PrevisualizarDocPlantillaRequest $request
     * @param DocPlantilla                     $plantilla
     * @return JsonResponse
     */
    public function previsualizar(PrevisualizarDocPlantillaRequest $request, DocPlantilla $plantilla): JsonResponse
    {
        $plantilla->loadMissing('tipoDocumento');

        $entidad = $this->generacion->resolverEntidad(
            $plantilla->tipoDocumento,
            $request->integer('entidad_id')
        );

        return response()->json([
            'data' => [
                'plantilla_id' => $plantilla->id,
                'version'      => $plantilla->version,
                'contenido'    => $this->generacion->renderizar($plantilla, $entidad, $request->user()),
            ],
        ]);
    }

    /**
     * Elimina (soft delete) una versión de plantilla.
     *
     * No se permite eliminar la versión activa: primero debe inactivarse o
     * reemplazarse por una versión nueva.
     *
     * @param DocPlantilla $plantilla
     * @return JsonResponse
     */
    public function destroy(DocPlantilla $plantilla): JsonResponse
    {
        if ($plantilla->status === DocPlantilla::STATUS_ACTIVA) {
            return response()->json([
                'message' => 'No se puede eliminar la versión activa. Inactívela o active una versión nueva.',
            ], 422);
        }

        $plantilla->delete();

        return response()->json([
            'message' => 'Versión de plantilla eliminada exitosamente.',
        ]);
    }

    /**
     * Restaura una versión de plantilla eliminada.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $plantilla = DocPlantilla::onlyTrashed()->findOrFail($id);
        $plantilla->restore();

        return response()->json([
            'message' => 'Versión de plantilla restaurada exitosamente.',
            'data'    => new DocPlantillaResource($plantilla->load('tipoDocumento')),
        ]);
    }

    /**
     * Elimina permanentemente una versión de plantilla.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $plantilla = DocPlantilla::onlyTrashed()->findOrFail($id);
        $plantilla->forceDelete();

        return response()->json([
            'message' => 'Versión de plantilla eliminada permanentemente.',
        ]);
    }

    /**
     * Lista las versiones de plantilla eliminadas (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters                 = $request->only(['search', 'status']);
        $filters['only_trashed'] = true;

        $plantillas = DocPlantilla::withFilters($filters)
            ->with('tipoDocumento')
            ->select(self::COLUMNAS_LISTADO)
            ->withSorting($request->get('sort_by', 'version'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => DocPlantillaResource::collection($plantillas),
            'meta' => [
                'current_page' => $plantillas->currentPage(),
                'last_page'    => $plantillas->lastPage(),
                'per_page'     => $plantillas->perPage(),
                'total'        => $plantillas->total(),
                'from'         => $plantillas->firstItem(),
                'to'           => $plantillas->lastItem(),
            ],
        ]);
    }

    /**
     * Opciones de estado disponibles para las versiones de plantilla.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status_options' => DocPlantilla::getStatusOptions(),
            ],
        ]);
    }
}
