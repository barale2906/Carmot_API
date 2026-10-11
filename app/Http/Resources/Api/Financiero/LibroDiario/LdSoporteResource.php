<?php

namespace App\Http\Resources\Api\Financiero\LibroDiario;

use App\Models\Financiero\LibroDiario\LdSoporte;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso de un archivo de soporte del libro diario. No expone la ruta en disco:
 * el archivo se descarga por la URL protegida.
 */
class LdSoporteResource extends JsonResource
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
            'tipo' => $this->tipo,
            'tipo_text' => LdSoporte::TIPOS[$this->tipo] ?? $this->tipo,
            'nombre_original' => $this->nombre_original,
            'mime_type' => $this->mime_type,
            'tamano_bytes' => $this->tamano_bytes,
            'url_archivo' => route('libro-diario.soportes.archivo', $this->id),
            'usuario' => $this->whenLoaded('usuario', fn () => ['id' => $this->usuario->id, 'name' => $this->usuario->name]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
