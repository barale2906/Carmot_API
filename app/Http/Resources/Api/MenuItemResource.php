<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        return [
            'id'         => $item['id'],
            'title'      => $item['title'],
            'icon'       => $item['icon'],
            'route'      => $item['route'] ?? null,
            'permission' => $item['permission'] ?? null,
            'disabled'   => $item['disabled'] ?? false,
            'children'   => MenuItemResource::collection($item['children'] ?? []),
        ];
    }
}
