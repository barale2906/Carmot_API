<?php

namespace App\Http\Resources\Api\Financiero\Lp;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource LpProductoResource
 *
 * Transforma el modelo LpProducto en una respuesta JSON estructurada.
 * Incluye todos los campos del producto, sus relaciones y el estado formateado.
 *
 * @package App\Http\Resources\Api\Financiero\Lp
 */
class LpProductoResource extends JsonResource
{
    use HasActiveStatus;

    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request Solicitud HTTP actual
     * @return array<string, mixed> Array con los datos formateados del producto
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo_producto_id' => $this->tipo_producto_id,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'referencia_id' => $this->referencia_id,
            'referencia_tipo' => $this->referencia_tipo,
            'status' => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'tipo_producto' => $this->whenLoaded('tipoProducto', function () {
                return new LpTipoProductoResource($this->tipoProducto);
            }),

            'referencia' => $this->whenLoaded('referencia', function () {
                if (!$this->referencia) {
                    return null;
                }

                // Retornar datos básicos según el tipo de referencia
                return [
                    'id' => $this->referencia->id,
                    'nombre' => $this->referencia->nombre ?? $this->referencia->name ?? null,
                    'tipo' => $this->referencia_tipo,
                ];
            }),

            'precios' => $this->whenLoaded('precios', function () {
                return LpPrecioProductoResource::collection($this->precios);
            }),

            'listas_precios' => $this->whenLoaded('listasPrecios', function () {
                return LpListaPrecioResource::collection($this->listasPrecios);
            }),

            // Contadores
            'precios_count' => $this->when(isset($this->precios_count), $this->precios_count),
            'listas_precios_count' => $this->when(isset($this->listas_precios_count), $this->listas_precios_count),
        ];
    }
}
