<?php

namespace App\Services\Financiero;

use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\ReciboPago\ReciboPago;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio ReciboPagoPDFService
 *
 * Genera PDFs para recibos de pago usando DomPDF (barryvdh/laravel-dompdf).
 * Enriquece cada línea de cartera con el estado actual de la cuota, de modo
 * que el PDF generado sea idéntico en datos al modal de impresión del frontend.
 *
 * @package App\Services\Financiero
 */
class ReciboPagoPDFService
{
    /**
     * Genera el PDF del recibo de pago.
     *
     * @param ReciboPago $reciboPago
     * @return mixed Instancia del PDF generado
     * @throws \Exception Si DomPDF no está instalado
     */
    public function generarPDF(ReciboPago $reciboPago)
    {
        if (!class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
            throw new \Exception(
                'La librería de PDF (barryvdh/laravel-dompdf) no está instalada. ' .
                'Ejecute: composer require barryvdh/laravel-dompdf'
            );
        }

        $reciboPago->load([
            'sede',
            'estudiante',
            'cajero',
            'matricula.curso',
            'conceptosPago',
            'mediosPago',
        ]);

        // Cargar estado de cartera para líneas con id_relacional (mismo enriquecimiento que el frontend)
        $idRelacionales = $reciboPago->conceptosPago
            ->pluck('pivot.id_relacional')
            ->filter()
            ->unique()
            ->values();

        $carteras = collect();
        if ($idRelacionales->isNotEmpty()) {
            $carteras = Cartera::whereIn('id', $idRelacionales)
                ->get()
                ->keyBy('id');
        }

        $logoBase64 = null;
        $logoPath = public_path('images/logo.svg');
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($logoPath));
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('recibos-pago.pdf', [
            'recibo'      => $reciboPago,
            'carteras'    => $carteras,
            'logoBase64'  => $logoBase64,
        ]);

        $pdf->setPaper('letter', 'portrait');
        $pdf->setOption('margin-top', 0);
        $pdf->setOption('margin-bottom', 0);
        $pdf->setOption('margin-left', 0);
        $pdf->setOption('margin-right', 0);

        return $pdf;
    }

    /**
     * Genera el PDF y lo guarda en storage.
     *
     * @param ReciboPago $reciboPago
     * @return string Ruta del archivo guardado
     */
    public function generarYGuardarPDF(ReciboPago $reciboPago): string
    {
        $pdf = $this->generarPDF($reciboPago);

        $nombreArchivo = 'recibos-pago/' . $reciboPago->numero_recibo . '.pdf';
        Storage::disk('public')->put($nombreArchivo, $pdf->output());

        return $nombreArchivo;
    }

    /**
     * Obtiene la URL pública del PDF si existe.
     *
     * @param ReciboPago $reciboPago
     * @return string|null
     */
    public function obtenerURLPDF(ReciboPago $reciboPago): ?string
    {
        $nombreArchivo = 'recibos-pago/' . $reciboPago->numero_recibo . '.pdf';

        if (Storage::disk('public')->exists($nombreArchivo)) {
            return Storage::disk('public')->url($nombreArchivo);
        }

        return null;
    }
}
