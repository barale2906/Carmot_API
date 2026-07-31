<?php

namespace App\Notifications\Financiero;

use App\Models\Financiero\ReciboPago\ReciboPago;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * TransferenciaAprobadaNotification
 *
 * Confirmación interna (solo base de datos) al cajero cuando su recibo
 * por transferencia es aprobado y el número de recibo es asignado.
 */
class TransferenciaAprobadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ReciboPago $recibo)
    {
    }

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo'             => 'transferencia_aprobada',
            'recibo_id'        => $this->recibo->id,
            'numero_recibo'    => $this->recibo->numero_recibo,
            'validador_id'     => $this->recibo->aprobado_por_id,
            'validador_nombre' => $this->recibo->aprobadoPor?->name,
            'estudiante_id'    => $this->recibo->estudiante_id,
            'valor_total'      => (float) $this->recibo->valor_total,
            'fecha_aprobacion' => $this->recibo->fecha_aprobacion?->format('Y-m-d H:i:s'),
            'mensaje'          => "Recibo {$this->recibo->numero_recibo} aprobado y enviado al estudiante.",
        ];
    }
}
