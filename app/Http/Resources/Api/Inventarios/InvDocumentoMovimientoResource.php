<?php

namespace App\Http\Resources\Api\Inventarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para la representación de un documento de movimiento de inventario.
 */
class InvDocumentoMovimientoResource extends JsonResource
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
            'id'                 => $this->id,
            'numero_documento'   => $this->numero_documento,
            'tipo_documento'     => $this->tipo_documento,
            'almacen_id'         => $this->almacen_id,
            'almacen_destino_id' => $this->almacen_destino_id,
            'proveedor_id'       => $this->proveedor_id,
            'pedido_id'          => $this->pedido_id,
            'motivo'             => $this->motivo,
            'status'             => $this->status,
            'motivo_anulacion'   => $this->motivo_anulacion,
            'user_id'            => $this->user_id,
            'created_at'         => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'         => $this->updated_at?->format('Y-m-d H:i:s'),

            'almacen' => $this->when(
                $this->relationLoaded('almacen'),
                fn () => $this->almacen ? [
                    'id'     => $this->almacen->id,
                    'nombre' => $this->almacen->nombre,
                ] : null
            ),

            'almacen_destino' => $this->when(
                $this->relationLoaded('almacenDestino'),
                fn () => $this->almacenDestino ? [
                    'id'     => $this->almacenDestino->id,
                    'nombre' => $this->almacenDestino->nombre,
                ] : null
            ),

            'proveedor' => $this->when(
                $this->relationLoaded('proveedor'),
                fn () => $this->proveedor ? [
                    'id'           => $this->proveedor->id,
                    'razon_social' => $this->proveedor->razon_social,
                ] : null
            ),

            'usuario' => $this->when(
                $this->relationLoaded('usuario'),
                fn () => $this->usuario ? [
                    'id'     => $this->usuario->id,
                    'nombre' => $this->usuario->name,
                ] : null
            ),

            'movimientos' => $this->when(
                $this->relationLoaded('movimientos'),
                fn () => InvMovimientoResource::collection($this->movimientos)
            ),

            'movimientos_count' => $this->when(isset($this->movimientos_count), $this->movimientos_count),
        ];
    }
}
