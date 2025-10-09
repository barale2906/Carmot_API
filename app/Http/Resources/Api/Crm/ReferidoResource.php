<?php

namespace App\Http\Resources\Api\Crm;

use App\Traits\HasStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferidoResource extends JsonResource
{
    use HasStatus;
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed> Array con los datos del referido
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'celular' => $this->celular,
            'ciudad' => $this->ciudad,
            'status' => $this->status,
            'status_text' => self::getStatusText($this->status),
            'curso' => $this->whenLoaded('curso', function () {
                return [
                    'id' => $this->curso->id,
                    'nombre' => $this->curso->nombre,
                ];
            }),
            'gestor' => $this->whenLoaded('gestor', function () {
                return [
                    'id' => $this->gestor->id,
                    'name' => $this->gestor->name,
                    'email' => $this->gestor->email,
                ];
            }),
            'seguimientos' => $this->whenLoaded('seguimientos', function () {
                return $this->seguimientos->map(function ($seguimiento) {
                    return [
                        'id' => $seguimiento->id,
                        'fecha' => $seguimiento->fecha,
                        'seguimiento' => $seguimiento->seguimiento,
                        'created_at' => $seguimiento->created_at,
                    ];
                });
            }),
            'agendamientos' => $this->whenLoaded('agendamientos', function () {
                return $this->agendamientos->map(function ($agendamiento) {
                    return [
                        'id' => $agendamiento->id,
                        'fecha' => $agendamiento->fecha,
                        'hora' => $agendamiento->hora,
                        'jornada' => $agendamiento->jornada,
                        'status' => $agendamiento->status,
                        'status_text' => \App\Models\Crm\Agenda::getStatusText($agendamiento->status),
                        'created_at' => $agendamiento->created_at,
                    ];
                });
            }),
            'seguimientos_count' => $this->when(isset($this->seguimientos_count), $this->seguimientos_count),
            'agendamientos_count' => $this->when(isset($this->agendamientos_count), $this->agendamientos_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at),
            'is_deleted' => $this->trashed(),
            'is_matriculado' => $this->isMatriculado(),
            'can_be_deleted' => $this->canBeDeleted(),
            'days_since_created' => $this->getDaysSinceCreated(),
            'next_suggested_status' => $this->getNextSuggestedStatus(),
            'next_suggested_status_text' => $this->getNextSuggestedStatusText(),
        ];
    }

}
