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
            'fecha' => $this->fecha?->format('Y-m-d'),
            'hora' => $this->hora,
            'jornada' => $this->jornada,
            'tipo' => $this->tipo,
            'tipo_text' => $this->tipo_text,
            'nota' => $this->nota,
            'status' => $this->status,
            'status_text' => $this->status_text, // según el tipo: Asistió, Contestó, Respondió...
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'referido' => $this->whenLoaded('referido', function () {
                return [
                    'id' => $this->referido->id,
                    'nombre' => $this->referido->nombre,
                    'celular' => $this->referido->celular,
                    'ciudad' => $this->referido->ciudad,
                    'status' => $this->referido->status,
                    'status_text' => $this->referido->status_text,
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
}
