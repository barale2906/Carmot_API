<?php

namespace App\Http\Resources\Api\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso de un tipo de movimiento del libro diario.
 */
class LdTipoMovimientoResource extends JsonResource
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
            'nombre' => $this->nombre,
            'clase' => $this->clase,
            'clase_text' => LdTipoMovimiento::getClaseOptions()[$this->clase] ?? null,
            'descripcion' => $this->descripcion,
            'status' => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }
}
