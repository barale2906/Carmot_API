<?php

namespace App\Http\Resources\Api\Configuracion;

use App\Models\Configuracion\DiaNoLaborable;
use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiaNoLaborableResource extends JsonResource
{
    use HasActiveStatus;

    /**
     * Transforma el recurso en un array.
     * sede_id NULL indica que el día aplica a todas las sedes (todas_las_sedes = true).
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'fecha'           => $this->fecha?->format('Y-m-d'),
            'nombre'          => $this->nombre,
            'tipo'            => $this->tipo,
            'tipo_text'       => DiaNoLaborable::TIPOS[$this->tipo] ?? $this->tipo,
            'sede_id'         => $this->sede_id,
            'todas_las_sedes' => $this->sede_id === null,
            'sede'            => $this->whenLoaded('sede', fn () => $this->sede ? [
                'id'     => $this->sede->id,
                'nombre' => $this->sede->nombre,
            ] : null),
            'observaciones'   => $this->observaciones,
            'status'          => $this->status,
            'status_text'     => self::getActiveStatusText($this->status),
            'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'      => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at'      => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }
}
