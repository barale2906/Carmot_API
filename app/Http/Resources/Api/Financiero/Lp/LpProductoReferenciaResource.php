<?php

namespace App\Http\Resources\Api\Financiero\Lp;

use App\Models\Financiero\Lp\LpProductoReferencia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource LpProductoReferenciaResource
 *
 * Transforma el modelo LpProductoReferencia en una respuesta JSON estructurada.
 * Incluye la información del vínculo y, cuando se cargue, la entidad académica
 * (curso o módulo) y el producto LP asociado.
 *
 * Campos siempre presentes: id, lp_producto_id, referencia_id, referencia_tipo,
 *   created_at, updated_at.
 * Campos condicionales:
 *   - producto   → solo si se cargó with('producto')
 *   - referencia → siempre que referencia_id no sea null; contiene id, nombre,
 *                  codigo, tipo y tipo_label del Curso/Modulo resuelto.
 *
 * @mixin \App\Models\Financiero\Lp\LpProductoReferencia
 * @package App\Http\Resources\Api\Financiero\Lp
 */
class LpProductoReferenciaResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'lp_producto_id'  => $this->lp_producto_id,
            'referencia_id'   => $this->referencia_id,
            'referencia_tipo' => $this->referencia_tipo,
            'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'      => $this->updated_at?->format('Y-m-d H:i:s'),

            // Producto LP — solo si fue cargado con with('producto')
            'producto' => $this->whenLoaded('producto', function () {
                return new LpProductoResource($this->producto);
            }),

            // Entidad académica — resuelta desde el accessor referenciaModel
            // Solo presente cuando se cargó manualmente (loadReferenciaModels en el controller)
            'referencia' => $this->when(
                $this->referencia_id !== null,
                function () {
                    $modelo = $this->referenciaModel;

                    if (!$modelo) {
                        return null;
                    }

                    return [
                        'id'     => $modelo->id,
                        'nombre' => $modelo->nombre ?? ($modelo->name ?? null),
                        'codigo' => $modelo->codigo ?? null,
                        'tipo'   => $this->referencia_tipo,
                        'tipo_label' => $this->referencia_tipo === LpProductoReferencia::TIPO_CURSO
                            ? 'Curso'
                            : 'Módulo',
                    ];
                }
            ),
        ];
    }
}
