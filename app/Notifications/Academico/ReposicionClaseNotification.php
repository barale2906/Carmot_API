<?php

namespace App\Notifications\Academico;

use App\Models\Academico\AsistenciaReposicion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * ReposicionClaseNotification
 *
 * Avisa al instituto (personal de la sede y profesor del grupo de destino) que un estudiante
 * tomará una clase fuera de su grupo, para que quede registrado y lo esperen en la planilla.
 */
class ReposicionClaseNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly AsistenciaReposicion $reposicion) {}

    /**
     * Canales de envío: base de datos.
     *
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Representación para base de datos (tabla notifications).
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $origen = $this->reposicion->claseOrigen;
        $destino = $this->reposicion->claseDestino;
        $nombre = $this->reposicion->estudiante->name;

        return [
            'tipo' => 'reposicion_clase',
            'reposicion_id' => $this->reposicion->id,
            'estudiante_id' => $this->reposicion->estudiante_id,
            'clase_origen_id' => $origen->id,
            'clase_destino_id' => $destino->id,
            'mensaje' => "{$nombre} tomará la clase del {$origen->fecha_clase->format('d/m/Y')} (grupo {$origen->grupo->nombre}) "
                ."el {$destino->fecha_clase->format('d/m/Y')} en el grupo {$destino->grupo->nombre}.",
        ];
    }
}
