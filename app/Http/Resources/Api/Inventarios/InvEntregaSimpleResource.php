<?php

namespace App\Http\Resources\Api\Inventarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para entregas simples (productos de tipo simple/variante/servicio).
 */
class InvEntregaSimpleResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'pedido_item_id'      => $this->pedido_item_id,
            'producto_id'         => $this->producto_id,
            'cantidad_entregada'  => $this->cantidad_entregada,
            // Datos que solo resuelve la pantalla de entregas pendientes.
            ...$this->datosDeEntregaPendiente(),
            'status'              => $this->status,
            'fecha_entrega'       => $this->fecha_entrega,
            'usuario'             => $this->whenLoaded('usuario', fn () => [
                'id'     => $this->usuario->id,
                'nombre' => $this->usuario->name,
            ]),
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];
    }

    /**
     * Campos añadidos por InvEntregaPendienteService: cantidad aún por entregar y
     * stock del producto en el almacén del pedido.
     *
     * @return array<string, mixed>
     */
    private function datosDeEntregaPendiente(): array
    {
        if (! array_key_exists('cantidad_pendiente', $this->resource->getAttributes())) {
            return [];
        }

        return [
            'cantidad_pendiente' => $this->cantidad_pendiente,
            'stock_disponible'   => $this->stock_disponible,
        ];
    }
}
