<?php

namespace App\Http\Resources\Api\Activos;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActCategoriaResource extends JsonResource
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
            'id'            => $this->id,
            'nombre'        => $this->nombre,
            'descripcion'   => $this->descripcion,
            'status'        => $this->status,
            'status_text'   => self::getActiveStatusText($this->status),
            'activos_count' => $this->when(isset($this->activos_count), $this->activos_count),
            'created_at'    => $this->created_at?->format('Y-m-d H:i:s'),
            'deleted_at'    => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }
}
