<?php

namespace App\Http\Resources\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocPlantilla;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Da forma a la respuesta JSON de una versión de plantilla.
 *
 * El contenido solo se incluye cuando la consulta lo trajo: los listados lo
 * omiten para no arrastrar el HTML completo de cada versión.
 *
 * @package App\Http\Resources\Api\Academico\Documentacion
 */
class DocPlantillaResource extends JsonResource
{
    /**
     * Transforma el recurso en un arreglo.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'tipo_documento_id'   => $this->tipo_documento_id,
            'tipo_documento'      => new DocTipoDocumentoResource($this->whenLoaded('tipoDocumento')),
            'nombre'              => $this->nombre,
            'version'             => $this->version,
            'contenido_html'      => $this->whenNotNull($this->contenido_html),
            'status'              => $this->status,
            'status_text'         => DocPlantilla::getStatusText($this->status),
            'fecha_inicio'        => $this->fecha_inicio?->toDateString(),
            'fecha_fin'           => $this->fecha_fin?->toDateString(),
            'vigente'             => $this->status === DocPlantilla::STATUS_ACTIVA,
            'bloques'             => $this->whenLoaded('bloques', fn () => $this->bloques->map(fn ($bloque) => [
                'bloque'          => $bloque->bloque_key,
                'columnas'        => $bloque->columnas,
                'titulos'         => $bloque->titulos ?? [],
                'mostrar_resumen' => $bloque->mostrar_resumen,
            ])->all()),
            'version_anterior_id' => $this->version_anterior_id,
            'creado_por'          => $this->creado_por,
            'creador'             => $this->whenLoaded('creador', fn () => [
                'id'     => $this->creador->id,
                'nombre' => $this->creador->name,
            ]),
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
            'deleted_at'          => $this->deleted_at,
        ];
    }
}
