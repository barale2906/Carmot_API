<?php

namespace App\Http\Resources\Api\Financiero\Cartera;

use App\Traits\Financiero\HasCarteraStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso CarteraResource
 *
 * Da forma a la respuesta JSON de un registro de cartera.
 */
class CarteraResource extends JsonResource
{
    use HasCarteraStatus;

    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'matricula_id'     => $this->matricula_id,
            'sede_id'          => $this->sede_id,
            'estudiante_id'    => $this->estudiante_id,
            'numero_cuota'     => $this->numero_cuota,
            'valor'            => $this->valor,
            'saldo'            => $this->saldo,
            'abono'            => $this->abono,
            'descuento'        => $this->descuento,
            'fecha_vencimiento' => $this->fecha_vencimiento?->toDateString(),
            'status'           => $this->status,
            'status_text'      => self::getStatusText($this->status),
            'observaciones'    => $this->observaciones,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,

            // Relaciones opcionales
            'matricula'  => $this->whenLoaded('matricula', fn () => [
                'id'         => $this->matricula->id,
                'curso'      => $this->matricula->relationLoaded('curso')
                    ? $this->matricula->curso?->nombre
                    : null,
                'estudiante' => $this->matricula->relationLoaded('estudiante')
                    ? ($this->matricula->estudiante?->nombre_completo ?? null)
                    : null,
            ]),
            'sede'       => $this->whenLoaded('sede', fn () => [
                'id'     => $this->sede->id,
                'nombre' => $this->sede->nombre,
            ]),
            'estudiante' => $this->whenLoaded('estudiante', fn () => [
                'id'     => $this->estudiante->id,
                'nombre' => $this->estudiante->nombre_completo ?? $this->estudiante->name,
            ]),
        ];
    }
}
