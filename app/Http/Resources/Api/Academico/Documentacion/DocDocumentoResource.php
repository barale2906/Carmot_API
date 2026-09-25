<?php

namespace App\Http\Resources\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocDocumento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Da forma a la respuesta JSON de un documento generado o subido.
 *
 * El contenido renderizado solo se incluye cuando la consulta lo trajo: los
 * listados lo omiten para no arrastrar el HTML completo de cada documento.
 *
 * @package App\Http\Resources\Api\Academico\Documentacion
 */
class DocDocumentoResource extends JsonResource
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
            'id'                    => $this->id,
            'numero_documento'      => $this->numero_documento,
            'tipo_documento_id'     => $this->tipo_documento_id,
            'tipo_documento'        => new DocTipoDocumentoResource($this->whenLoaded('tipoDocumento')),
            'plantilla_id'          => $this->plantilla_id,
            'plantilla'             => new DocPlantillaResource($this->whenLoaded('plantilla')),
            'entidad_type'          => $this->entidad_type,
            'entidad_id'            => $this->entidad_id,
            'origen'                => $this->origen,
            'origen_text'           => $this->origen === DocDocumento::ORIGEN_SUBIDO ? 'Subido' : 'Generado',
            'contenido_renderizado' => $this->whenNotNull($this->contenido_renderizado),
            'variables_aplicadas'   => $this->whenNotNull($this->variables_aplicadas),
            'fecha_referencia'      => $this->fecha_referencia?->toDateString(),
            'google_drive_url'      => $this->google_drive_url,
            'nombre_original'       => $this->nombre_original,
            'mime_type'             => $this->mime_type,
            'tamano_bytes'          => $this->tamano_bytes,
            'status'                => $this->status,
            'status_text'           => DocDocumento::getActiveStatusText($this->status),
            'motivo_anulacion'      => $this->motivo_anulacion,
            'generado_por'          => $this->generado_por,
            'generador'             => $this->whenLoaded('generador', fn () => [
                'id'     => $this->generador->id,
                'nombre' => $this->generador->name,
            ]),
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
            'deleted_at'            => $this->deleted_at,
        ];
    }
}
