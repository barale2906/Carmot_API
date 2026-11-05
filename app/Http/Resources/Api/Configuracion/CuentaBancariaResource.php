<?php

namespace App\Http\Resources\Api\Configuracion;

use App\Models\Configuracion\CuentaBancaria;
use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Forma de respuesta de una cuenta bancaria del instituto.
 */
class CuentaBancariaResource extends JsonResource
{
    use HasActiveStatus;

    /**
     * Transforma el recurso en un array. `alcance` resume dónde aplica la cuenta:
     * la ciudad completa y/o las sedes asociadas.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'poblacion_id' => $this->poblacion_id,
            'banco_id' => $this->banco_id,
            'tipo_cuenta' => $this->tipo_cuenta,
            'tipo_cuenta_text' => CuentaBancaria::TIPOS[$this->tipo_cuenta] ?? $this->tipo_cuenta,
            'numero_cuenta' => $this->numero_cuenta,
            'titular' => $this->titular,
            'descripcion' => $this->descripcion,
            'etiqueta' => $this->when($this->relationLoaded('banco'), fn () => $this->etiqueta),
            'status' => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            'sede_ids' => $this->whenLoaded('sedes', fn () => $this->sedes->pluck('id')->values()),
            'sedes' => $this->whenLoaded('sedes', fn () => $this->sedes->map->only(['id', 'nombre'])->values()),
            'poblacion' => $this->whenLoaded('poblacion', fn () => $this->poblacion?->only(['id', 'nombre'])),
            'alcance' => $this->when(
                $this->relationLoaded('sedes') && $this->relationLoaded('poblacion'),
                fn () => collect([
                    $this->poblacion ? 'Todas las sedes de '.$this->poblacion->nombre : null,
                    $this->sedes->isNotEmpty() ? $this->sedes->pluck('nombre')->implode(', ') : null,
                ])->filter()->implode(' + ')
            ),
            'banco' => $this->whenLoaded('banco', fn () => $this->banco?->only(['id', 'nombre', 'codigo'])),
            'medios_pago_count' => $this->when(isset($this->medios_pago_count), $this->medios_pago_count),
        ];
    }
}
