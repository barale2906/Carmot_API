<?php

namespace App\Http\Resources\Api\Academico\Documentacion;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Da forma a la respuesta JSON de un tipo de documento.
 *
 * @package App\Http\Resources\Api\Academico\Documentacion
 */
class DocTipoDocumentoResource extends JsonResource
{
    use HasActiveStatus;

    /**
     * Transforma el recurso en un arreglo.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'codigo'                 => $this->codigo,
            'nombre'                 => $this->nombre,
            'descripcion'            => $this->descripcion,
            'entidad_type'           => $this->entidad_type,
            'entidad_nombre'         => $this->entidad_type
                ? config("documentacion.entidades.{$this->entidad_type}.nombre")
                : null,
            'se_ata_fecha'           => $this->se_ata_fecha,
            'campo_fecha_referencia' => $this->campo_fecha_referencia,
            'prefijo_numero'         => $this->prefijo_numero,
            'status'                 => $this->status,
            'status_text'            => self::getActiveStatusText($this->status),
            'variables'              => $this->whenLoaded(
                'variables',
                fn () => $this->variables->pluck('variable_key')->all()
            ),
            'variables_count'        => $this->whenCounted('variables'),
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
            'deleted_at'             => $this->deleted_at,
        ];
    }
}
