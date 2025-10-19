<?php

namespace App\Http\Resources\Api\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource UserResource
 *
 * Transforma el modelo User para la respuesta de la API en contexto de dashboards.
 * Versión simplificada para evitar información sensible.
 */
class UserResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
