<?php

namespace App\Http\Resources\Api\Academico;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatriculaResource extends JsonResource
{
    use HasActiveStatus;

    /**
     * Obtiene las opciones de estado para matrículas.
     * Sobrescribe el método del trait para incluir el estado "Anulado".
     *
     * @return array<string, string> Array con los estados disponibles
     */
    public static function getActiveStatusOptions(): array
    {
        return [
            0 => 'Inactivo',
            1 => 'Activo',
            2 => 'Anulado',
        ];
    }

    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha_matricula' => $this->fecha_matricula?->format('Y-m-d'),
            'fecha_inicio' => $this->fecha_inicio?->format('Y-m-d'),
            'monto' => (float) $this->monto,
            'observaciones' => $this->observaciones,
            'status' => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'anulada' => $this->anulada,
            'activa' => $this->activa,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'curso' => $this->whenLoaded('curso', [
                'id' => $this->curso->id,
                'nombre' => $this->curso->nombre,
                'duracion' => $this->curso->duracion,
                'status' => $this->curso->status,
                'status_text' => self::getActiveStatusText($this->curso->status),
            ]),

            'ciclo' => $this->whenLoaded('ciclo', [
                'id' => $this->ciclo->id,
                'nombre' => $this->ciclo->nombre,
                'descripcion' => $this->ciclo->descripcion,
                'fecha_inicio' => $this->ciclo->fecha_inicio?->format('Y-m-d'),
                'fecha_fin' => $this->ciclo->fecha_fin?->format('Y-m-d'),
                'status' => $this->ciclo->status,
                'status_text' => self::getActiveStatusText($this->ciclo->status),
            ]),

            'estudiante' => $this->whenLoaded('estudiante', [
                'id' => $this->estudiante->id,
                'name' => $this->estudiante->name,
                'email' => $this->estudiante->email,
                'documento' => $this->estudiante->documento,
            ]),

            'matriculado_por' => $this->whenLoaded('matriculadoPor', [
                'id' => $this->matriculadoPor->id,
                'name' => $this->matriculadoPor->name,
                'email' => $this->matriculadoPor->email,
            ]),

            'comercial' => $this->whenLoaded('comercial', [
                'id' => $this->comercial->id,
                'name' => $this->comercial->name,
                'email' => $this->comercial->email,
            ]),
        ];
    }
}
