<?php

namespace App\Http\Controllers\Api\Academico\Documentacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\Documentacion\GenerarDocDocumentoRequest;
use App\Http\Resources\Api\Academico\Documentacion\DocDocumentoResource;
use App\Models\Academico\Documentacion\DocDocumento;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Services\Academico\Documentacion\DocGeneracionService;
use App\Services\Academico\Documentacion\DocPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Controlador para imprimir documentos y consultar la bitácora de impresiones.
 *
 * Los documentos se arman cada vez que se piden, con los datos del estudiante y
 * la plantilla que corresponde: la vigente a la fecha de la matrícula cuando el
 * tipo la conforma, o la vigente hoy en los demás casos. No se almacena ni el
 * contenido ni el PDF; de cada impresión queda solo su rastro en la bitácora.
 *
 * @package App\Http\Controllers\Api\Academico\Documentacion
 */
class DocDocumentoController extends Controller
{
    /**
     * Registra los middlewares de permisos del módulo.
     *
     * @param DocGeneracionService $generacion Armado del contenido y bitácora.
     * @param DocPdfService        $pdf        Conversión a PDF.
     */
    public function __construct(
        private DocGeneracionService $generacion,
        private DocPdfService $pdf
    ) {
        $this->middleware('permission:aca_documentos')->only(['index', 'show', 'filters']);
        $this->middleware('permission:aca_documentoGenerar')->only(['render', 'pdf']);
        $this->middleware('permission:aca_documentoAnular')
            ->only(['destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Arma el documento y devuelve su contenido en HTML.
     *
     * Sirve para mostrarlo en pantalla antes de imprimirlo. Cada llamada registra
     * la impresión en la bitácora.
     *
     * @param GenerarDocDocumentoRequest $request
     * @return JsonResponse
     */
    public function render(GenerarDocDocumentoRequest $request): JsonResponse
    {
        $tipoDocumento = DocTipoDocumento::findOrFail($request->integer('tipo_documento_id'));
        $entidad       = $this->generacion->resolverEntidad($tipoDocumento, $request->integer('entidad_id'));
        $vigencia      = $this->generacion->resolverVigencia($tipoDocumento, $entidad);

        if (!$vigencia['plantilla']) {
            return response()->json([
                'message' => 'No hay una versión de plantilla vigente para la fecha de este documento.',
            ], 422);
        }

        $contenido = $this->generacion->renderizar($vigencia['plantilla'], $entidad, $request->user());

        $emision = $this->generacion->registrarEmision(
            $tipoDocumento,
            $vigencia['plantilla'],
            $entidad,
            $vigencia['fecha_referencia'],
            $request->user()
        );

        return response()->json([
            'data' => [
                'emision_id'       => $emision->id,
                'tipo_documento'   => $tipoDocumento->nombre,
                'plantilla_id'     => $vigencia['plantilla']->id,
                'version'          => $vigencia['plantilla']->version,
                'fecha_referencia' => $vigencia['fecha_referencia']?->toDateString(),
                'contenido'        => $contenido,
            ],
        ]);
    }

    /**
     * Arma el documento y lo descarga en PDF.
     *
     * El PDF se construye en el momento y no se almacena. Cada descarga registra
     * la impresión en la bitácora.
     *
     * @param GenerarDocDocumentoRequest $request
     * @return HttpResponse
     */
    public function pdf(GenerarDocDocumentoRequest $request): HttpResponse
    {
        $tipoDocumento = DocTipoDocumento::findOrFail($request->integer('tipo_documento_id'));
        $entidad       = $this->generacion->resolverEntidad($tipoDocumento, $request->integer('entidad_id'));
        $vigencia      = $this->generacion->resolverVigencia($tipoDocumento, $entidad);

        if (!$vigencia['plantilla']) {
            return response()->json([
                'message' => 'No hay una versión de plantilla vigente para la fecha de este documento.',
            ], 422);
        }

        $contenido = $this->generacion->renderizar($vigencia['plantilla'], $entidad, $request->user());

        $this->generacion->registrarEmision(
            $tipoDocumento,
            $vigencia['plantilla'],
            $entidad,
            $vigencia['fecha_referencia'],
            $request->user()
        );

        return $this->pdf->generarPDF($tipoDocumento, $contenido)
            ->download($this->pdf->nombreArchivo($tipoDocumento, $entidad?->getKey()));
    }

    /**
     * Lista paginada de la bitácora de impresiones y de los archivos subidos.
     *
     * Admite filtrar por tipo, origen y por el registro asociado (`entidad_type` +
     * `entidad_id`), que es la forma de ver todo lo emitido para una matrícula.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'tipo_documento_id', 'origen',
            'entidad_type', 'entidad_id', 'include_trashed', 'only_trashed',
        ]);

        $documentos = DocDocumento::withFilters($filters)
            ->with(['tipoDocumento', 'generador'])
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => DocDocumentoResource::collection($documentos),
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
     * Muestra una entrada de la bitácora.
     *
     * @param DocDocumento $documento
     * @return JsonResponse
     */
    public function show(DocDocumento $documento): JsonResponse
    {
        return response()->json([
            'data' => new DocDocumentoResource($documento->load(['tipoDocumento', 'plantilla', 'generador'])),
        ]);
    }

    /**
     * Elimina (soft delete) una entrada de la bitácora o un archivo subido.
     *
     * @param DocDocumento $documento
     * @return JsonResponse
     */
    public function destroy(DocDocumento $documento): JsonResponse
    {
        $documento->delete();

        return response()->json([
            'message' => 'Registro eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura una entrada eliminada.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $documento = DocDocumento::onlyTrashed()->findOrFail($id);
        $documento->restore();

        return response()->json([
            'message' => 'Registro restaurado exitosamente.',
            'data'    => new DocDocumentoResource($documento->load('tipoDocumento')),
        ]);
    }

    /**
     * Elimina permanentemente una entrada.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $documento = DocDocumento::onlyTrashed()->findOrFail($id);
        $documento->forceDelete();

        return response()->json([
            'message' => 'Registro eliminado permanentemente.',
        ]);
    }

    /**
     * Lista las entradas eliminadas (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters                 = $request->only(['search', 'tipo_documento_id', 'origen']);
        $filters['only_trashed'] = true;

        $documentos = DocDocumento::withFilters($filters)
            ->with('tipoDocumento')
            ->withSorting($request->get('sort_by'), $request->get('sort_direction'))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => DocDocumentoResource::collection($documentos),
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
     * Opciones de origen disponibles para filtrar la bitácora.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'origen_options' => DocDocumento::getOrigenOptions(),
            ],
        ]);
    }
}
