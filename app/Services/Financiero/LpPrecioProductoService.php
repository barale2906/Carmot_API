<?php

namespace App\Services\Financiero;

use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\Lp\LpPrecioProducto;
use Carbon\Carbon;

/**
 * Servicio LpPrecioProductoService
 *
 * Servicio que contiene la lógica de negocio para el manejo de precios de productos
 * en las listas de precios. Incluye métodos para calcular cuotas, obtener precios
 * y validar solapamientos de vigencia.
 *
 * @package App\Services\Financiero
 */
class LpPrecioProductoService
{
    /**
     * Redondea un valor al 100 más cercano.
     * Ejemplo: 5530 → 5500, 6580 → 6600
     *
     * @param float $valor Valor a redondear
     * @return float Valor redondeado al 100 más cercano
     */
    public function redondearACien(float $valor): float
    {
        return round($valor / 100) * 100;
    }

    /**
     * Calcula el valor de la cuota para un producto financiable.
     * La fórmula es: (precio_total - matricula) / numero_cuotas, redondeado al 100.
     *
     * @param float $precioTotal Precio total del producto
     * @param float $matricula Valor de la matrícula pagada
     * @param int $numeroCuotas Número de cuotas
     * @return float Valor de la cuota calculado y redondeado al 100 más cercano, o 0 si número de cuotas es inválido
     * @throws \InvalidArgumentException Si el número de cuotas es menor o igual a 0
     */
    public function calcularCuota(float $precioTotal, float $matricula, int $numeroCuotas): float
    {
        if ($numeroCuotas <= 0) {
            throw new \InvalidArgumentException('El número de cuotas debe ser mayor a 0');
        }

        $valorRestante = $precioTotal - $matricula;

        if ($valorRestante <= 0) {
            return 0;
        }

        $valorCuota = $valorRestante / $numeroCuotas;

        return $this->redondearACien($valorCuota);
    }

    /**
     * Obtiene el precio de un producto para una población y fecha específica.
     * Retorna el precio del producto desde la lista de precios vigente (activa)
     * que aplica para la población especificada.
     *
     * @param int $productoId ID del producto
     * @param int $poblacionId ID de la población (ciudad)
     * @param Carbon|null $fecha Fecha para verificar vigencia. Si es null, usa la fecha actual
     * @return LpPrecioProducto|null Precio del producto o null si no existe lista vigente
     */
    public function obtenerPrecio(int $productoId, int $poblacionId, ?Carbon $fecha = null): ?LpPrecioProducto
    {
        $fecha = $fecha ?? Carbon::now();

        // Buscar lista de precios vigente para la población
        $listaPrecio = LpListaPrecio::whereHas('poblaciones', function ($query) use ($poblacionId) {
            $query->where('poblacions.id', $poblacionId);
        })
        ->vigentes($fecha) // Solo retorna listas activas (status = 3) y vigentes
        ->first();

        if (!$listaPrecio) {
            return null;
        }

        // Buscar el precio del producto en la lista de precios
        return LpPrecioProducto::where('lista_precio_id', $listaPrecio->id)
            ->where('producto_id', $productoId)
            ->first();
    }

    /**
     * Valida que no existan solapamientos de vigencia para una población.
     * Un solapamiento ocurre cuando dos listas de precios activas tienen períodos
     * de vigencia que se superponen para la misma población.
     *
     * @param int $poblacionId ID de la población
     * @param Carbon $fechaInicio Fecha de inicio de vigencia
     * @param Carbon $fechaFin Fecha de fin de vigencia
     * @param int|null $excluirListaId ID de lista a excluir de la validación (útil al actualizar)
     * @return bool True si no hay solapamientos, false si existe solapamiento
     * @throws \InvalidArgumentException Si fecha_fin es menor que fecha_inicio
     */
    public function validarSolapamientoVigencia(
        int $poblacionId,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        ?int $excluirListaId = null
    ): bool {
        if ($fechaFin->lt($fechaInicio)) {
            throw new \InvalidArgumentException('La fecha de fin debe ser mayor o igual a la fecha de inicio');
        }

        $query = LpListaPrecio::whereHas('poblaciones', function ($q) use ($poblacionId) {
            $q->where('poblacions.id', $poblacionId);
        })
        ->where('status', LpListaPrecio::STATUS_ACTIVA) // Solo validar solapamientos con listas activas
        ->where(function ($q) use ($fechaInicio, $fechaFin) {
            // Caso 1: La fecha_inicio de la lista existente está dentro del rango nuevo
            $q->whereBetween('fecha_inicio', [$fechaInicio, $fechaFin])
              // Caso 2: La fecha_fin de la lista existente está dentro del rango nuevo
              ->orWhereBetween('fecha_fin', [$fechaInicio, $fechaFin])
              // Caso 3: La lista existente contiene completamente el rango nuevo
              ->orWhere(function ($q2) use ($fechaInicio, $fechaFin) {
                  $q2->where('fecha_inicio', '<=', $fechaInicio)
                     ->where('fecha_fin', '>=', $fechaFin);
              });
        });

        if ($excluirListaId) {
            $query->where('id', '!=', $excluirListaId);
        }

        return $query->count() === 0;
    }
}

