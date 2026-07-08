<?php

namespace App\Notifications\Academico;

use App\Models\Academico\Aplazamiento;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * AplazamientoVencidoNotification
 *
 * Se envía cuando la fecha_reinicio_probable de un aplazamiento llega sin que
 * el usuario haya confirmado, ampliado, interrumpido ni revertido el aplazamiento.
 * Requiere que el usuario tome acción para cerrar el ciclo de vida del aplazamiento.
 */
class AplazamientoVencidoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Aplazamiento $aplazamiento)
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
        $ciclo             = $this->aplazamiento->ciclo;
        $tipo              = $this->aplazamiento->tipoAplazamiento?->nombre ?? 'Sin tipo';
        $fechaProbable     = $this->aplazamiento->fecha_reinicio_probable->format('d/m/Y');
        $fechaOriginal     = $this->aplazamiento->fecha_inicio_original->format('d/m/Y');

        return (new MailMessage)
            ->subject("⚠️ Reinicio pendiente de confirmación — Ciclo {$ciclo->nombre}")
            ->greeting("Hola {$notifiable->name},")
            ->line("El ciclo **{$ciclo->nombre}** tenía previsto reiniciar el **{$fechaProbable}** "
                . "(aplazado desde {$fechaOriginal} por: {$tipo}).")
            ->line("La fecha probable de reinicio ha llegado y el aplazamiento aún está **Pendiente**.")
            ->line("Por favor, indique qué sucedió:")
            ->line("• **Confirmar**: el ciclo reinició en la fecha prevista.")
            ->line("• **Ampliar**: el ciclo necesita más tiempo y se aplazará a una nueva fecha.")
            ->line("• **Interrumpir**: el ciclo reinició antes de la fecha prevista.")
            ->line("• **Revertir**: el aplazamiento fue un error y se desea restaurar las fechas originales.")
            ->salutation("Carmot — Sistema Académico");
    }

    /**
     * Representación para base de datos (tabla notifications).
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $ciclo         = $this->aplazamiento->ciclo;
        $fechaProbable = $this->aplazamiento->fecha_reinicio_probable->format('d/m/Y');

        return [
            'tipo'                    => 'aplazamiento_vencido',
            'aplazamiento_id'         => $this->aplazamiento->id,
            'ciclo_id'                => $ciclo->id,
            'ciclo_nombre'            => $ciclo->nombre,
            'fecha_reinicio_probable'  => $fechaProbable,
            'dias_aplazamiento'       => $this->aplazamiento->dias_aplazamiento,
            'mensaje'                 => "El ciclo '{$ciclo->nombre}' tenía previsto reiniciar el {$fechaProbable}. Confirme, amplíe, interrumpa o revierta el aplazamiento.",
        ];
    }
}
