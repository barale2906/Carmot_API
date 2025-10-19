<?php

namespace App\Http\Resources\Api\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource DashboardResource
 *
 * Transforma el modelo Dashboard para la respuesta de la API.
 * Incluye información del dashboard y sus tarjetas.
 */
class DashboardResource extends JsonResource
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
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'user' => new UserResource($this->whenLoaded('user')),
            'dashboard_cards' => DashboardCardResource::collection($this->whenLoaded('dashboardCards')),
            'dashboard_cards_count' => $this->when($this->relationLoaded('dashboardCards'), $this->dashboardCards->count()),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
