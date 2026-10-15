<?php

namespace App\Services\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\Academico\Matricula;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
     * @param Model|null       $entidad       Registro del documento: su sede da dirección y teléfono del encabezado.
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generarPDF(DocTipoDocumento $tipoDocumento, string $contenidoHtml, ?Model $entidad = null)
    {
        return $this->numerarPaginas(Pdf::loadView('pdf.documentacion', [
            'tipoDocumento' => $tipoDocumento,
            'contenido'     => $contenidoHtml,
            'instituto'     => app(DatosInstitutoService::class)->paraEntidad($entidad),
            'logoBase64'    => $this->logoBase64(),
        ])->setPaper('letter', 'portrait'));
    }

    /**
     * Construye en un solo PDF la hoja de matrícula y los documentos que la conforman.
     *
     * La hoja va primero; cada documento empieza en una página nueva para que se
     * pueda firmar y escanear por separado.
     *
     * @param Matricula                         $matricula
     * @param array<int, array<string, mixed>>  $documentos Documentos ya armados (ver DocGeneracionService::generarDocumentosMatricula).
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generarPdfMatricula(Matricula $matricula, array $documentos)
    {
        $matricula->loadMissing(['estudiante', 'curso', 'ciclo.sede', 'sede', 'eps', 'lugarOrigen', 'matriculadoPor']);

        return $this->numerarPaginas(Pdf::loadView('pdf.documentos_matricula', [
            'matricula'  => $matricula,
            'codigo'     => $this->codigoMatricula($matricula),
            'documentos' => $documentos,
            'instituto'  => app(DatosInstitutoService::class)->paraEntidad($matricula),
            'logoBase64' => $this->logoBase64(),
            'fotoBase64' => $this->fotoBase64($matricula),
        ])->setPaper('letter', 'portrait'));
    }

    /**
     * Nombre de archivo sugerido para el paquete de documentos de una matrícula.
     *
     * @param Matricula $matricula
     * @return string
     */
    public function nombreArchivoMatricula(Matricula $matricula): string
    {
        return 'DOCUMENTOS-' . $this->codigoMatricula($matricula) . '.pdf';
    }

    /**
     * Código visible de la matrícula, el mismo que muestra la hoja del frontend.
     *
     * @param Matricula $matricula
     * @return string Por ejemplo MAT-2026-045.
     */
    private function codigoMatricula(Matricula $matricula): string
    {
        $anio = ($matricula->fecha_matricula ?? now())->format('Y');

        return 'MAT-' . $anio . '-' . str_pad((string) $matricula->id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Foto del estudiante embebida, porque dompdf no carga imágenes remotas.
     *
     * @param Matricula $matricula
     * @return string|null
     */
    private function fotoBase64(Matricula $matricula): ?string
    {
        $disco = Storage::disk('public');

        if (!$matricula->foto || !$disco->exists($matricula->foto)) {
            return null;
        }

        return 'data:' . $disco->mimeType($matricula->foto) . ';base64,' . base64_encode($disco->get($matricula->foto));
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
     * Logo institucional embebido para el encabezado del PDF. Se usa la versión PNG
     * para fondo claro: dompdf no dibuja bien el SVG (degradados y letras blancas),
     * que salía como una "C" (B18).
     *
     * @return string|null
     */
    private function logoBase64(): ?string
    {
        $ruta = public_path('images/logo-pdf.png');

        if (!file_exists($ruta)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($ruta));
    }

    /**
     * Escribe "página / total" en el pie de cada página. dompdf no soporta
     * counter(pages) en CSS (salía "1 / 0", B18); el total solo se conoce tras
     * renderizar, así que se dibuja sobre el lienzo con {PAGE_COUNT}.
     *
     * @param \Barryvdh\DomPDF\PDF $pdf
     * @return \Barryvdh\DomPDF\PDF
     */
    private function numerarPaginas($pdf)
    {
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $fuente = $dompdf->getFontMetrics()->getFont('DejaVu Sans');

        // Unidades en puntos (carta = 612 × 792). Margen derecho 50px = 37,5pt; la
        // línea del pie queda a ~52pt del borde inferior.
        $canvas->page_text($canvas->get_width() - 37.5 - 30, $canvas->get_height() - 52, '{PAGE_NUM} / {PAGE_COUNT}', $fuente, 7, [0.47, 0.47, 0.47]);

        return $pdf;
    }
}
