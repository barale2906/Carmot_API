<?php

namespace App\Http\Controllers\Api\Academico\Documentacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Academico\Documentacion\AnularDocDocumentoRequest;
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
 * Controlador para la generación y consulta de documentos.
 *
 * Genera documentos a partir de la versión de plantilla vigente para la fecha
 * de referencia de cada tipo y conserva el contenido renderizado, de modo que
 * un documento emitido hoy siga mostrando lo mismo dentro de años.
 *
 * @package App\Http\Controllers\Api\Academico\Documentacion
 */
class DocDocumentoController extends Controller
{
    /** Columnas del listado; excluye el contenido renderizado y el detalle de variables. */
    private const COLUMNAS_LISTADO = [
        'id', 'tipo_documento_id', 'plantilla_id', 'entidad_type', 'entidad_id',
        'numero_documento', 'origen', 'fecha_referencia',
        'google_drive_url', 'nombre_original', 'mime_type', 'tamano_bytes',
        'status', 'motivo_anulacion', 'generado_por', 'created_at', 'updated_at', 'deleted_at',
    ];

    /**
     * Registra los middlewares de permisos del módulo.
     *
     * @param DocGeneracionService $generacion Generación y anulación de documentos.
     * @param DocPdfService        $pdf        Conversión del documento a PDF.
     */
    public function __construct(
        private DocGeneracionService $generacion,
        private DocPdfService $pdf
    ) {
        $this->middleware('permission:aca_documentos')->only(['index', 'show', 'filters', 'pdf']);
        $this->middleware('permission:aca_documentoGenerar')->only(['generar']);
        $this->middleware('permission:aca_documentoAnular')
            ->only(['anular', 'destroy', 'restore', 'forceDelete', 'trashed']);
    }

    /**
     * Lista paginada de documentos con filtros opcionales.
     *
     * Admite filtrar por tipo, estado, origen y por la entidad asociada
     * (`entidad_type` + `entidad_id`), que es la forma de consultar todos los
     * documentos de una matrícula o de un estudiante.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'status', 'tipo_documento_id', 'origen',
            'entidad_type', 'entidad_id', 'include_trashed', 'only_trashed',
        ]);

        $documentos = DocDocumento::withFilters($filters)
            ->with('tipoDocumento')
            ->select(self::COLUMNAS_LISTADO)
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
     * Genera un documento a partir de su tipo y del registro asociado.
     *
     * La versión de plantilla se resuelve según la configuración del tipo: los
     * tipos atados a fecha usan la vigente en la fecha de referencia de la
     * entidad; los demás, la vigente hoy.
     *
     * @param GenerarDocDocumentoRequest $request
     * @return JsonResponse
     */
    public function generar(GenerarDocDocumentoRequest $request): JsonResponse
    {
        $tipoDocumento = DocTipoDocumento::findOrFail($request->integer('tipo_documento_id'));
        $entidad       = $this->generacion->resolverEntidad($tipoDocumento, $request->integer('entidad_id'));
        $vigencia      = $this->generacion->resolverVigencia($tipoDocumento, $entidad);

        if (!$vigencia['plantilla']) {
            return response()->json([
                'message' => 'No hay una versión de plantilla vigente para la fecha de este documento.',
            ], 422);
        }

        $documento = $this->generacion->generar(
            $tipoDocumento,
            $vigencia['plantilla'],
            $entidad,
            $vigencia['fecha_referencia'],
            $request->user()
        );

        return response()->json([
            'message' => 'Documento generado exitosamente.',
            'data'    => new DocDocumentoResource($documento->load(['tipoDocumento', 'plantilla'])),
        ], 201);
    }

    /**
     * Muestra un documento con su contenido renderizado.
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
     * Descarga el PDF de un documento conservando su diseño.
     *
     * El PDF se arma al momento de la solicitud y no se almacena: se construye
     * con el contenido congelado del documento, así que siempre refleja la
     * versión de plantilla y los datos con los que se expidió.
     *
     * @param DocDocumento $documento
     * @return HttpResponse
     */
    public function pdf(DocDocumento $documento): HttpResponse
    {
        if ($documento->origen === DocDocumento::ORIGEN_SUBIDO) {
            return response()->json([
                'message' => 'Este documento es un archivo subido; descárguelo desde su enlace original.',
            ], 422);
        }

        return $this->pdf->generarPDF($documento)
            ->download($this->pdf->nombreArchivo($documento));
    }

    /**
     * Anula un documento indicando el motivo.
     *
     * La anulación conserva el documento y su contenido: solo lo marca como no
     * válido, porque el rastro de lo que se emitió no debe perderse.
     *
     * @param AnularDocDocumentoRequest $request
     * @param DocDocumento              $documento
     * @return JsonResponse
     */
    public function anular(AnularDocDocumentoRequest $request, DocDocumento $documento): JsonResponse
    {
        if ($documento->status === DocDocumento::STATUS_ANULADO) {
            return response()->json([
                'message' => 'El documento ya se encuentra anulado.',
            ], 422);
        }

        $documento = $this->generacion->anular($documento, $request->input('motivo'));

        return response()->json([
            'message' => 'Documento anulado exitosamente.',
            'data'    => new DocDocumentoResource($documento->load('tipoDocumento')),
        ]);
    }

    /**
     * Elimina (soft delete) un documento.
     *
     * @param DocDocumento $documento
     * @return JsonResponse
     */
    public function destroy(DocDocumento $documento): JsonResponse
    {
        $documento->delete();

        return response()->json([
            'message' => 'Documento eliminado exitosamente.',
        ]);
    }

    /**
     * Restaura un documento eliminado.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $documento = DocDocumento::onlyTrashed()->findOrFail($id);
        $documento->restore();

        return response()->json([
            'message' => 'Documento restaurado exitosamente.',
            'data'    => new DocDocumentoResource($documento->load('tipoDocumento')),
        ]);
    }

    /**
     * Elimina permanentemente un documento.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete(int $id): JsonResponse
    {
        $documento = DocDocumento::onlyTrashed()->findOrFail($id);
        $documento->forceDelete();

        return response()->json([
            'message' => 'Documento eliminado permanentemente.',
        ]);
    }

    /**
     * Lista los documentos eliminados (soft delete).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trashed(Request $request): JsonResponse
    {
        $filters                 = $request->only(['search', 'status', 'tipo_documento_id', 'origen']);
        $filters['only_trashed'] = true;

        $documentos = DocDocumento::withFilters($filters)
            ->with('tipoDocumento')
            ->select(self::COLUMNAS_LISTADO)
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
     * Opciones de estado y origen disponibles para los documentos.
     *
     * @return JsonResponse
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status_options' => DocDocumento::getActiveStatusOptions(),
                'origen_options' => [
                    DocDocumento::ORIGEN_GENERADO => 'Generado',
                    DocDocumento::ORIGEN_SUBIDO   => 'Subido',
                ],
            ],
        ]);
    }
}
