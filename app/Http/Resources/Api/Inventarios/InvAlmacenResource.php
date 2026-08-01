<?php

namespace App\Http\Resources\Api\Inventarios;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para la representación de un almacén.
 */
class InvAlmacenResource extends JsonResource
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
            'descripcion' => $this->descripcion,
            'sede_id'     => $this->sede_id,
            'status'      => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'created_at'  => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'  => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at'  => $this->deleted_at?->format('Y-m-d H:i:s'),

            'sede' => $this->when(
                $this->relationLoaded('sede'),
                fn () => $this->sede ? [
                    'id'     => $this->sede->id,
                    'nombre' => $this->sede->nombre,
                ] : null
            ),

            'usuarios_count' => $this->when(isset($this->usuarios_count), $this->usuarios_count),

            'usuarios' => $this->when(
                $this->relationLoaded('usuarios'),
                fn () => $this->usuarios->map(fn ($u) => [
                    'id'     => $u->id,
                    'nombre' => $u->name,
                    'email'  => $u->email,
                ])
            ),
        ];
    }
}
