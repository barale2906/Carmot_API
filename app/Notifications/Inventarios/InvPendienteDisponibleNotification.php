<?php

namespace App\Notifications\Inventarios;

use App\Models\Inventarios\InvNecesidadCompra;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * InvPendienteDisponibleNotification — notifica a los cajeros cuando llega stock
 * que cubre una entrega pendiente de un estudiante.
 *
 * Canal: database (badge en frontend).
 */
class InvPendienteDisponibleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly InvNecesidadCompra $necesidad)
    {
    }

    /**
     * Canal de envío: solo base de datos para el badge del frontend.
     *
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Contenido de la notificación para la tabla notifications.
     *
     * @param object $notifiable
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $producto   = $this->necesidad->producto?->nombre ?? "Producto #{$this->necesidad->producto_id}";
        $almacen    = $this->necesidad->almacen?->nombre  ?? "Almacén #{$this->necesidad->almacen_id}";
        $estudiante = $this->necesidad->estudiante?->name ?? "Estudiante #{$this->necesidad->estudiante_id}";

        return [
            'tipo'          => 'inv_pendiente_disponible',
            'necesidad_id'  => $this->necesidad->id,
            'producto_id'   => $this->necesidad->producto_id,
            'almacen_id'    => $this->necesidad->almacen_id,
            'estudiante_id' => $this->necesidad->estudiante_id,
            'mensaje'       => "Hay stock disponible de «{$producto}» en {$almacen} para entregar a {$estudiante}.",
        ];
    }
}
