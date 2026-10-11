<?php

namespace App\Http\Resources\Api\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * Recurso de un turno de caja. El resumen es el congelado al cierre; en un turno
 * abierto el controlador lo asigna calculado en vivo, sin guardarlo.
 *
 * Conteo ciego: el efectivo esperado, la diferencia y los datos de arqueo de la
 * bitácora solo se incluyen para quien supervisa cajas y no es el cajero del turno
 * (LdTurnoCaja::arqueoVisiblePara).
 */
class LdTurnoCajaResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $verArqueo = $this->arqueoVisiblePara($request->user());

        return [
            'id' => $this->id,
            'sede_id' => $this->sede_id,
            'sede' => $this->whenLoaded('sede', fn () => ['id' => $this->sede->id, 'nombre' => $this->sede->nombre]),
            'cajero_id' => $this->cajero_id,
            'cajero' => $this->whenLoaded('cajero', fn () => ['id' => $this->cajero->id, 'name' => $this->cajero->name]),
            'fecha' => $this->fecha?->format('Y-m-d'),
            'base_inicial' => (float) $this->base_inicial,
            'abierto_at' => $this->abierto_at?->format('Y-m-d H:i:s'),
            'status' => $this->status,
            'status_text' => LdTurnoCaja::getStatusOptions()[$this->status] ?? null,
            'cerrado_at' => $this->cerrado_at?->format('Y-m-d H:i:s'),
            'cerrado_por' => $this->whenLoaded('cerradoPor', fn () => $this->cerradoPor?->only(['id', 'name'])),
            'efectivo_esperado' => $this->when($verArqueo, fn () => $this->efectivo_esperado !== null ? (float) $this->efectivo_esperado : null),
            'efectivo_contado' => $this->efectivo_contado !== null ? (float) $this->efectivo_contado : null,
            'diferencia' => $this->when($verArqueo, fn () => $this->diferencia !== null ? (float) $this->diferencia : null),
            // Alarma: el efectivo contado no coincide con el esperado
            'descuadre' => $this->when($verArqueo, fn () => $this->diferencia !== null && abs((float) $this->diferencia) >= 0.01),
            'observaciones_cierre' => $this->observaciones_cierre,
            'aprobado_por' => $this->whenLoaded('aprobadoPor', fn () => $this->aprobadoPor?->only(['id', 'name'])),
            'aprobado_at' => $this->aprobado_at?->format('Y-m-d H:i:s'),
            'observaciones_aprobacion' => $this->observaciones_aprobacion,
            'ajustado' => $this->ajustado,
            'resumen' => $verArqueo || $this->resumen === null ? $this->resumen : Arr::except($this->resumen, 'efectivo_esperado'),
            'autorizacion' => $this->whenLoaded('autorizacion', fn () => $this->autorizacion?->only(['id', 'motivo', 'autorizado_por_id'])),
            'eventos' => $this->whenLoaded('eventos', fn () => $this->eventos->map(fn ($e) => [
                'id' => $e->id,
                'accion' => $e->accion,
                'usuario' => $e->usuario?->name,
                'motivo' => $e->motivo,
                'datos' => $verArqueo ? $e->datos : null,
                'created_at' => $e->created_at?->format('Y-m-d H:i:s'),
            ])),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
