<?php

namespace App\Notifications\Financiero;

use App\Models\Financiero\Cartera\ProrrogaPago;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ProrrogaPagoVencidaNotification
 *
 * Se envía cuando llega la fecha compromiso de un plazo nuevo de pago y las cuotas que cubría siguen
 * con saldo. Avisa al estudiante (para que pague) y a la administración de la sede (para el control
 * y cumplimiento de los acuerdos).
 */
class ProrrogaPagoVencidaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ProrrogaPago $prorroga) {}

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
     * Texto del aviso: en primera persona para el estudiante, con su nombre para el personal.
     */
    private function mensaje(object $notifiable): string
    {
        $fecha = $this->prorroga->fecha_compromiso->format('d/m/Y');
        $saldo = number_format((float) $this->prorroga->saldo_vencido, 0, ',', '.');

        return $notifiable->id === $this->prorroga->estudiante_id
            ? "Su plazo nuevo de pago venció el {$fecha} y aún tiene cuotas vencidas por \${$saldo}. Pague para seguir asistiendo a clase."
            : "El plazo nuevo de pago de {$this->prorroga->estudiante->name} venció el {$fecha} sin que se pagaran sus cuotas vencidas (\${$saldo}). Haga el seguimiento del acuerdo.";
    }

    /**
     * Representación para correo electrónico.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Plazo de pago vencido')
            ->greeting("Hola {$notifiable->name},")
            ->line($this->mensaje($notifiable))
            ->salutation('Carmot — Sistema Financiero');
    }

    /**
     * Representación para base de datos (tabla notifications).
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo' => 'prorroga_pago_vencida',
            'prorroga_id' => $this->prorroga->id,
            'estudiante_id' => $this->prorroga->estudiante_id,
            'fecha_compromiso' => $this->prorroga->fecha_compromiso->format('Y-m-d'),
            'saldo_vencido' => (float) $this->prorroga->saldo_vencido,
            'mensaje' => $this->mensaje($notifiable),
        ];
    }
}
