<?php

namespace App\Http\Resources\Api\Academico;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotaEstudianteResource extends JsonResource
{
    use HasActiveStatus;

    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estudiante_id' => $this->estudiante_id,
            'grupo_id' => $this->grupo_id,
            'modulo_id' => $this->modulo_id,
            'esquema_calificacion_id' => $this->esquema_calificacion_id,
            'tipo_nota_esquema_id' => $this->tipo_nota_esquema_id,
            'nota' => (float) $this->nota,
            'nota_ponderada' => (float) $this->nota_ponderada,
            'fecha_registro' => $this->fecha_registro?->format('Y-m-d'),
            'registrado_por_id' => $this->registrado_por_id,
            'observaciones' => $this->observaciones,
            'status' => $this->status,
            'status_text' => $this->getStatusText(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'estudiante' => $this->whenLoaded('estudiante', [
                'id' => $this->estudiante->id,
                'name' => $this->estudiante->name,
                'email' => $this->estudiante->email,
                'documento' => $this->estudiante->documento,
            ]),

            'grupo' => $this->whenLoaded('grupo', [
                'id' => $this->grupo->id,
                'nombre' => $this->grupo->nombre,
            ]),

            'modulo' => $this->whenLoaded('modulo', [
                'id' => $this->modulo->id,
                'nombre' => $this->modulo->nombre,
            ]),

            'esquema_calificacion' => $this->whenLoaded('esquemaCalificacion', function () {
                return new EsquemaCalificacionResource($this->esquemaCalificacion);
            }),

            'tipo_nota_esquema' => $this->whenLoaded('tipoNotaEsquema', function () {
                return new TipoNotaEsquemaResource($this->tipoNotaEsquema);
            }),

            'registrado_por' => $this->whenLoaded('registradoPor', [
                'id' => $this->registradoPor->id,
                'name' => $this->registradoPor->name,
                'email' => $this->registradoPor->email,
            ]),
        ];
    }

    /**
     * Obtiene el texto del estado.
     *
     * @return string
     */
    private function getStatusText(): string
    {
        return match($this->status) {
            0 => 'Pendiente',
            1 => 'Registrada',
            2 => 'Cerrada',
            default => 'Desconocido',
        };
    }
}
