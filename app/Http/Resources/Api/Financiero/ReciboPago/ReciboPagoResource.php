<?php

namespace App\Http\Resources\Api\Financiero\ReciboPago;

use App\Http\Resources\Api\Academico\MatriculaResource;
use App\Http\Resources\Api\Configuracion\SedeResource;
use App\Http\Resources\Api\Financiero\ConceptoPago\ConceptoPagoResource;
use App\Http\Resources\Api\Financiero\Descuento\DescuentoResource;
use App\Http\Resources\Api\Financiero\Lp\LpListaPrecioResource;
use App\Http\Resources\Api\Financiero\Lp\LpProductoResource;
use App\Http\Resources\Api\UserResource;
use App\Models\Financiero\ReciboPago\ReciboPago;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource ReciboPagoResource
 *
 * Transforma el modelo ReciboPago en una respuesta JSON estructurada.
 * Incluye todos los campos del recibo de pago, relaciones y datos formateados.
 *
 * @package App\Http\Resources\Api\Financiero\ReciboPago
 */
class ReciboPagoResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request Solicitud HTTP actual
     * @return array<string, mixed> Array con los datos formateados del recibo de pago
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_recibo' => $this->numero_recibo,
            'consecutivo' => $this->consecutivo,
            'prefijo' => $this->prefijo,
            'origen' => $this->origen,
            'origen_text' => $this->origen_text,
            'fecha_recibo' => $this->fecha_recibo?->format('Y-m-d'),
            'fecha_transaccion' => $this->fecha_transaccion?->format('Y-m-d H:i:s'),
            'valor_total' => (float) $this->valor_total,
            'valor_total_formatted' => number_format((float) $this->valor_total, 2, '.', ','),
            'descuento_total' => (float) $this->descuento_total,
            'descuento_total_formatted' => number_format((float) $this->descuento_total, 2, '.', ','),
            'banco' => $this->banco,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'cierre' => $this->cierre,
            'sede_id' => $this->sede_id,
            'estudiante_id' => $this->estudiante_id,
            'cajero_id' => $this->cajero_id,
            'matricula_id' => $this->matricula_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones opcionales
            'sede' => $this->whenLoaded('sede', fn () => new SedeResource($this->sede)),
            'estudiante' => $this->whenLoaded('estudiante', fn () => new UserResource($this->estudiante)),
            'cajero' => $this->whenLoaded('cajero', fn () => new UserResource($this->cajero)),
            'matricula' => $this->whenLoaded('matricula', fn () => new MatriculaResource($this->matricula)),

            // Conceptos de pago con detalles del pivot
            'conceptos_pago' => $this->whenLoaded('conceptosPago', function () {
                return $this->conceptosPago->map(function ($concepto) {
                    return [
                        'id' => $concepto->id,
                        'nombre' => $concepto->nombre,
                        'tipo' => $concepto->tipo,
                        'tipo_nombre' => $concepto->getNombreTipo(),
                        'valor' => $concepto->pivot->valor,
                        'producto' => $concepto->pivot->producto,
                        'cantidad' => $concepto->pivot->cantidad,
                        'unitario' => (float) $concepto->pivot->unitario,
                        'subtotal' => (float) $concepto->pivot->subtotal,
                        'id_relacional' => $concepto->pivot->id_relacional,
                        'observaciones' => $concepto->pivot->observaciones,
                    ];
                });
            }),

            // Listas de precio
            'listas_precio' => $this->whenLoaded('listasPrecio', fn () => LpListaPrecioResource::collection($this->listasPrecio)),

            // Productos con detalles del pivot
            'productos' => $this->whenLoaded('productos', function () {
                return $this->productos->map(function ($producto) {
                    return [
                        'id' => $producto->id,
                        'nombre' => $producto->nombre,
                        'codigo' => $producto->codigo,
                        'cantidad' => $producto->pivot->cantidad,
                        'precio_unitario' => (float) $producto->pivot->precio_unitario,
                        'subtotal' => (float) $producto->pivot->subtotal,
                    ];
                });
            }),

            // Descuentos con detalles del pivot
            'descuentos' => $this->whenLoaded('descuentos', function () {
                return $this->descuentos->map(function ($descuento) {
                    return [
                        'id' => $descuento->id,
                        'nombre' => $descuento->nombre,
                        'codigo_descuento' => $descuento->codigo_descuento,
                        'tipo' => $descuento->tipo,
                        'valor_descuento' => (float) $descuento->pivot->valor_descuento,
                        'valor_original' => (float) $descuento->pivot->valor_original,
                        'valor_final' => (float) $descuento->pivot->valor_final,
                    ];
                });
            }),

            // Medios de pago
            'medios_pago' => $this->whenLoaded('mediosPago', function () {
                return $this->mediosPago->map(function ($medio) {
                    return [
                        'id' => $medio->id,
                        'medio_pago' => $medio->medio_pago,
                        'valor' => (float) $medio->valor,
                        'valor_formatted' => number_format((float) $medio->valor, 2, '.', ','),
                        'referencia' => $medio->referencia,
                        'banco' => $medio->banco,
                    ];
                });
            }),

            // Métodos de verificación
            'esta_anulado' => $this->estaAnulado(),
            'esta_cerrado' => $this->estaCerrado(),
            'esta_en_proceso' => $this->estaEnProceso(),
        ];
    }
}

