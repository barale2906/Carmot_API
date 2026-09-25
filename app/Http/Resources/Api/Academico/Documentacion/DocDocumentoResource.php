<?php

namespace App\Http\Resources\Api\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocDocumento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Da forma a la respuesta JSON de una entrada de la bitácora de documentos.
 *
 * No incluye contenido: los documentos generados se arman al momento de pedirlos
 * y aquí solo queda el rastro de cada impresión.
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
            'id'                => $this->id,
            'tipo_documento_id' => $this->tipo_documento_id,
            'tipo_documento'    => new DocTipoDocumentoResource($this->whenLoaded('tipoDocumento')),
            'plantilla_id'      => $this->plantilla_id,
            'plantilla'         => new DocPlantillaResource($this->whenLoaded('plantilla')),
            'entidad_type'      => $this->entidad_type,
            'entidad_id'        => $this->entidad_id,
            'origen'            => $this->origen,
            'origen_text'       => DocDocumento::getOrigenText($this->origen),
            'fecha_referencia'  => $this->fecha_referencia?->toDateString(),
            'google_drive_url'  => $this->google_drive_url,
            'nombre_original'   => $this->nombre_original,
            'mime_type'         => $this->mime_type,
            'tamano_bytes'      => $this->tamano_bytes,
            'generado_por'      => $this->generado_por,
            'generador'         => $this->whenLoaded('generador', fn () => [
                'id'     => $this->generador->id,
                'nombre' => $this->generador->name,
            ]),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'deleted_at'        => $this->deleted_at,
        ];
    }
}
