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
 */
class LpPrecioProductoService
{
    /**
     * Redondea un valor al 100 más cercano.
     * Ejemplo: 5530 → 5500, 6580 → 6600
     *
     * @param  float  $valor  Valor a redondear
     * @return float Valor redondeado al 100 más cercano
     */
    public function redondearACien(float $valor): float
    {
        return round($valor / 100) * 100;
    }

    /**
     * Calcula el valor de la cuota para un producto financiable.
     * $precioTotal ya es el saldo a financiar, neto de matrícula
     * (precio_contado = matricula + precio_total), por lo que la fórmula
     * es precio_total / numero_cuotas, redondeado al 100.
     *
     * @param  float  $precioTotal  Saldo a financiar (precio_total, neto de matrícula)
     * @param  int  $numeroCuotas  Número de cuotas
     * @return float Valor de la cuota calculado y redondeado al 100 más cercano, o 0 si el saldo es 0
     *
     * @throws \InvalidArgumentException Si el número de cuotas es menor o igual a 0
     */
    public function calcularCuota(float $precioTotal, int $numeroCuotas): float
    {
        if ($numeroCuotas <= 0) {
            throw new \InvalidArgumentException('El número de cuotas debe ser mayor a 0');
        }

        if ($precioTotal <= 0) {
            return 0;
        }

        return $this->redondearACien($precioTotal / $numeroCuotas);
    }

    /**
     * Obtiene todos los precios de un producto para una población y fecha específica.
     *
     * Un producto puede tener múltiples registros de precio dentro de la misma lista
     * (por ejemplo, uno de contado y otro financiado). Por eso retorna una colección.
     * Si no existe lista vigente para la población indicada, retorna una colección vacía.
     *
     * @param  int  $productoId  ID del producto LP
     * @param  int  $poblacionId  ID de la población (ciudad)
     * @param  Carbon|null  $fecha  Fecha de vigencia. Si es null, usa la fecha actual.
     * @return \Illuminate\Support\Collection<int, LpPrecioProducto>
     */
    public function obtenerPrecios(int $productoId, int $poblacionId, ?Carbon $fecha = null): \Illuminate\Support\Collection
    {
        $fecha = $fecha ?? Carbon::now();

        $listaPrecio = LpListaPrecio::whereHas('poblaciones', function ($query) use ($poblacionId) {
            $query->where('poblacions.id', $poblacionId);
        })
            ->vigentes($fecha)
            ->first();

        if (! $listaPrecio) {
            return collect();
        }

        return LpPrecioProducto::where('lista_precio_id', $listaPrecio->id)
            ->where('producto_id', $productoId)
            ->get();
    }

    /**
     * Obtiene el primer precio de un producto para una población y fecha específica.
     * Método de compatibilidad — preferir obtenerPrecios() cuando se necesiten
     * todos los registros de precio disponibles.
     *
     * @param  int  $productoId  ID del producto LP
     * @param  int  $poblacionId  ID de la población (ciudad)
     * @param  Carbon|null  $fecha  Fecha de vigencia. Si es null, usa la fecha actual.
     */
    public function obtenerPrecio(int $productoId, int $poblacionId, ?Carbon $fecha = null): ?LpPrecioProducto
    {
        return $this->obtenerPrecios($productoId, $poblacionId, $fecha)->first();
    }

    /**
     * Obtiene todos los precios asociados a una referencia académica (curso o módulo)
     * para una población y fecha específica.
     *
     * Este método es el punto de entrada principal para el frontend: recibe directamente
     * la referencia académica (curso/módulo) y la sede, y retorna todos los precios
     * disponibles de todos los productos LP vinculados a esa referencia en la lista vigente.
     *
     * Un mismo curso puede estar vinculado a más de un producto LP, y cada producto puede
     * tener más de un precio en la lista (ej. contado y financiado), por lo que la
     * respuesta puede contener múltiples registros agrupados por producto.
     *
     * @param  int  $referenciaId  ID de la referencia académica (curso o módulo)
     * @param  string  $referenciaTipo  Tipo de referencia: 'curso' o 'modulo'
     * @param  int  $poblacionId  ID de la población (sede/ciudad)
     * @param  Carbon|null  $fecha  Fecha de vigencia. Si es null, usa la fecha actual.
     * @return \Illuminate\Support\Collection<int, LpPrecioProducto>
     */
    public function obtenerPreciosPorReferencia(
        int $referenciaId,
        string $referenciaTipo,
        int $poblacionId,
        ?Carbon $fecha = null
    ): \Illuminate\Support\Collection {
        $fecha = $fecha ?? Carbon::now();

        $listaPrecio = LpListaPrecio::whereHas('poblaciones', function ($query) use ($poblacionId) {
            $query->where('poblacions.id', $poblacionId);
        })
            ->vigentes($fecha)
            ->first();

        if (! $listaPrecio) {
            return collect();
        }

        return LpPrecioProducto::where('lista_precio_id', $listaPrecio->id)
            ->whereHas('producto.referencias', function ($query) use ($referenciaId, $referenciaTipo) {
                $query->where('referencia_id', $referenciaId)
                    ->where('referencia_tipo', $referenciaTipo);
            })
            ->with(['producto.tipoProducto', 'producto.referencias', 'listaPrecio.poblaciones'])
            ->get();
    }

    /**
     * Valida que no existan solapamientos de vigencia para una población.
     * Un solapamiento ocurre cuando dos listas de precios activas tienen períodos
     * de vigencia que se superponen para la misma población.
     *
     * @param  int  $poblacionId  ID de la población
     * @param  Carbon  $fechaInicio  Fecha de inicio de vigencia
     * @param  Carbon  $fechaFin  Fecha de fin de vigencia
     * @param  int|null  $excluirListaId  ID de lista a excluir de la validación (útil al actualizar)
     * @return bool True si no hay solapamientos, false si existe solapamiento
     *
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

    /**
     * Comprueba la regla de negocio: precio de contado = matrícula + precio total financiado.
     * Usa redondeo a 2 decimales y tolerancia de un centavo por flotantes.
     */
    public function precioContadoCuadraConFinanciacion(float $precioContado, float $matricula, float $precioTotal): bool
    {
        $esperado = round($matricula + $precioTotal, 2);
        $contado = round($precioContado, 2);

        return abs($contado - $esperado) <= 0.01;
    }

    /**
     * Mensaje de validación cuando no se cumple precio_contado = matricula + precio_total.
     */
    public function mensajePrecioContadoFinanciacion(): string
    {
        return 'El precio de contado debe ser igual a la matrícula más el precio total financiado (precio_contado = matricula + precio_total). '
            .'Equivalencias: matricula = precio_contado - precio_total; precio_total = precio_contado - matricula.';
    }
}
