<?php

namespace App\Http\Resources\Api\Inventarios;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para pedidos de inventario.
 */
class InvPedidoResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'estudiante'      => $this->whenLoaded('estudiante', fn () => [
                'id'     => $this->estudiante->id,
                'nombre' => $this->estudiante->name,
                'email'  => $this->estudiante->email,
            ]),
            'sede'            => $this->whenLoaded('sede', fn () => [
                'id'     => $this->sede->id,
                'nombre' => $this->sede->nombre,
            ]),
            'almacen'         => $this->whenLoaded('almacen', fn () => [
                'id'     => $this->almacen->id,
                'nombre' => $this->almacen->nombre,
            ]),
            'cajero'          => $this->whenLoaded('cajero', fn () => [
                'id'     => $this->cajero->id,
                'nombre' => $this->cajero->name,
            ]),
            'valor_total'     => $this->valor_total,
            'abono_acumulado' => $this->abono_acumulado,
            'saldo'           => $this->saldo,
            'status'          => $this->status,
            'observaciones'   => $this->observaciones,
            'items'           => InvPedidoItemResource::collection($this->whenLoaded('items')),
            'recibo_links'    => $this->whenLoaded('reciboLinks', function () {
                return $this->reciboLinks->map(fn ($link) => [
                    'recibo_pago_id'  => $link->recibo_pago_id,
                    'monto_abonado'   => $link->monto_abonado,
                    'created_at'      => $link->created_at,
                ]);
            }),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
