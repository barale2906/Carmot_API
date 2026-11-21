<?php

namespace App\Http\Resources\Api\Financiero\Cartera;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProrrogaPagoResource extends JsonResource
{
    /**
     * Prórroga de pago con su estudiante, sede y quien la registró.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estudiante_id' => $this->estudiante_id,
            'sede_id' => $this->sede_id,
            'cartera_ids' => $this->cartera_ids,
            'saldo_vencido' => (float) $this->saldo_vencido,
            'fecha_acuerdo' => $this->fecha_acuerdo?->format('Y-m-d'),
            'fecha_compromiso' => $this->fecha_compromiso?->format('Y-m-d'),
            'estado' => $this->estado,
            'observacion' => $this->observacion,
            'registrada_por_id' => $this->registrada_por_id,
            'estudiante' => $this->whenLoaded('estudiante', fn () => [
                'id' => $this->estudiante->id,
                'name' => $this->estudiante->name,
                'documento' => $this->estudiante->documento,
            ]),
            'sede' => $this->whenLoaded('sede', fn () => $this->sede ? [
                'id' => $this->sede->id,
                'nombre' => $this->sede->nombre,
            ] : null),
            'registrada_por' => $this->whenLoaded('registradaPor', fn () => $this->registradaPor ? [
                'id' => $this->registradaPor->id,
                'name' => $this->registradaPor->name,
            ] : null),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
