<?php

namespace App\Http\Resources\Api\Financiero\Descuento;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource DescuentoAplicadoResource
 *
 * Transforma el modelo DescuentoAplicado en una respuesta JSON estructurada.
 * Incluye todos los campos del registro de descuento aplicado y sus relaciones.
 *
 * @package App\Http\Resources\Api\Financiero\Descuento
 */
class DescuentoAplicadoResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request Solicitud HTTP actual
     * @return array<string, mixed> Array con los datos formateados del descuento aplicado
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'descuento_id' => $this->descuento_id,
            'descuento' => $this->whenLoaded('descuento', function () {
                return [
                    'id' => $this->descuento->id,
                    'nombre' => $this->descuento->nombre,
                    'codigo_descuento' => $this->descuento->codigo_descuento,
                    'tipo' => $this->descuento->tipo,
                    'valor' => (float) $this->descuento->valor,
                ];
            }),
            'concepto_tipo' => $this->concepto_tipo,
            'concepto_id' => $this->concepto_id,
            'valor_original' => (float) $this->valor_original,
            'valor_original_formatted' => number_format((float) $this->valor_original, 2, '.', ','),
            'valor_descuento' => (float) $this->valor_descuento,
            'valor_descuento_formatted' => number_format((float) $this->valor_descuento, 2, '.', ','),
            'valor_final' => (float) $this->valor_final,
            'valor_final_formatted' => number_format((float) $this->valor_final, 2, '.', ','),
            'producto_id' => $this->producto_id,
            'producto' => $this->whenLoaded('producto', function () {
                return [
                    'id' => $this->producto->id,
                    'nombre' => $this->producto->nombre,
                    'codigo' => $this->producto->codigo,
                ];
            }),
            'lista_precio_id' => $this->lista_precio_id,
            'lista_precio' => $this->whenLoaded('listaPrecio', function () {
                return [
                    'id' => $this->listaPrecio->id,
                    'nombre' => $this->listaPrecio->nombre,
                    'codigo' => $this->listaPrecio->codigo,
                ];
            }),
            'sede_id' => $this->sede_id,
            'sede' => $this->whenLoaded('sede', function () {
                return [
                    'id' => $this->sede->id,
                    'nombre' => $this->sede->nombre,
                ];
            }),
            'observaciones' => $this->observaciones,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

