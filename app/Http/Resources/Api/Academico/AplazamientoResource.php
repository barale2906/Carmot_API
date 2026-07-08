<?php

namespace App\Http\Resources\Api\Academico;

use App\Traits\HasAplazamientoEstado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AplazamientoResource extends JsonResource
{
    use HasAplazamientoEstado;

    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'ciclo_id'                => $this->ciclo_id,
            'tipo_aplazamiento_id'    => $this->tipo_aplazamiento_id,
            'user_id'                 => $this->user_id,
            'aplazamiento_padre_id'   => $this->aplazamiento_padre_id,

            'fecha_aplazamiento'      => $this->fecha_aplazamiento?->format('Y-m-d'),
            'fecha_inicio_original'   => $this->fecha_inicio_original?->format('Y-m-d'),
            'fecha_reinicio_probable'  => $this->fecha_reinicio_probable?->format('Y-m-d'),
            'dias_aplazamiento'       => $this->dias_aplazamiento,
            'fecha_reinicio_real'     => $this->fecha_reinicio_real?->format('Y-m-d'),
            'dias_reales'             => $this->dias_reales,

            'mover_cartera'           => $this->mover_cartera,
            'clases_movidas'          => $this->clases_movidas,
            'carteras_movidas'        => $this->carteras_movidas,

            'observaciones'           => $this->observaciones,
            'estado'                  => $this->estado,
            'estado_text'             => self::getEstadoText($this->estado),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'ciclo' => $this->whenLoaded('ciclo', fn () => [
                'id'     => $this->ciclo->id,
                'nombre' => $this->ciclo->nombre,
                'sede'   => $this->ciclo->sede?->nombre,
            ]),

            'tipo_aplazamiento' => $this->whenLoaded('tipoAplazamiento', fn () => [
                'id'     => $this->tipoAplazamiento->id,
                'nombre' => $this->tipoAplazamiento->nombre,
            ]),

            'user' => $this->whenLoaded('user', fn () => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ]),

            'padre' => $this->whenLoaded('padre', fn () => $this->padre ? [
                'id'                     => $this->padre->id,
                'fecha_reinicio_probable' => $this->padre->fecha_reinicio_probable?->format('Y-m-d'),
                'estado'                  => $this->padre->estado,
                'estado_text'             => self::getEstadoText($this->padre->estado),
            ] : null),

            'hijos_count' => $this->when(isset($this->hijos_count), (int) $this->hijos_count),
        ];
    }
}
