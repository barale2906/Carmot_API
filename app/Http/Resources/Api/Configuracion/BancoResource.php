<?php

namespace App\Http\Resources\Api\Configuracion;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BancoResource extends JsonResource
{
    use HasActiveStatus;

    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'nombre'      => $this->nombre,
            'codigo'      => $this->codigo,
            'status'      => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'created_at'  => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'  => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at'  => $this->deleted_at?->format('Y-m-d H:i:s'),

            'medios_pago_count' => $this->when(isset($this->medios_pago_count), $this->medios_pago_count),
        ];
    }
}
