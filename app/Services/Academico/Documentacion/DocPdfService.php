<?php

namespace App\Services\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocDocumento;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Servicio DocPdfService
 *
 * Convierte a PDF el contenido ya renderizado de un documento, envolviéndolo en
 * una plantilla Blade con el encabezado y el pie institucionales.
 *
 * El PDF se arma en el momento en que se solicita y no se almacena: el archivo
 * sería una copia redundante del contenido que el documento ya tiene guardado.
 * Ese contenido fue resuelto y escapado al expedirlo, así que cada descarga
 * reproduce exactamente el mismo documento.
 *
 * @package App\Services\Academico\Documentacion
 */
class DocPdfService
{
    /**
     * Construye el PDF de un documento.
     *
     * @param DocDocumento $documento
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generarPDF(DocDocumento $documento)
    {
        $documento->loadMissing('tipoDocumento');

        return Pdf::loadView('pdf.documentacion', [
            'documento'  => $documento,
            'instituto'  => config('documentacion.instituto', []),
            'logoBase64' => $this->logoBase64(),
        ])->setPaper('letter', 'portrait');
    }

    /**
     * Nombre de archivo sugerido para la descarga.
     *
     * @param DocDocumento $documento
     * @return string
     */
    public function nombreArchivo(DocDocumento $documento): string
    {
        return $documento->numero_documento . '.pdf';
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
