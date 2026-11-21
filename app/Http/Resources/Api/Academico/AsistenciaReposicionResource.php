<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AsistenciaReposicionResource extends JsonResource
{
    /**
     * Reposición con el estudiante y las dos clases (origen y destino) con su grupo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estudiante_id' => $this->estudiante_id,
            'clase_origen_id' => $this->clase_origen_id,
            'clase_destino_id' => $this->clase_destino_id,
            'motivo' => $this->motivo,
            'registrada_por_id' => $this->registrada_por_id,
            'estudiante' => $this->whenLoaded('estudiante', fn () => [
                'id' => $this->estudiante->id,
                'name' => $this->estudiante->name,
                'documento' => $this->estudiante->documento,
            ]),
            'clase_origen' => $this->whenLoaded('claseOrigen', fn () => $this->clase($this->claseOrigen)),
            'clase_destino' => $this->whenLoaded('claseDestino', fn () => $this->clase($this->claseDestino)),
            'registrada_por' => $this->whenLoaded('registradaPor', fn () => $this->registradaPor ? [
                'id' => $this->registradaPor->id,
                'name' => $this->registradaPor->name,
            ] : null),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  \App\Models\Academico\AsistenciaClaseProgramada  $clase
     * @return array<string, mixed>
     */
    private function clase($clase): array
    {
        return [
            'id' => $clase->id,
            'fecha_clase' => $clase->fecha_clase?->format('Y-m-d'),
            'hora_inicio' => $clase->hora_inicio,
            'hora_fin' => $clase->hora_fin,
            'estado' => $clase->estado,
            'grupo' => $clase->relationLoaded('grupo') && $clase->grupo
                ? ['id' => $clase->grupo->id, 'nombre' => $clase->grupo->nombre]
                : null,
        ];
    }
}
