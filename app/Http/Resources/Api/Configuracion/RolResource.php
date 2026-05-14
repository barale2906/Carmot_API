<?php

namespace App\Http\Resources\Api\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de transformación para el modelo Role de Spatie.
 *
 * Serializa un rol con sus permisos asociados y los contadores de
 * permisos y usuarios. Los contadores solo se incluyen cuando han
 * sido calculados explícitamente (no se generan N+1 queries).
 *
 * @mixin \Spatie\Permission\Models\Role
 * @package App\Http\Resources\Api\Configuracion
 */
class RolResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'guard_name' => $this->guard_name,
            'status'     => $this->status,

            'permissions' => PermissionResource::collection(
                $this->whenLoaded('permissions')
            ),

            // Contadores (solo presentes cuando se cargan con loadCount)
            'permissions_count' => $this->when(isset($this->permissions_count), $this->permissions_count),
            'users_count'       => $this->when(isset($this->users_count), $this->users_count),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
