<?php

namespace App\Notifications\Financiero;

use App\Models\Financiero\ReciboPago\ReciboPago;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * TransferenciaRechazadaNotification
 *
 * Se envía al cajero que generó el recibo cuando el validador lo rechaza.
 * Incluye el motivo del rechazo para que el cajero pueda corregir o eliminar.
 */
class TransferenciaRechazadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ReciboPago $recibo,
        public readonly string $motivo,
    ) {
    }

    /**
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
        $validador  = $this->recibo->aprobadoPor;
        $estudiante = $this->recibo->estudiante;
        $valor      = number_format((float) $this->recibo->valor_total, 0, ',', '.');

        return (new MailMessage)
            ->subject("❌ Recibo por transferencia rechazado")
            ->greeting("Hola {$notifiable->name},")
            ->line("El recibo de pago por transferencia que generó fue **rechazado** por el validador.")
            ->line("**Validador:** " . ($validador?->name ?? 'Sistema'))
            ->line("**Estudiante:** " . ($estudiante?->name ?? 'No especificado'))
            ->line("**Valor:** \${$valor}")
            ->line("**Motivo del rechazo:**")
            ->line("> {$this->motivo}")
            ->line("Por favor ingrese al sistema para corregir los datos del recibo (número de transacción o comprobante) o eliminarlo si no es posible corregirlo.")
            ->salutation("Carmot — Sistema Financiero");
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo'            => 'transferencia_rechazada',
            'recibo_id'       => $this->recibo->id,
            'validador_id'    => $this->recibo->aprobado_por_id,
            'validador_nombre'=> $this->recibo->aprobadoPor?->name,
            'estudiante_id'   => $this->recibo->estudiante_id,
            'valor_total'     => (float) $this->recibo->valor_total,
            'motivo_rechazo'  => $this->motivo,
            'mensaje'         => "Su recibo por transferencia fue rechazado. Motivo: {$this->motivo}",
        ];
    }
}
