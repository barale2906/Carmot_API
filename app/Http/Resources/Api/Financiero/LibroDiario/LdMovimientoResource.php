<?php

namespace App\Http\Resources\Api\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdMovimiento;
use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use App\Models\Financiero\ReciboPago\ReciboPagoMedioPago;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso de un movimiento manual del libro diario.
 */
class LdMovimientoResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'turno_caja_id' => $this->turno_caja_id,
            'sede_id' => $this->sede_id,
            'sede' => $this->whenLoaded('sede', fn () => ['id' => $this->sede->id, 'nombre' => $this->sede->nombre]),
            'usuario' => $this->whenLoaded('usuario', fn () => ['id' => $this->usuario->id, 'name' => $this->usuario->name]),
            'tipo_movimiento_id' => $this->tipo_movimiento_id,
            'tipo_movimiento' => new LdTipoMovimientoResource($this->whenLoaded('tipoMovimiento')),
            'clase' => $this->clase,
            'clase_text' => LdTipoMovimiento::getClaseOptions()[$this->clase] ?? null,
            'fecha' => $this->fecha?->format('Y-m-d'),
            'tercero_tipo_identificacion' => $this->tercero_tipo_identificacion,
            'tercero_identificacion' => $this->tercero_identificacion,
            'tercero_nombre' => $this->tercero_nombre,
            'documento_tipo' => $this->documento_tipo,
            'documento_tipo_text' => LdMovimiento::TIPOS_DOCUMENTO[$this->documento_tipo] ?? $this->documento_tipo,
            'documento_numero' => $this->documento_numero,
            'subtotal' => (float) $this->subtotal,
            'total_impuestos' => (float) $this->total_impuestos,
            'total_retenciones' => (float) $this->total_retenciones,
            'valor_total' => (float) $this->valor_total,
            'impuestos' => $this->whenLoaded('impuestos', fn () => $this->impuestos->map(fn ($i) => [
                'impuesto_id' => $i->impuesto_id,
                'nombre' => $i->nombre,
                'efecto' => $i->efecto,
                'porcentaje' => (float) $i->porcentaje,
                'valor' => (float) $i->valor,
            ])),
            'medio_pago' => $this->medio_pago,
            'medio_pago_text' => ReciboPagoMedioPago::MEDIOS[$this->medio_pago] ?? $this->medio_pago,
            'banco_id' => $this->banco_id,
            'banco' => $this->whenLoaded('banco', fn () => $this->banco?->only(['id', 'nombre'])),
            'observaciones' => $this->observaciones,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'anulado_por' => $this->whenLoaded('anuladoPor', fn () => $this->anuladoPor?->only(['id', 'name'])),
            'anulado_at' => $this->anulado_at?->format('Y-m-d H:i:s'),
            'motivo_anulacion' => $this->motivo_anulacion,
            'turno_abierto' => $this->whenLoaded('turnoCaja', fn () => $this->turnoCaja->estaAbierto()),
            'soportes' => LdSoporteResource::collection($this->whenLoaded('soportes')),
            'soportes_count' => $this->when(isset($this->soportes_count), fn () => $this->soportes_count),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
