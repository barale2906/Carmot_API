<?php

namespace App\Http\Resources\Api\Traslados;

use App\Models\Traslados\MatriculaTraslado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Registro del historial de traslados de una matrícula.
 *
 * @mixin MatriculaTraslado
 */
class MatriculaTrasladoResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricula_id' => $this->matricula_id,
            'estudiante' => $this->whenLoaded('matricula', fn () => $this->matricula->estudiante ? [
                'id' => $this->matricula->estudiante->id,
                'name' => $this->matricula->estudiante->name,
            ] : null),
            'tipo' => $this->tipo,
            'tipo_texto' => MatriculaTraslado::TIPOS[$this->tipo] ?? null,
            'fecha' => $this->fecha?->format('Y-m-d'),
            'motivo' => $this->motivo,
            'origen' => [
                'sede' => $this->ubicacion('sedeOrigen', $this->sede_origen_id),
                'curso' => $this->ubicacion('cursoOrigen', $this->curso_origen_id),
                'ciclo' => $this->ubicacion('cicloOrigen', $this->ciclo_origen_id),
                'programacion' => $this->ubicacion('programacionOrigen', $this->programacion_origen_id),
                'lp_precio_producto_id' => $this->lp_origen_id,
            ],
            'destino' => [
                'sede' => $this->ubicacion('sedeDestino', $this->sede_destino_id),
                'curso' => $this->ubicacion('cursoDestino', $this->curso_destino_id),
                'ciclo' => $this->ubicacion('cicloDestino', $this->ciclo_destino_id),
                'programacion' => $this->ubicacion('programacionDestino', $this->programacion_destino_id),
                'lp_precio_producto_id' => $this->lp_destino_id,
            ],
            'financiero' => [
                'valor_pagado' => (float) $this->valor_pagado,
                'saldo_anulado' => (float) $this->saldo_anulado,
                'saldo_nuevo' => (float) $this->saldo_nuevo,
                'costo_traslado' => (float) $this->costo_traslado,
                'carteras' => $this->carteras,
            ],
            'reposiciones_canceladas' => $this->reposiciones_canceladas,
            'nivelacion' => $this->nivelacion,
            'nivelacion_pendiente' => $this->nivelacion_pendiente,
            'nivelacion_resuelta_at' => $this->nivelacion_resuelta_at?->format('Y-m-d H:i:s'),
            'nivelacion_resuelta_por' => $this->whenLoaded('nivelacionResueltaPor', fn () => $this->nivelacionResueltaPor ? [
                'id' => $this->nivelacionResueltaPor->id,
                'name' => $this->nivelacionResueltaPor->name,
            ] : null),
            'nivelacion_observaciones' => $this->nivelacion_observaciones,
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario ? [
                'id' => $this->usuario->id,
                'name' => $this->usuario->name,
            ] : null),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Id y nombre de una ubicación; el nombre solo si la relación está cargada.
     *
     * @return array{id: int|null, nombre: string|null}
     */
    private function ubicacion(string $relacion, ?int $id): array
    {
        return ['id' => $id, 'nombre' => $this->relationLoaded($relacion) ? $this->{$relacion}?->nombre : null];
    }
}
