<?php

namespace App\Http\Resources\Api\Financiero\Lp;

use App\Traits\Financiero\HasListaPrecioStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource LpListaPrecioResource
 *
 * Transforma el modelo LpListaPrecio en una respuesta JSON estructurada.
 * Incluye todos los campos de la lista de precios, sus relaciones y el estado formateado.
 *
 * @package App\Http\Resources\Api\Financiero\Lp
 */
class LpListaPrecioResource extends JsonResource
{
    use HasListaPrecioStatus;

    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request Solicitud HTTP actual
     * @return array<string, mixed> Array con los datos formateados de la lista de precios
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'fecha_inicio' => $this->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $this->fecha_fin?->format('Y-m-d'),
            'descripcion' => $this->descripcion,
            'status' => $this->status,
            'status_text' => self::getStatusText($this->status),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'poblaciones' => $this->whenLoaded('poblaciones', function () {
                return $this->poblaciones->map(function ($poblacion) {
                    return [
                        'id' => $poblacion->id,
                        'nombre' => $poblacion->nombre,
                        'pais' => $poblacion->pais,
                        'provincia' => $poblacion->provincia,
                    ];
                });
            }),

            'precios_productos' => $this->whenLoaded('preciosProductos', function () {
                return LpPrecioProductoResource::collection($this->preciosProductos);
            }),

            'productos' => $this->whenLoaded('productos', function () {
                return LpProductoResource::collection($this->productos);
            }),

            // Contadores
            'poblaciones_count' => $this->when(isset($this->poblaciones_count), $this->poblaciones_count),
            'precios_productos_count' => $this->when(isset($this->precios_productos_count), $this->precios_productos_count),
            'productos_count' => $this->when(isset($this->productos_count), $this->productos_count),
        ];
    }
}
