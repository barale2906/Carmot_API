<?php

namespace App\Http\Resources\Api\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgendaResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'referido_id' => $this->referido_id,
            'agendador_id' => $this->agendador_id,
            'fecha' => $this->fecha,
            'hora' => $this->hora,
            'jornada' => $this->jornada,
            'status' => $this->status,
            'status_text' => $this->getStatusText(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,

            // Relaciones cargadas
            'referido' => $this->whenLoaded('referido', function () {
                return [
                    'id' => $this->referido->id,
                    'nombre' => $this->referido->nombre,
                    'apellido' => $this->referido->apellido,
                    'telefono' => $this->referido->telefono,
                    'email' => $this->referido->email,
                ];
            }),

            'agendador' => $this->whenLoaded('agendador', function () {
                return [
                    'id' => $this->agendador->id,
                    'name' => $this->agendador->name,
                    'email' => $this->agendador->email,
                ];
            }),
        ];
    }

    /**
     * Obtiene el texto descriptivo del status.
     *
     * @return string
     */
    private function getStatusText(): string
    {
        return match ($this->status) {
            0 => 'Agendado',
            1 => 'Asistió',
            2 => 'No asistió',
            3 => 'Reprogramó',
            4 => 'Canceló',
            default => 'Desconocido',
        };
    }
}
