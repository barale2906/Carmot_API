<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => MenuItemResource::collection($this->resource),
            'message' => 'Menú obtenido exitosamente',
        ];
    }
}
