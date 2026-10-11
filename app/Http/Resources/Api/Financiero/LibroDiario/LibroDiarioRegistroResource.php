<?php

namespace App\Http\Resources\Api\Financiero\LibroDiario;

use App\Services\Financiero\LibroDiario\LibroDiarioService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso de una fila del libro diario unificado (recibo, movimiento o consignación).
 */
class LibroDiarioRegistroResource extends JsonResource
{
    /**
     * Transforma la fila en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'origen' => $this->origen,
            'origen_text' => LibroDiarioService::getOrigenOptions()[$this->origen] ?? $this->origen,
            'registro_id' => (int) $this->registro_id,
            'sede_id' => (int) $this->sede_id,
            'sede' => $this->sede,
            'turno_caja_id' => $this->turno_caja_id !== null ? (int) $this->turno_caja_id : null,
            'usuario_id' => (int) $this->usuario_id,
            'usuario' => $this->usuario,
            'fecha' => substr((string) $this->fecha, 0, 10),
            'naturaleza' => $this->naturaleza,
            'naturaleza_text' => LibroDiarioService::getNaturalezaOptions()[$this->naturaleza] ?? $this->naturaleza,
            'concepto' => $this->concepto,
            'tercero' => $this->tercero,
            'documento' => $this->documento,
            'valor' => (float) $this->valor,
            'creado_at' => (string) $this->creado_at,
        ];
    }
}
