<?php

namespace App\Http\Resources\Api\Inventarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para componentes individuales dentro de una entrega de kit.
 */
class InvEntregaKitComponenteResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'entrega_kit_id'       => $this->entrega_kit_id,
            'kit_componente_id'    => $this->kit_componente_id,
            // Datos que solo resuelve la pantalla de entregas pendientes.
            ...$this->datosDeEntregaPendiente(),
            'producto_entregado'   => $this->whenLoaded('productoEntregado', fn () => [
                'id'     => $this->productoEntregado?->id,
                'nombre' => $this->productoEntregado?->nombre,
                'codigo' => $this->productoEntregado?->codigo,
            ]),
            'cantidad_solicitada'  => $this->cantidad_solicitada,
            'cantidad_entregada'   => $this->cantidad_entregada,
            'cantidad_pendiente'   => max(0, $this->cantidad_solicitada - $this->cantidad_entregada),
            'status'               => $this->status,
            'fecha_entrega'        => $this->fecha_entrega,
            'usuario'              => $this->whenLoaded('usuario', fn () => [
                'id'     => $this->usuario?->id,
                'nombre' => $this->usuario?->name,
            ]),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }

    /**
     * Campos añadidos por InvEntregaPendienteService: nombre y tipo del componente,
     * stock en el almacén del pedido y variantes elegibles.
     *
     * Se publican solo cuando el servicio los adjuntó, para no alterar la respuesta
     * de los demás endpoints que usan este Resource.
     *
     * @return array<string, mixed>
     */
    private function datosDeEntregaPendiente(): array
    {
        if (! array_key_exists('componente_nombre', $this->resource->getAttributes())) {
            return [];
        }

        return [
            'componente_nombre' => $this->componente_nombre,
            'componente_tipo'   => $this->componente_tipo,
            'stock_disponible'  => $this->stock_disponible,
            'variantes'         => $this->variantes,
        ];
    }
}
