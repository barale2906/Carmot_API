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
                'id'        => $this->estudiante->id,
                'nombre'    => $this->estudiante->name,
                'email'     => $this->estudiante->email,
                'documento' => $this->estudiante->numero_documento ?? $this->estudiante->documento ?? null,
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
            // Transferencias registradas que aún no cuentan en abono_acumulado/saldo
            'abono_pendiente_aprobacion' => $this->when(
                array_key_exists('abono_pendiente_aprobacion', $this->getAttributes()),
                fn () => (float) $this->abono_pendiente_aprobacion
            ),
            'status'          => $this->status,
            'observaciones'   => $this->observaciones,
            'motivo_cancelacion' => $this->motivo_cancelacion,
            'fecha_cancelacion'  => $this->fecha_cancelacion,
            'cancelado_por'      => $this->whenLoaded('canceladoPor', fn () => $this->canceladoPor ? [
                'id'     => $this->canceladoPor->id,
                'nombre' => $this->canceladoPor->name,
            ] : null),
            'items'           => InvPedidoItemResource::collection($this->whenLoaded('items')),
            'recibo_links'    => $this->whenLoaded('reciboLinks', function () {
                return $this->reciboLinks->map(fn ($link) => [
                    'recibo_pago_id'  => $link->recibo_pago_id,
                    'numero_recibo'   => $link->reciboPago?->numero_recibo,
                    'status'          => $link->reciboPago?->status,
                    'fecha_recibo'    => $link->reciboPago?->fecha_recibo,
                    'valor_total'     => $link->reciboPago?->valor_total,
                    'monto_abonado'   => $link->monto_abonado,
                    'medios_pago'     => $link->reciboPago?->relationLoaded('mediosPago')
                        ? $link->reciboPago->mediosPago->map(fn ($mp) => [
                            'medio_pago' => $mp->medio_pago,
                            'valor'      => $mp->valor,
                            'referencia' => $mp->referencia,
                        ])
                        : null,
                    'created_at'      => $link->created_at,
                ]);
            }),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
