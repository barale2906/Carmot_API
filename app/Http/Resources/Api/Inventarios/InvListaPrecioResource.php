<?php

namespace App\Http\Resources\Api\Inventarios;

use App\Traits\Financiero\HasListaPrecioStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para listas de precios del módulo de inventarios (origen=0).
 */
class InvListaPrecioResource extends JsonResource
{
    use HasListaPrecioStatus;

    /**
     * Transforma la lista de precios de inventario en un array para la respuesta JSON.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'nombre'       => $this->nombre,
            'fecha_inicio' => $this->fecha_inicio?->format('Y-m-d'),
            'fecha_fin'    => $this->fecha_fin?->format('Y-m-d'),
            'descripcion'  => $this->descripcion,
            'status'       => $this->status,
            'status_text'  => self::getStatusText($this->status),
            'esta_vigente' => $this->estaVigente(),

            'poblaciones' => $this->whenLoaded('poblaciones', fn () =>
                $this->poblaciones->map(fn ($p) => [
                    'id'     => $p->id,
                    'nombre' => $p->nombre,
                ])
            ),

            'precios' => $this->whenLoaded('preciosInventario', fn () =>
                InvPrecioProductoResource::collection($this->preciosInventario)
            ),

            'precios_count' => $this->when(
                isset($this->precios_inventario_count),
                fn () => $this->precios_inventario_count
            ),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
