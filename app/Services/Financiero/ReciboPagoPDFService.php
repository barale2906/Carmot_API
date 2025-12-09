<?php

namespace App\Services\Financiero;

use App\Models\Financiero\ReciboPago\ReciboPago;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio ReciboPagoPDFService
 *
 * Gestiona la generación de PDFs para los recibos de pago.
 * Utiliza DomPDF (barryvdh/laravel-dompdf) para generar documentos PDF a partir de vistas Blade.
 *
 * NOTA: Requiere instalar la librería: composer require barryvdh/laravel-dompdf
 *
 * @package App\Services\Financiero
 */
class ReciboPagoPDFService
{
    /**
     * Genera el PDF del recibo de pago.
     *
     * @param ReciboPago $reciboPago Recibo de pago para generar PDF
     * @return mixed Instancia del PDF generado (depende de la librería utilizada)
     * @throws \Exception Si la librería de PDF no está instalada
     */
    public function generarPDF(ReciboPago $reciboPago)
    {
        // Verificar que la librería esté disponible
        if (!class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
            throw new \Exception(
                'La librería de PDF (barryvdh/laravel-dompdf) no está instalada. ' .
                'Ejecute: composer require barryvdh/laravel-dompdf'
            );
        }

        // Cargar todas las relaciones necesarias
        $reciboPago->load([
            'sede.poblacion',
            'estudiante',
            'cajero',
            'matricula',
            'conceptosPago',
            'listasPrecio',
            'productos',
            'descuentos',
            'mediosPago'
        ]);

        // Generar PDF desde la vista usando DomPDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('recibos-pago.pdf', [
            'recibo' => $reciboPago,
        ]);

        // Configurar opciones del PDF
        $pdf->setPaper('letter', 'portrait');
        $pdf->setOption('margin-top', 20);
        $pdf->setOption('margin-bottom', 20);
        $pdf->setOption('margin-left', 15);
        $pdf->setOption('margin-right', 15);

        return $pdf;
    }

    /**
     * Genera el PDF y lo guarda en storage.
     *
     * @param ReciboPago $reciboPago Recibo de pago para generar PDF
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
     * @param ReciboPago $reciboPago Recibo de pago
     * @return string|null URL del PDF o null si no existe
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

