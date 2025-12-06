<?php

namespace App\Http\Resources\Api\Financiero\Lp;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource LpTipoProductoResource
 *
 * Transforma el modelo LpTipoProducto en una respuesta JSON estructurada.
 * Incluye todos los campos del tipo de producto y su estado formateado.
 *
 * @package App\Http\Resources\Api\Financiero\Lp
 */
class LpTipoProductoResource extends JsonResource
{
    use HasActiveStatus;

    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request Solicitud HTTP actual
     * @return array<string, mixed> Array con los datos formateados del tipo de producto
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'es_financiable' => $this->es_financiable,
            'descripcion' => $this->descripcion,
            'status' => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'productos' => $this->whenLoaded('productos', function () {
                return LpProductoResource::collection($this->productos);
            }),

            // Contadores
            'productos_count' => $this->when(isset($this->productos_count), $this->productos_count),
        ];
    }
}
