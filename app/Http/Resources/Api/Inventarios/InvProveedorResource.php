<?php

namespace App\Http\Resources\Api\Inventarios;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para la representación de un proveedor de inventario.
 */
class InvProveedorResource extends JsonResource
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
            'id'           => $this->id,
            'razon_social' => $this->razon_social,
            'nit'          => $this->nit,
            'contacto'     => $this->contacto,
            'telefono'     => $this->telefono,
            'email'        => $this->email,
            'direccion'    => $this->direccion,
            'notas'        => $this->notas,
            'status'       => $this->status,
            'status_text'  => self::getActiveStatusText($this->status),
            'created_at'   => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'   => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at'   => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }
}
