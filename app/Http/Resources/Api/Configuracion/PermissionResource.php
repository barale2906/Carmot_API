<?php

namespace App\Http\Resources\Api\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de transformación para el modelo Permission de Spatie.
 *
 * Expone los campos esenciales de un permiso: identificador, nombre,
 * descripción personalizada y guard al que pertenece.
 *
 * @mixin \Spatie\Permission\Models\Permission
 * @package App\Http\Resources\Api\Configuracion
 */
class PermissionResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'descripcion' => $this->descripcion,
            'guard_name'  => $this->guard_name,
            'created_at'  => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'  => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
