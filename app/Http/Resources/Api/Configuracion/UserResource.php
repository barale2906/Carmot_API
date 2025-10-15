<?php

namespace App\Http\Resources\Api\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transforma el recurso en una matriz.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => is_array($this->name) ? $this->name['es'] ?? $this->name['en'] ?? reset($this->name) : $this->name,
            'email' => $this->email,
            'documento' => $this->documento,
            /** @var array<string> */
            'roles' => $this->roles ? $this->roles->pluck('name')->toArray() : [],
            /** @var array<string> */
            'permissions' => $this->permissions ? $this->permissions->pluck('name')->toArray() : [],
            /** @var array<array<string, mixed>> */
            'grupos' => $this->grupos ? $this->grupos->map(function ($grupo) {
                return [
                    'id' => $grupo->id,
                    'nombre' => $grupo->nombre,
                    'inscritos' => $grupo->inscritos,
                    'jornada' => $grupo->jornada,
                    'jornada_nombre' => $grupo->jornada_nombre,
                    'status' => $grupo->status,
                    'sede' => $grupo->sede ? [
                        'id' => $grupo->sede->id,
                        'nombre' => $grupo->sede->nombre,
                    ] : null,
                    'modulo' => $grupo->modulo ? [
                        'id' => $grupo->modulo->id,
                        'nombre' => $grupo->modulo->nombre,
                    ] : null,
                ];
            })->toArray() : [],
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDateTimeString(),

            // Contadores
            'grupos_count' => $this->when(isset($this->grupos_count), $this->grupos_count),
        ];
    }
}
