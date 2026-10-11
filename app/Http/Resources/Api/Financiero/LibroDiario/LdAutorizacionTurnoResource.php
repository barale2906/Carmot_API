<?php

namespace App\Http\Resources\Api\Financiero\LibroDiario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso de una autorización para abrir un nuevo turno de caja en el día.
 */
class LdAutorizacionTurnoResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cajero_id' => $this->cajero_id,
            'cajero' => $this->whenLoaded('cajero', fn () => ['id' => $this->cajero->id, 'name' => $this->cajero->name]),
            'fecha' => $this->fecha?->format('Y-m-d'),
            'autorizado_por' => $this->whenLoaded('autorizadoPor', fn () => ['id' => $this->autorizadoPor->id, 'name' => $this->autorizadoPor->name]),
            'motivo' => $this->motivo,
            'turno_caja_id' => $this->turno_caja_id,
            'usada' => $this->turno_caja_id !== null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
