<?php

namespace App\Services\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\Academico\Matricula;
use Barryvdh\DomPDF\Facade\Pdf;
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

        return Pdf::loadView('pdf.documentos_matricula', [
            'matricula'  => $matricula,
            'codigo'     => $this->codigoMatricula($matricula),
            'documentos' => $documentos,
            'instituto'  => config('documentacion.instituto', []),
            'logoBase64' => $this->logoBase64(),
            'fotoBase64' => $this->fotoBase64($matricula),
        ])->setPaper('letter', 'portrait');
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
