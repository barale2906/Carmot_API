<?php

namespace App\Notifications\Financiero;

use App\Models\Financiero\ReciboPago\ReciboPago;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * TransferenciaPendienteNotification
 *
 * Se envía al personal con permiso fin_reciboPagoAprobar cuando el cajero
 * notifica que un recibo de pago por transferencia está listo para revisión.
 */
class TransferenciaPendienteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ReciboPago $recibo)
    {
    }

    /**
     * Canales de envío: base de datos y correo electrónico.
     *
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Representación para correo electrónico.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $cajero     = $this->recibo->cajero;
        $estudiante = $this->recibo->estudiante;
        $sede       = $this->recibo->sede;
        $valor      = number_format((float) $this->recibo->valor_total, 0, ',', '.');

        return (new MailMessage)
            ->subject("⏳ Recibo por transferencia pendiente de aprobación — Sede {$sede->nombre}")
            ->greeting("Hola {$notifiable->name},")
            ->line("El cajero **{$cajero->name}** ha generado un recibo de pago por transferencia que requiere su aprobación.")
            ->line("**Estudiante:** " . ($estudiante?->name ?? 'No especificado'))
            ->line("**Sede:** {$sede->nombre}")
            ->line("**Valor:** \${$valor}")
            ->line("**Fecha de generación:** " . $this->recibo->fecha_recibo?->format('d/m/Y'))
            ->line("Por favor ingrese al sistema para validar el comprobante y aprobar o rechazar el recibo.")
            ->salutation("Carmot — Sistema Financiero");
    }

    /**
     * Representación para base de datos (tabla notifications).
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo'           => 'transferencia_pendiente',
            'recibo_id'      => $this->recibo->id,
            'cajero_id'      => $this->recibo->cajero_id,
            'cajero_nombre'  => $this->recibo->cajero?->name,
            'estudiante_id'  => $this->recibo->estudiante_id,
            'sede_id'        => $this->recibo->sede_id,
            'valor_total'    => (float) $this->recibo->valor_total,
            'fecha_recibo'   => $this->recibo->fecha_recibo?->format('Y-m-d'),
            'mensaje'        => "Recibo por transferencia generado por {$this->recibo->cajero?->name} requiere aprobación.",
        ];
    }
}
