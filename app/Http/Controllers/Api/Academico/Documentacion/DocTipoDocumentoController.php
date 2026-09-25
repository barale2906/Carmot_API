<?php

namespace App\Http\Controllers\Api\Academico\Documentacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\Documentacion\StoreDocTipoDocumentoRequest;
use App\Http\Requests\Api\Academico\Documentacion\SyncDocTipoDocumentoVariablesRequest;
use App\Http\Requests\Api\Academico\Documentacion\UpdateDocTipoDocumentoRequest;
use App\Http\Resources\Api\Academico\Documentacion\DocTipoDocumentoResource;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Services\Academico\Documentacion\DocVariableResolverService;
use App\Traits\HasActiveStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para la gestión de tipos de documento del módulo Documentación.
 *
 * Administra el catálogo de documentos que el instituto puede generar
 * (contrato, pagaré, hoja de matrícula, certificados, cartas) y la selección
 * de variables del sistema que quedan disponibles para redactar sus plantillas.
 *
 * @package App\Http\Controllers\Api\Academico\Documentacion
 */
class DocTipoDocumentoController extends Controller
{
    use HasActiveStatus;

    /** Campos permitidos en store/update. */
    private const FILLABLE_FIELDS = [
        'codigo',
        'nombre',
        'descripcion',
        'entidad_type',
        'se_ata_fecha',
        'campo_fecha_referencia',
        'prefijo_numero',
        'status',
    ];

    /**
     * Registra los middlewares de permisos del módulo.
     *
     * @param DocVariableResolverService $variables Catálogo de variables del sistema.
     */
    public function __construct(private DocVariableResolverService $variables)
    {
        $this->middleware('permission:aca_docTipos')->only(['index', 'show', 'filters', 'variablesDisponibles']);
        $this->middleware('permission:aca_docTipoCrear')->only(['store']);
        $this->middleware('permission:aca_docTipoEditar')->only(['update']);
        $this->middleware('permission:aca_docTipoVariables')->only(['sincronizarVariables']);
        $this->middleware('permission:aca_docTipoInactivar')->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Lista paginada de tipos de documento con filtros opcionales.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters   = $request->only(['search', 'status', 'include_trashed', 'only_trashed']);
        $relations = $request->has('with') ? explode(',', $request->with) : [];

        $tipos = DocTipoDocumento::withFilters($filters)
            ->withRelationsAndCounts($relations, true)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => DocTipoDocumentoResource::collection($tipos),
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
     * Crea un nuevo tipo de documento con sus variables habilitadas.
     *
     * @param StoreDocTipoDocumentoRequest $request
     * @return JsonResponse
     */
    public function store(StoreDocTipoDocumentoRequest $request): JsonResponse
    {
        $data           = $request->only(self::FILLABLE_FIELDS);
        $data['status'] = $data['status'] ?? 1;

        $tipoDocumento = DB::transaction(function () use ($request, $data) {
            $tipoDocumento = DocTipoDocumento::create($data);
            $this->guardarVariables($tipoDocumento, $request->input('variables', []));

            return $tipoDocumento;
        });

        return response()->json([
            'message' => 'Tipo de documento creado exitosamente.',
            'data'    => new DocTipoDocumentoResource($tipoDocumento->load('variables')),
        ], 201);
    }

    /**
     * Muestra el detalle de un tipo de documento con sus variables habilitadas.
     *
     * @param DocTipoDocumento $tipoDocumento
     * @return JsonResponse
     */
    public function show(DocTipoDocumento $tipoDocumento): JsonResponse
    {
        return response()->json([
            'data' => new DocTipoDocumentoResource($tipoDocumento->load('variables')),
        ]);
    }

    /**
     * Actualiza un tipo de documento.
     *
     * @param UpdateDocTipoDocumentoRequest $request
     * @param DocTipoDocumento              $tipoDocumento
     * @return JsonResponse
     */
    public function update(UpdateDocTipoDocumentoRequest $request, DocTipoDocumento $tipoDocumento): JsonResponse
    {
        $tipoDocumento->update($request->only(self::FILLABLE_FIELDS));

        return response()->json([
            'message' => 'Tipo de documento actualizado exitosamente.',
            'data'    => new DocTipoDocumentoResource($tipoDocumento->fresh()->load('variables')),
        ]);
    }

    /**
     * Elimina (soft delete) un tipo de documento.
     *
     * @param DocTipoDocumento $tipoDocumento
     * @return JsonResponse
     */
    public function destroy(DocTipoDocumento $tipoDocumento): JsonResponse
    {
        if ($tipoDocumento->plantillas()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el tipo de documento porque tiene plantillas asociadas.',
            ], 422);
        }

        $tipoDocumento->delete();

        return response()->json([
            'message' => 'Tipo de documento eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un tipo de documento eliminado.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $tipoDocumento = DocTipoDocumento::onlyTrashed()->findOrFail($id);
        $tipoDocumento->restore();

        return response()->json([
            'message' => 'Tipo de documento restaurado exitosamente.',
            'data'    => new DocTipoDocumentoResource($tipoDocumento->load('variables')),
        ]);
    }

    /**
     * Elimina permanentemente un tipo de documento y sus variables habilitadas.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $tipoDocumento = DocTipoDocumento::onlyTrashed()->findOrFail($id);

        if ($tipoDocumento->plantillas()->withTrashed()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar permanentemente el tipo de documento porque tiene plantillas asociadas.',
            ], 422);
        }

        $tipoDocumento->forceDelete();

        return response()->json([
            'message' => 'Tipo de documento eliminado permanentemente.',
        ]);
    }

    /**
     * Lista los tipos de documento eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters                 = $request->only(['search', 'status']);
        $filters['only_trashed'] = true;

        $tipos = DocTipoDocumento::withFilters($filters)
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => DocTipoDocumentoResource::collection($tipos),
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
     * Catálogos de filtros y opciones para el formulario de tipos de documento.
     *
     * Devuelve los estados disponibles y las entidades del sistema a las que se
     * puede asociar un documento, con los campos de fecha válidos de cada una
     * para configurar la vigencia atada a fecha.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        $entidades = [];

        foreach (config('documentacion.entidades', []) as $clase => $definicion) {
            $entidades[] = [
                'entidad_type' => $clase,
                'nombre'       => $definicion['nombre'],
                'campos_fecha' => $definicion['campos_fecha'],
            ];
        }

        return response()->json([
            'data' => [
                'status_options' => self::getActiveStatusOptions(),
                'entidades'      => $entidades,
            ],
        ]);
    }

    /**
     * Variables del catálogo disponibles para un tipo de documento.
     *
     * Lista las variables de la entidad asociada más las globales, marcando
     * cuáles están habilitadas actualmente para insertarse en las plantillas.
     *
     * @param DocTipoDocumento $tipoDocumento
     * @return JsonResponse
     */
    public function variablesDisponibles(DocTipoDocumento $tipoDocumento): JsonResponse
    {
        $habilitadas = $tipoDocumento->clavesHabilitadas();

        $catalogo = array_map(
            fn (array $variable) => $variable + ['habilitada' => in_array($variable['clave'], $habilitadas, true)],
            $this->variables->catalogo($tipoDocumento->entidad_type)
        );

        return response()->json([
            'data' => $catalogo,
        ]);
    }

    /**
     * Define cuáles variables quedan habilitadas para un tipo de documento.
     *
     * Reemplaza la selección completa: las variables que no vengan en la
     * petición dejan de estar disponibles para las plantillas de este tipo.
     *
     * @param SyncDocTipoDocumentoVariablesRequest $request
     * @param DocTipoDocumento                     $tipoDocumento
     * @return JsonResponse
     */
    public function sincronizarVariables(
        SyncDocTipoDocumentoVariablesRequest $request,
        DocTipoDocumento $tipoDocumento
    ): JsonResponse {
        DB::transaction(function () use ($request, $tipoDocumento) {
            $tipoDocumento->variables()->delete();
            $this->guardarVariables($tipoDocumento, $request->input('variables', []));
        });

        return response()->json([
            'message' => 'Variables habilitadas actualizadas exitosamente.',
            'data'    => new DocTipoDocumentoResource($tipoDocumento->fresh()->load('variables')),
        ]);
    }

    /**
     * Registra las variables habilitadas de un tipo de documento.
     *
     * @param DocTipoDocumento   $tipoDocumento
     * @param array<int, string> $claves
     * @return void
     */
    private function guardarVariables(DocTipoDocumento $tipoDocumento, array $claves): void
    {
        foreach (array_unique($claves) as $clave) {
            $tipoDocumento->variables()->create(['variable_key' => $clave]);
        }
    }
}
