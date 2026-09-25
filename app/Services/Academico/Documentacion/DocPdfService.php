<?php

namespace App\Services\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocTipoDocumento;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Servicio DocPdfService
 *
 * Envuelve el contenido ya armado de un documento en una plantilla Blade con el
 * encabezado y el pie institucionales, y lo convierte a PDF.
 *
 * El PDF se produce en el momento en que se solicita y no se almacena: el
 * contenido se vuelve a resolver en cada impresión, así que guardar el archivo
 * solo duplicaría información.
 *
 * @package App\Services\Academico\Documentacion
 */
class DocPdfService
{
    /**
     * Construye el PDF de un documento ya renderizado.
     *
     * @param DocTipoDocumento $tipoDocumento Tipo del documento, para el título.
     * @param string           $contenidoHtml Contenido con variables y bloques resueltos.
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generarPDF(DocTipoDocumento $tipoDocumento, string $contenidoHtml)
    {
        return Pdf::loadView('pdf.documentacion', [
            'tipoDocumento' => $tipoDocumento,
            'contenido'     => $contenidoHtml,
            'instituto'     => config('documentacion.instituto', []),
            'logoBase64'    => $this->logoBase64(),
        ])->setPaper('letter', 'portrait');
    }

    /**
     * Nombre de archivo sugerido para la descarga.
     *
     * Usa el código del tipo y el identificador del registro, que para los
     * documentos de matrícula es su número.
     *
     * @param DocTipoDocumento $tipoDocumento
     * @param int|null         $entidadId
     * @return string
     */
    public function nombreArchivo(DocTipoDocumento $tipoDocumento, ?int $entidadId): string
    {
        return $entidadId
            ? $tipoDocumento->codigo . '-' . $entidadId . '.pdf'
            : $tipoDocumento->codigo . '.pdf';
    }

    /**
     * Logo institucional embebido para el encabezado del PDF.
     *
     * @return string|null
     */
    private function logoBase64(): ?string
    {
        $ruta = public_path('images/logo.svg');

        if (!file_exists($ruta)) {
            return null;
        }

        return 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($ruta));
    }
}
