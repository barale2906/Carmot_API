<?php

namespace App\Http\Resources\Api\Financiero\Lp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource LpPrecioProductoResource
 *
 * Transforma el modelo LpPrecioProducto en una respuesta JSON estructurada.
 * Incluye todos los campos del precio de producto y sus relaciones.
 *
 * @package App\Http\Resources\Api\Financiero\Lp
 */
class LpPrecioProductoResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request Solicitud HTTP actual
     * @return array<string, mixed> Array con los datos formateados del precio de producto
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lista_precio_id' => $this->lista_precio_id,
            'producto_id' => $this->producto_id,
            'precio_contado' => $this->precio_contado ? (float) $this->precio_contado : 0.00,
            'precio_total' => $this->precio_total ? (float) $this->precio_total : null,
            'matricula' => $this->matricula ? (float) $this->matricula : 0.00,
            'numero_cuotas' => $this->numero_cuotas,
            'valor_cuota' => $this->valor_cuota ? (float) $this->valor_cuota : null,
            'observaciones' => $this->observaciones,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'lista_precio' => $this->whenLoaded('listaPrecio', function () {
                return new LpListaPrecioResource($this->listaPrecio);
            }),

            'producto' => $this->whenLoaded('producto', function () {
                return new LpProductoResource($this->producto);
            }),

            // Incluir tipo de producto cuando esté cargado a través de producto
            'producto_tipo' => $this->when(
                $this->relationLoaded('producto') && $this->producto->relationLoaded('tipoProducto'),
                function () {
                    return new LpTipoProductoResource($this->producto->tipoProducto);
                }
            ),
        ];
    }
}
