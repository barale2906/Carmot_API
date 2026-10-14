<?php

namespace App\Http\Resources\Api\Activos;

use App\Models\Activos\ActActivo;
use App\Models\Activos\ActMovimiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActMovimientoResource extends JsonResource
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
            'id'              => $this->id,
            'tipo'            => $this->tipo,
            'tipo_texto'      => ActMovimiento::TIPOS[$this->tipo] ?? $this->tipo,
            'fecha'           => $this->fecha?->format('Y-m-d'),
            'motivo'          => $this->motivo,
            'estado_anterior' => $this->estado_anterior ? (ActActivo::ESTADOS[$this->estado_anterior] ?? $this->estado_anterior) : null,
            'estado_nuevo'    => $this->estado_nuevo ? (ActActivo::ESTADOS[$this->estado_nuevo] ?? $this->estado_nuevo) : null,
            'origen'          => $this->sede_origen_id ? trim(($this->sedeOrigen?->nombre ?? '') . ($this->areaOrigen ? ' · ' . $this->areaOrigen->nombre : '')) : null,
            'destino'         => $this->sede_destino_id ? trim(($this->sedeDestino?->nombre ?? '') . ($this->areaDestino ? ' · ' . $this->areaDestino->nombre : '')) : null,
            'usuario'         => $this->usuario ? ['id' => $this->usuario->id, 'name' => $this->usuario->name] : null,
            'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
