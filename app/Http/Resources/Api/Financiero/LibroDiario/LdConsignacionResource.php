<?php

namespace App\Http\Resources\Api\Financiero\LibroDiario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso de una consignación de efectivo del libro diario.
 */
class LdConsignacionResource extends JsonResource
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
            'turno_caja_id' => $this->turno_caja_id,
            'sede_id' => $this->sede_id,
            'sede' => $this->whenLoaded('sede', fn () => ['id' => $this->sede->id, 'nombre' => $this->sede->nombre]),
            'usuario' => $this->whenLoaded('usuario', fn () => ['id' => $this->usuario->id, 'name' => $this->usuario->name]),
            'fecha' => $this->fecha?->format('Y-m-d'),
            'banco_id' => $this->banco_id,
            'banco' => $this->whenLoaded('banco', fn () => $this->banco?->only(['id', 'nombre'])),
            'valor' => (float) $this->valor,
            'titular' => $this->titular,
            'numero_cuenta' => $this->numero_cuenta,
            'numero_comprobante' => $this->numero_comprobante,
            'observaciones' => $this->observaciones,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'anulado_por' => $this->whenLoaded('anuladoPor', fn () => $this->anuladoPor?->only(['id', 'name'])),
            'anulado_at' => $this->anulado_at?->format('Y-m-d H:i:s'),
            'motivo_anulacion' => $this->motivo_anulacion,
            'turno_abierto' => $this->whenLoaded('turnoCaja', fn () => $this->turnoCaja->estaAbierto()),
            'soportes' => LdSoporteResource::collection($this->whenLoaded('soportes')),
            'soportes_count' => $this->when(isset($this->soportes_count), fn () => $this->soportes_count),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
