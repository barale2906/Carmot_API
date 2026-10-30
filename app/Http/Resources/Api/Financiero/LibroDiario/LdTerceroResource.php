<?php

namespace App\Http\Resources\Api\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdTercero;
use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso de un tercero del libro diario.
 */
class LdTerceroResource extends JsonResource
{
    use HasActiveStatus;

    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo_identificacion' => $this->tipo_identificacion,
            'tipo_identificacion_text' => LdTercero::TIPOS_IDENTIFICACION[$this->tipo_identificacion] ?? $this->tipo_identificacion,
            'identificacion' => $this->identificacion,
            'nombre' => $this->nombre,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'direccion' => $this->direccion,
            'status' => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'movimientos_count' => $this->when(isset($this->movimientos_count), fn () => $this->movimientos_count),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
