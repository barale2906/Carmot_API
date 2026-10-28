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
            'estado_traslado' => $this->estado_traslado,
            'estado_traslado_texto' => $this->estado_traslado ? (ActMovimiento::ESTADOS_TRASLADO[$this->estado_traslado] ?? $this->estado_traslado) : null,
            'fecha'           => $this->fecha?->format('Y-m-d'),
            'motivo'          => $this->motivo,
            'estado_anterior' => $this->estado_anterior ? (ActActivo::ESTADOS[$this->estado_anterior] ?? $this->estado_anterior) : null,
            'estado_nuevo'    => $this->estado_nuevo ? (ActActivo::ESTADOS[$this->estado_nuevo] ?? $this->estado_nuevo) : null,
            'origen'          => $this->sede_origen_id ? trim(($this->sedeOrigen?->nombre ?? '') . ($this->areaOrigen ? ' · ' . $this->areaOrigen->nombre : '')) : null,
            'destino'         => $this->sede_destino_id ? trim(($this->sedeDestino?->nombre ?? '') . ($this->areaDestino ? ' · ' . $this->areaDestino->nombre : '')) : null,
            'sede_origen_id'  => $this->sede_origen_id,
            'sede_destino_id' => $this->sede_destino_id,
            'area_destino_id' => $this->area_destino_id,
            'usuario'         => $this->usuario ? ['id' => $this->usuario->id, 'name' => $this->usuario->name] : null,
            'responsable_destino' => $this->responsable_destino_id ? ['id' => $this->responsable_destino_id, 'name' => $this->responsableDestino?->name] : null,

            // Recepción o cancelación del traslado
            'fecha_recepcion'         => $this->fecha_recepcion?->format('Y-m-d'),
            'observaciones_recepcion' => $this->observaciones_recepcion,
            'recibido_por'            => $this->recibido_por_id ? ['id' => $this->recibido_por_id, 'name' => $this->recibidoPor?->name] : null,
            'motivo_cancelacion'      => $this->motivo_cancelacion,
            'cancelado_por'           => $this->cancelado_por_id ? ['id' => $this->cancelado_por_id, 'name' => $this->canceladoPor?->name] : null,

            'activo'          => $this->whenLoaded('activo', fn () => [
                'id'         => $this->activo->id,
                'nombre'     => $this->activo->nombre,
                'serial'     => $this->activo->serial,
                'imagen_url' => $this->activo->imagen_url,
                'categoria'  => $this->activo->relationLoaded('categoria') ? $this->activo->categoria?->nombre : null,
            ]),
            'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
