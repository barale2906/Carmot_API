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
            'name' => $this->name,
            'email' => $this->email,
            'documento' => $this->documento,
            /** @var array<string> */
            'roles' => $this->roles ? $this->roles->pluck('name')->toArray() : [],
            /** @var array<string> */
            'permissions' => $this->permissions ? $this->permissions->pluck('name')->toArray() : [],
            /** @var array<array<string, mixed>> */
            'grupos' => $this->when($this->relationLoaded('grupos'), function () {
                return $this->grupos->map(function ($grupo) {
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
                })->toArray();
            }, []),
            /** @var array<array<string, mixed>> */
            'cursos' => $this->when($this->relationLoaded('cursos'), function () {
                return $this->cursos->map(function ($curso) {
                    return [
                        'id' => $curso->id,
                        'nombre' => $curso->nombre,
                        'duracion' => $curso->duracion,
                        'status' => $curso->status,
                        'created_at' => $curso->created_at->toDateTimeString(),
                    ];
                })->toArray();
            }, []),
            /** @var array<array<string, mixed>> */
            'gestores' => $this->when($this->relationLoaded('gestores'), function () {
                return $this->gestores->map(function ($gestor) {
                    return [
                        'id' => $gestor->id,
                        'nombre' => $gestor->nombre,
                        'telefono' => $gestor->telefono,
                        'email' => $gestor->email,
                        'status' => $gestor->status,
                        'curso' => $gestor->curso ? [
                            'id' => $gestor->curso->id,
                            'nombre' => $gestor->curso->nombre,
                        ] : null,
                        'created_at' => $gestor->created_at->toDateTimeString(),
                    ];
                })->toArray();
            }, []),
            /** @var array<array<string, mixed>> */
            'agendadores' => $this->when($this->relationLoaded('agendadores'), function () {
                return $this->agendadores->map(function ($agenda) {
                    return [
                        'id' => $agenda->id,
                        'fecha' => is_string($agenda->fecha) ? $agenda->fecha : $agenda->fecha->toDateString(),
                        'hora' => $agenda->hora,
                        'jornada' => $agenda->jornada,
                        'status' => $agenda->status,
                        'referido' => $agenda->referido ? [
                            'id' => $agenda->referido->id,
                            'nombre' => $agenda->referido->nombre,
                        ] : null,
                        'created_at' => $agenda->created_at->toDateTimeString(),
                    ];
                })->toArray();
            }, []),
            /** @var array<array<string, mixed>> */
            'seguimientos' => $this->when($this->relationLoaded('seguimientos'), function () {
                return $this->seguimientos->map(function ($seguimiento) {
                    return [
                        'id' => $seguimiento->id,
                        'fecha' => is_string($seguimiento->fecha) ? $seguimiento->fecha : $seguimiento->fecha->toDateString(),
                        'seguimiento' => $seguimiento->seguimiento,
                        'referido' => $seguimiento->referido ? [
                            'id' => $seguimiento->referido->id,
                            'nombre' => $seguimiento->referido->nombre,
                        ] : null,
                        'created_at' => $seguimiento->created_at->toDateTimeString(),
                    ];
                })->toArray();
            }, []),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDateTimeString(),

            // Contadores
            'grupos_count' => $this->when(isset($this->grupos_count), $this->grupos_count),
            'cursos_count' => $this->when(isset($this->cursos_count), $this->cursos_count),
            'gestores_count' => $this->when(isset($this->gestores_count), $this->gestores_count),
            'agendadores_count' => $this->when(isset($this->agendadores_count), $this->agendadores_count),
            'seguimientos_count' => $this->when(isset($this->seguimientos_count), $this->seguimientos_count),
        ];
    }
}
