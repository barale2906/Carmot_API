<?php

namespace App\Http\Resources\Api\Financiero\ConceptoPago;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource ConceptoPagoResource
 *
 * Transforma el modelo ConceptoPago en una respuesta JSON estructurada.
 * Incluye todos los campos del concepto de pago y su información formateada.
 *
 * @package App\Http\Resources\Api\Financiero\ConceptoPago
 */
class ConceptoPagoResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request Solicitud HTTP actual
     * @return array<string, mixed> Array con los datos formateados del concepto de pago
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo, // Índice numérico
            'tipo_nombre' => $this->getNombreTipo(), // Nombre del tipo según el índice
            'valor' => (float) $this->valor,
            'valor_formatted' => number_format((float) $this->valor, 2, '.', ','),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }
}

