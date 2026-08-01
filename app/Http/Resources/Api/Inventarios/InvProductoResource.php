<?php

namespace App\Http\Resources\Api\Inventarios;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para la representación de un producto del inventario.
 */
class InvProductoResource extends JsonResource
{
    use HasActiveStatus;

    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'codigo'             => $this->codigo,
            'nombre'             => $this->nombre,
            'descripcion'        => $this->descripcion,
            'tipo'               => $this->tipo,
            'imagen'             => $this->imagen,
            'categoria_id'       => $this->categoria_id,
            'unidad_medida_id'   => $this->unidad_medida_id,
            'producto_padre_id'  => $this->producto_padre_id,
            'status'             => $this->status,
            'status_text'        => self::getActiveStatusText($this->status),
            'created_at'         => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'         => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at'         => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas explícitamente
            'categoria' => $this->when(
                $this->relationLoaded('categoria'),
                fn () => $this->categoria ? [
                    'id'     => $this->categoria->id,
                    'nombre' => $this->categoria->nombre,
                ] : null
            ),

            'unidad_medida' => $this->when(
                $this->relationLoaded('unidadMedida'),
                fn () => $this->unidadMedida ? [
                    'id'          => $this->unidadMedida->id,
                    'nombre'      => $this->unidadMedida->nombre,
                    'abreviatura' => $this->unidadMedida->abreviatura,
                ] : null
            ),

            'producto_padre' => $this->when(
                $this->relationLoaded('productoPadre'),
                fn () => $this->productoPadre ? [
                    'id'     => $this->productoPadre->id,
                    'codigo' => $this->productoPadre->codigo,
                    'nombre' => $this->productoPadre->nombre,
                ] : null
            ),

            // Componentes del kit (cuando es tipo=kit y se carga la relación)
            'kit_componentes' => $this->when(
                $this->relationLoaded('kitComponentes'),
                fn () => InvKitComponenteResource::collection($this->kitComponentes)
            ),

            // Variantes del grupo (cuando es tipo=grupo y se carga la relación)
            'variantes_count' => $this->when(isset($this->variantes_count), $this->variantes_count),
        ];
    }
}
