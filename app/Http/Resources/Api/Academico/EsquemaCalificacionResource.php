<?php

namespace App\Http\Resources\Api\Academico;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EsquemaCalificacionResource extends JsonResource
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
            'modulo_id' => $this->modulo_id,
            'grupo_id' => $this->grupo_id,
            'profesor_id' => $this->profesor_id,
            'nombre_esquema' => $this->nombre_esquema,
            'descripcion' => $this->descripcion,
            'condicion_aplicacion' => $this->condicion_aplicacion,
            'status' => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'suma_pesos' => $this->suma_pesos,
            'pesos_validos' => $this->validarPesos(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'modulo' => $this->whenLoaded('modulo', [
                'id' => $this->modulo->id,
                'nombre' => $this->modulo->nombre,
                'status' => $this->modulo->status,
                'status_text' => self::getActiveStatusText($this->modulo->status),
            ]),

            'grupo' => $this->whenLoaded('grupo', function () {
                return [
                    'id' => $this->grupo->id,
                    'nombre' => $this->grupo->nombre,
                    'status' => $this->grupo->status,
                    'status_text' => self::getActiveStatusText($this->grupo->status),
                ];
            }),

            'profesor' => $this->whenLoaded('profesor', [
                'id' => $this->profesor->id,
                'name' => $this->profesor->name,
                'email' => $this->profesor->email,
                'documento' => $this->profesor->documento,
            ]),

            'tipos_nota' => $this->whenLoaded('tiposNota', function () {
                return TipoNotaEsquemaResource::collection($this->tiposNota);
            }),

            // Contadores
            'tipos_nota_count' => $this->when(isset($this->tipos_nota_count), $this->tipos_nota_count),
            'notas_estudiantes_count' => $this->when(isset($this->notas_estudiantes_count), $this->notas_estudiantes_count),
        ];
    }
}
