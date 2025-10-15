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
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name')->toArray();
            }, []),
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->pluck('name')->toArray();
            }, []),
            'grupos' => $this->whenLoaded('grupos', function () {
                return $this->grupos->map(function ($grupo) {
                    return [
                        'id' => $grupo->id,
                        'nombre' => $grupo->nombre,
                        'inscritos' => $grupo->inscritos,
                        'jornada' => $grupo->jornada,
                        'jornada_nombre' => $grupo->jornada_nombre,
                        'status' => $grupo->status,
                        'sede' => $grupo->whenLoaded('sede', function () use ($grupo) {
                            return [
                                'id' => $grupo->sede->id,
                                'nombre' => $grupo->sede->nombre,
                            ];
                        }),
                        'modulo' => $grupo->whenLoaded('modulo', function () use ($grupo) {
                            return [
                                'id' => $grupo->modulo->id,
                                'nombre' => $grupo->modulo->nombre,
                            ];
                        }),
                    ];
                })->toArray();
            }, []),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDateTimeString(),

            // Contadores
            'grupos_count' => $this->when(isset($this->grupos_count), $this->grupos_count),
        ];
    }
}
