<?php

namespace App\Http\Resources\Api\Activos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActActivoResource extends JsonResource
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
            'id'             => $this->id,
            'nombre'         => $this->nombre,
            'descripcion'    => $this->descripcion,
            'imagen'         => $this->imagen,
            'imagen_url'     => $this->imagen_url,
            'categoria_id'   => $this->categoria_id,
            'sede_id'        => $this->sede_id,
            'area_id'        => $this->area_id,
            'responsable_id' => $this->responsable_id,
            'marca'          => $this->marca,
            'modelo'         => $this->modelo,
            'serial'         => $this->serial,
            'fecha_ingreso'  => $this->fecha_ingreso?->format('Y-m-d'),
            'valor'          => (float) $this->valor,
            'estado'         => $this->estado,
            'estado_texto'   => $this->estado_texto,
            'observaciones'  => $this->observaciones,
            'created_at'     => $this->created_at?->format('Y-m-d H:i:s'),
            'deleted_at'     => $this->deleted_at?->format('Y-m-d H:i:s'),

            'categoria'   => $this->whenLoaded('categoria', fn () => ['id' => $this->categoria->id, 'nombre' => $this->categoria->nombre]),
            'sede'        => $this->whenLoaded('sede', fn () => ['id' => $this->sede->id, 'nombre' => $this->sede->nombre]),
            'area'        => $this->whenLoaded('area', fn () => $this->area ? ['id' => $this->area->id, 'nombre' => $this->area->nombre] : null),
            'responsable' => $this->whenLoaded('responsable', fn () => $this->responsable ? ['id' => $this->responsable->id, 'name' => $this->responsable->name] : null),
            'movimientos' => ActMovimientoResource::collection($this->whenLoaded('movimientos')),
            // Traslado enviado sin recibir: el activo sigue en el origen hasta la recepción.
            'traslado_pendiente' => $this->whenLoaded('trasladoPendiente', fn () => $this->trasladoPendiente ? new ActMovimientoResource($this->trasladoPendiente) : null),
        ];
    }
}
