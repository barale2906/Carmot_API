<?php

namespace App\Mail;

use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Services\Financiero\ReciboPagoPDFService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable ReciboPagoMail
 *
 * Clase para enviar recibos de pago por correo electrónico.
 * Incluye el PDF del recibo como adjunto.
 *
 * @package App\Mail
 */
class ReciboPagoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * El recibo de pago a enviar.
     *
     * @var ReciboPago
     */
    public ReciboPago $reciboPago;

    /**
     * Instancia del servicio de PDF.
     *
     * @var ReciboPagoPDFService
     */
    protected ReciboPagoPDFService $pdfService;

    /**
     * Create a new message instance.
     *
     * @param ReciboPago $reciboPago Recibo de pago a enviar
     */
    public function __construct(ReciboPago $reciboPago)
    {
        $this->reciboPago = $reciboPago;
        $this->pdfService = new ReciboPagoPDFService();
    }

    /**
     * Get the message envelope.
     *
     * @return Envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recibo de Pago - ' . $this->reciboPago->numero_recibo,
        );
    }

    /**
     * Get the message content definition.
     *
     * @return Content
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.recibo-pago',
            with: [
                'recibo' => $this->reciboPago,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = $this->pdfService->generarPDF($this->reciboPago);

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                'Recibo_' . $this->reciboPago->numero_recibo . '.pdf'
            )->withMime('application/pdf'),
        ];
    }
}

