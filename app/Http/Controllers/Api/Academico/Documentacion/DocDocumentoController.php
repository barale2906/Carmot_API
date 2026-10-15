<?php

namespace App\Http\Controllers\Api\Academico\Documentacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\Documentacion\GenerarDocDocumentoRequest;
use App\Http\Requests\Api\Academico\Documentacion\SubirEscaneadoMatriculaRequest;
use App\Http\Resources\Api\Academico\Documentacion\DocDocumentoResource;
use App\Models\Academico\Documentacion\DocDocumento;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\Academico\Matricula;
use App\Services\Academico\Documentacion\DocEscaneadoService;
use App\Services\Academico\Documentacion\DocGeneracionService;
use App\Services\Academico\Documentacion\DocPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Controlador para imprimir documentos y consultar la bitácora de impresiones.
 *
 * Los documentos se arman cada vez que se piden, con los datos del estudiante y
 * la plantilla que corresponde: la vigente a la fecha de la matrícula cuando el
 * tipo la conforma, o la vigente hoy en los demás casos. No se almacena ni el
 * contenido ni el PDF; de cada impresión queda solo su rastro en la bitácora.
 *
 * También recibe los escaneados firmados de los documentos de una matrícula, que
 * sí se guardan (en el disco privado) y se descargan con sesión.
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
     * @param DocEscaneadoService  $escaneados Almacenamiento de los escaneados firmados.
     */
    public function __construct(
        private DocGeneracionService $generacion,
        private DocPdfService $pdf,
        private DocEscaneadoService $escaneados
    ) {
        $this->middleware('permission:aca_documentos')->only(['index', 'show', 'filters', 'archivo']);
        $this->middleware('permission:aca_documentoGenerar')->only(['render', 'pdf', 'generarMatricula', 'pdfMatricula']);
        $this->middleware('permission:aca_documentoSubir')->only(['subirEscaneado']);
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

        return $this->pdf->generarPDF($tipoDocumento, $contenido, $entidad)
            ->download($this->pdf->nombreArchivo($tipoDocumento, $entidad?->getKey()));
    }

    /**
     * Genera los documentos vigentes que conforman una matrícula.
     *
     * Es el paso que sigue al registro de la matrícula y precede a los recibos de
     * pago: arma, con la versión vigente a la fecha de la matrícula, cada tipo
     * activo que la conforma y registra su impresión en la bitácora. Los tipos sin
     * versión vigente se devuelven en `sin_plantilla` para advertirlos sin
     * bloquear el flujo.
     *
     * @param Request   $request
     * @param Matricula $matricula
     * @return JsonResponse
     */
    public function generarMatricula(Request $request, Matricula $matricula): JsonResponse
    {
        $resultado = $this->generacion->generarDocumentosMatricula($matricula, $request->user());

        return response()->json([
            'data'    => $resultado,
            'message' => count($resultado['documentos'])
                ? 'Documentos de la matrícula generados exitosamente.'
                : 'No hay documentos vigentes que conformen la matrícula.',
        ]);
    }

    /**
     * Descarga en un solo PDF la hoja de matrícula y los documentos que la conforman.
     *
     * Arma los documentos igual que `generarMatricula` —versión vigente a la fecha
     * de la matrícula, impresión registrada en la bitácora— y los une detrás de la
     * hoja de matrícula, cada uno en una página nueva. Los tipos sin versión
     * vigente se omiten. El PDF no se almacena.
     *
     * @param Request   $request
     * @param Matricula $matricula
     * @return HttpResponse
     */
    public function pdfMatricula(Request $request, Matricula $matricula): HttpResponse
    {
        $resultado = $this->generacion->generarDocumentosMatricula($matricula, $request->user());

        return $this->pdf->generarPdfMatricula($matricula, $resultado['documentos'])
            ->download($this->pdf->nombreArchivoMatricula($matricula));
    }

    /**
     * Carga el escaneado firmado de un documento de la matrícula.
     *
     * El documento se identifica por `tipo_documento_id` o, si no tiene tipo (la
     * hoja de matrícula u otro soporte), por `descripcion`. Si ya había un
     * escaneado del mismo documento, el anterior pasa a la papelera.
     *
     * @param SubirEscaneadoMatriculaRequest $request
     * @param Matricula                      $matricula
     * @return JsonResponse
     */
    public function subirEscaneado(SubirEscaneadoMatriculaRequest $request, Matricula $matricula): JsonResponse
    {
        $tipoDocumento = $request->filled('tipo_documento_id')
            ? DocTipoDocumento::find($request->integer('tipo_documento_id'))
            : null;

        $documento = $this->escaneados->subir(
            $matricula,
            $request->file('archivo'),
            $tipoDocumento,
            $request->input('descripcion'),
            $request->user()
        );

        return response()->json([
            'message' => 'Documento escaneado cargado exitosamente.',
            'data'    => new DocDocumentoResource($documento->load(['tipoDocumento', 'generador'])),
        ], 201);
    }

    /**
     * Descarga el archivo de un documento escaneado.
     *
     * @param DocDocumento $documento
     * @return HttpResponse
     */
    public function archivo(DocDocumento $documento): HttpResponse
    {
        $disco = Storage::disk(DocDocumento::DISCO_ARCHIVOS);

        if (!$documento->archivo_path || !$disco->exists($documento->archivo_path)) {
            return response()->json([
                'message' => 'El documento no tiene un archivo disponible.',
            ], 404);
        }

        return $disco->download($documento->archivo_path, $documento->nombre_original);
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
     * Elimina permanentemente una entrada y, si es un escaneado, su archivo.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $documento = DocDocumento::onlyTrashed()->findOrFail($id);
        $this->escaneados->eliminarArchivo($documento);
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
