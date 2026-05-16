<?php

namespace App\Http\Resources\Api\Financiero\Lp;

use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource LpProductoResource
 *
 * Transforma el modelo LpProducto en una respuesta JSON estructurada.
 * Incluye todos los campos del producto, sus relaciones y el estado formateado.
 *
 * Campos siempre presentes: id, tipo_producto_id, nombre, codigo, descripcion,
 *   status, status_text, created_at, updated_at, deleted_at.
 * Campos condicionales (requieren with() o carga explícita):
 *   - tipo_producto   → with('tipoProducto')
 *   - referencias     → with('referencias')   — colección de LpProductoReferenciaResource
 *   - cursos          → with('cursos')         — ids y nombres de los cursos vinculados
 *   - modulos         → with('modulos')        — ids y nombres de los módulos vinculados
 *   - precios         → with('precios')
 *   - listas_precios  → with('listasPrecios')
 * Contadores (se incluyen cuando están disponibles):
 *   - referencias_count, cursos_count, modulos_count, precios_count,
 *     listas_precios_count
 *
 * @mixin \App\Models\Financiero\Lp\LpProducto
 * @package App\Http\Resources\Api\Financiero\Lp
 */
class LpProductoResource extends JsonResource
{
    use HasActiveStatus;

    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request Solicitud HTTP actual
     * @return array<string, mixed> Array con los datos formateados del producto
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'tipo_producto_id' => $this->tipo_producto_id,
            'nombre'      => $this->nombre,
            'codigo'      => $this->codigo,
            'descripcion' => $this->descripcion,
            'status'      => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            'created_at'  => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'  => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at'  => $this->deleted_at?->format('Y-m-d H:i:s'),

            // Relaciones cargadas
            'tipo_producto' => $this->whenLoaded('tipoProducto', function () {
                return new LpTipoProductoResource($this->tipoProducto);
            }),

            // Vínculos académicos detallados (registros de la tabla pivot)
            'referencias' => $this->whenLoaded('referencias', function () {
                return LpProductoReferenciaResource::collection($this->referencias);
            }),

            // Cursos vinculados directamente
            'cursos' => $this->whenLoaded('cursos', function () {
                return $this->cursos->map(fn ($curso) => [
                    'id'     => $curso->id,
                    'nombre' => $curso->nombre,
                    'codigo' => $curso->codigo ?? null,
                ]);
            }),

            // Módulos vinculados directamente
            'modulos' => $this->whenLoaded('modulos', function () {
                return $this->modulos->map(fn ($modulo) => [
                    'id'     => $modulo->id,
                    'nombre' => $modulo->nombre,
                    'codigo' => $modulo->codigo ?? null,
                ]);
            }),

            'precios' => $this->whenLoaded('precios', function () {
                return LpPrecioProductoResource::collection($this->precios);
            }),

            'listas_precios' => $this->whenLoaded('listasPrecios', function () {
                return LpListaPrecioResource::collection($this->listasPrecios);
            }),

            // Contadores
            'referencias_count'    => $this->when(isset($this->referencias_count), $this->referencias_count),
            'cursos_count'         => $this->when(isset($this->cursos_count), $this->cursos_count),
            'modulos_count'        => $this->when(isset($this->modulos_count), $this->modulos_count),
            'precios_count'        => $this->when(isset($this->precios_count), $this->precios_count),
            'listas_precios_count' => $this->when(isset($this->listas_precios_count), $this->listas_precios_count),
        ];
    }
}
