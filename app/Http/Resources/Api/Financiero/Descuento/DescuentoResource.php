<?php

namespace App\Http\Resources\Api\Financiero\Descuento;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource DescuentoResource
 *
 * Transforma el modelo Descuento en una respuesta JSON estructurada.
 * Incluye todos los campos del descuento, sus relaciones y información formateada.
 *
 * @package App\Http\Resources\Api\Financiero\Descuento
 */
class DescuentoResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param Request $request Solicitud HTTP actual
     * @return array<string, mixed> Array con los datos formateados del descuento
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'codigo_descuento' => $this->codigo_descuento,
            'descripcion' => $this->descripcion,
            'tipo' => $this->tipo,
            'tipo_text' => $this->getTipoText(),
            'valor' => (float) $this->valor,
            'valor_formatted' => number_format((float) $this->valor, 2, '.', ','),
            'aplicacion' => $this->aplicacion,
            'aplicacion_text' => $this->getAplicacionText(),
            'tipo_activacion' => $this->tipo_activacion,
            'tipo_activacion_text' => $this->getTipoActivacionText(),
            'dias_anticipacion' => $this->dias_anticipacion,
            'permite_acumulacion' => (bool) $this->permite_acumulacion,
            'fecha_inicio' => $this->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $this->fecha_fin?->format('Y-m-d'),
            'status' => $this->status,
            'status_text' => $this->status_text,
            'esta_vigente' => $this->estaVigente(),
            'listas_precios' => $this->getListasPreciosArray(),
            'productos' => $this->getProductosArray(),
            'sedes' => $this->getSedesArray(),
            'poblaciones' => $this->getPoblacionesArray(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Obtiene el array de listas de precios.
     * Siempre devuelve un array, cargando la relación si es necesario.
     *
     * @return array
     */
    private function getListasPreciosArray(): array
    {
        if (!$this->relationLoaded('listasPrecios')) {
            $this->load('listasPrecios');
        }

        return $this->listasPrecios->map(function ($lista) {
            return [
                'id' => $lista->id,
                'nombre' => $lista->nombre,
                'codigo' => $lista->codigo,
            ];
        })->values()->toArray();
    }

    /**
     * Obtiene el array de productos.
     * Siempre devuelve un array, cargando la relación si es necesario.
     *
     * @return array
     */
    private function getProductosArray(): array
    {
        if (!$this->relationLoaded('productos')) {
            $this->load('productos');
        }

        return $this->productos->map(function ($producto) {
            return [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'codigo' => $producto->codigo,
            ];
        })->values()->toArray();
    }

    /**
     * Obtiene el array de sedes.
     * Siempre devuelve un array, cargando la relación si es necesario.
     *
     * @return array
     */
    private function getSedesArray(): array
    {
        if (!$this->relationLoaded('sedes')) {
            $this->load('sedes');
        }

        return $this->sedes->map(function ($sede) {
            return [
                'id' => $sede->id,
                'nombre' => $sede->nombre,
            ];
        })->values()->toArray();
    }

    /**
     * Obtiene el array de poblaciones.
     * Siempre devuelve un array, cargando la relación si es necesario.
     *
     * @return array
     */
    private function getPoblacionesArray(): array
    {
        if (!$this->relationLoaded('poblaciones')) {
            $this->load('poblaciones');
        }

        return $this->poblaciones->map(function ($poblacion) {
            return [
                'id' => $poblacion->id,
                'nombre' => $poblacion->nombre,
            ];
        })->values()->toArray();
    }

    /**
     * Obtiene el texto del tipo de descuento.
     *
     * @return string
     */
    private function getTipoText(): string
    {
        return match ($this->tipo) {
            'porcentual' => 'Porcentual',
            'valor_fijo' => 'Valor Fijo',
            default => 'Desconocido',
        };
    }

    /**
     * Obtiene el texto de la aplicación del descuento.
     *
     * @return string
     */
    private function getAplicacionText(): string
    {
        return match ($this->aplicacion) {
            'valor_total' => 'Valor Total',
            'matricula' => 'Matrícula',
            'cuota' => 'Cuota',
            default => 'Desconocido',
        };
    }

    /**
     * Obtiene el texto del tipo de activación.
     *
     * @return string
     */
    private function getTipoActivacionText(): string
    {
        return match ($this->tipo_activacion) {
            'pago_anticipado' => 'Pago Anticipado',
            'promocion_matricula' => 'Promoción de Matrícula',
            'codigo_promocional' => 'Código Promocional',
            default => 'Desconocido',
        };
    }
}

