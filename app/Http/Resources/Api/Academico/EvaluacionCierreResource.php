<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluacionCierreResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     * Representa la evaluación de cierre de una matrícula (EvaluacionCierreService):
     * `motivos` impiden graduar; `advertencias` son informativas (asistencia).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ciclo_terminado'  => $this->resource['ciclo_terminado'],
            'nota_aprobatoria' => $this->resource['nota_aprobatoria'],
            'modulos'          => $this->resource['modulos'],
            'asistencia'       => $this->resource['asistencia'],
            'saldo_pendiente'  => $this->resource['saldo_pendiente'],
            'aprueba'          => $this->resource['aprueba'],
            'motivos'          => $this->resource['motivos'],
            'advertencias'     => $this->resource['advertencias'],
        ];
    }
}
