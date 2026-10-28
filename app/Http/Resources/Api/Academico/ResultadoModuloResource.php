<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila del consolidado de notas: resultado de un estudiante en el módulo de un grupo,
 * tal como lo calcula {@see \App\Services\Academico\ConsolidadoNotasService}.
 */
class ResultadoModuloResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'estudiante'           => $this->resource['estudiante'],
            'ciclo'                => $this->resource['ciclo'],
            'grupo'                => $this->resource['grupo'],
            'modulo'               => $this->resource['modulo'],
            'profesor'             => $this->resource['profesor'],
            'esquema_calificacion' => $this->resource['esquema_calificacion'],
            'tipos_nota'           => $this->resource['tipos_nota'],
            'nota_final'           => $this->resource['nota_final'],
            'completo'             => $this->resource['completo'],
            'recuperacion'         => $this->resource['recuperacion'],
            'nota_definitiva'      => $this->resource['nota_definitiva'],
            'aprobado'             => $this->resource['aprobado'],
            'estado'               => $this->resource['estado'],
            'puede_recuperar'      => $this->resource['puede_recuperar'],
            'notas_cerradas'       => $this->resource['notas_cerradas'],
        ];
    }
}
