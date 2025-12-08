<?php

namespace App\Services\Financiero;

use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\Descuento\DescuentoAplicado;
use App\Models\Financiero\Lp\LpPrecioProducto;
use Carbon\Carbon;

/**
 * Servicio DescuentoService
 *
 * Contiene la lógica de negocio para calcular y aplicar descuentos a productos.
 * Maneja la acumulación de descuentos, validación de condiciones de activación
 * y registro del historial de descuentos aplicados.
 *
 * @package App\Services\Financiero
 */
class DescuentoService
{
    /**
     * Obtiene los descuentos aplicables para un producto en una lista de precios,
     * considerando la sede, población, condiciones de activación y códigos promocionales.
     *
     * @param int $productoId ID del producto
     * @param int $listaPrecioId ID de la lista de precios
     * @param int|null $sedeId ID de la sede (opcional)
     * @param int|null $poblacionId ID de la población (opcional)
     * @param Carbon|null $fecha Fecha actual para verificar vigencia
     * @param string|null $codigoPromocional Código promocional ingresado (opcional)
     * @param Carbon|null $fechaPago Fecha de pago (para pago anticipado)
     * @param Carbon|null $fechaProgramada Fecha programada de pago (para pago anticipado)
     * @return \Illuminate\Database\Eloquent\Collection Colección de descuentos aplicables
     */
    public function obtenerDescuentosAplicables(
        int $productoId,
        int $listaPrecioId,
        ?int $sedeId = null,
        ?int $poblacionId = null,
        ?Carbon $fecha = null,
        ?string $codigoPromocional = null,
        ?Carbon $fechaPago = null,
        ?Carbon $fechaProgramada = null
    ) {
        $fecha = $fecha ?? Carbon::now();

        $descuentos = Descuento::whereHas('listasPrecios', function ($query) use ($listaPrecioId) {
            $query->where('lp_listas_precios.id', $listaPrecioId);
        })
        ->vigentes($fecha)
        ->get()
        ->filter(function ($descuento) use ($productoId, $sedeId, $poblacionId, $fecha, $codigoPromocional, $fechaPago, $fechaProgramada) {
            // Verificar si aplica al producto
            if (!$descuento->aplicaAProducto($productoId)) {
                return false;
            }

            // Verificar si aplica a la sede o población
            if ($sedeId && !$descuento->aplicaASede($sedeId)) {
                return false;
            }

            if ($poblacionId && !$descuento->aplicaAPoblacion($poblacionId)) {
                return false;
            }

            // Verificar condiciones de activación
            return $descuento->puedeActivar($fecha, $codigoPromocional, $fechaPago, $fechaProgramada);
        });

        return $descuentos;
    }

    /**
     * Calcula el precio final de un producto aplicando los descuentos correspondientes.
     * Considera la lógica de acumulación y asegura que los valores nunca sean negativos.
     *
     * @param float $precioTotal Precio total del producto
     * @param float $matricula Valor de la matrícula
     * @param float $valorCuota Valor de la cuota
     * @param int $productoId ID del producto
     * @param int $listaPrecioId ID de la lista de precios
     * @param int|null $sedeId ID de la sede (opcional)
     * @param int|null $poblacionId ID de la población (opcional)
     * @param string|null $codigoPromocional Código promocional ingresado (opcional)
     * @param Carbon|null $fechaPago Fecha de pago (para pago anticipado)
     * @param Carbon|null $fechaProgramada Fecha programada de pago (para pago anticipado)
     * @return array Array con los valores calculados después de aplicar descuentos
     */
    public function calcularPrecioConDescuentos(
        float $precioTotal,
        float $matricula,
        float $valorCuota,
        int $productoId,
        int $listaPrecioId,
        ?int $sedeId = null,
        ?int $poblacionId = null,
        ?string $codigoPromocional = null,
        ?Carbon $fechaPago = null,
        ?Carbon $fechaProgramada = null
    ): array {
        $descuentos = $this->obtenerDescuentosAplicables(
            $productoId,
            $listaPrecioId,
            $sedeId,
            $poblacionId,
            null,
            $codigoPromocional,
            $fechaPago,
            $fechaProgramada
        );

        // Separar descuentos por tipo de aplicación y si permiten acumulación
        $descuentosValorTotal = [];
        $descuentosMatricula = [];
        $descuentosCuota = [];
        $descuentosNoAcumulables = [];

        foreach ($descuentos as $descuento) {
            if (!$descuento->permite_acumulacion) {
                $descuentosNoAcumulables[] = $descuento;
            } else {
                switch ($descuento->aplicacion) {
                    case Descuento::APLICACION_VALOR_TOTAL:
                        $descuentosValorTotal[] = $descuento;
                        break;
                    case Descuento::APLICACION_MATRICULA:
                        $descuentosMatricula[] = $descuento;
                        break;
                    case Descuento::APLICACION_CUOTA:
                        $descuentosCuota[] = $descuento;
                        break;
                }
            }
        }

        $resultado = [
            'precio_total' => $precioTotal,
            'matricula' => $matricula,
            'valor_cuota' => $valorCuota,
            'descuentos_aplicados' => [],
            'total_descuentos' => 0,
        ];

        // Si hay descuentos no acumulables, solo aplicar el de mayor valor
        if (!empty($descuentosNoAcumulables)) {
            $mejorDescuento = collect($descuentosNoAcumulables)->sortByDesc(function ($d) use ($precioTotal, $matricula, $valorCuota) {
                $valor = match ($d->aplicacion) {
                    Descuento::APLICACION_VALOR_TOTAL => $d->calcularDescuento($precioTotal),
                    Descuento::APLICACION_MATRICULA => $d->calcularDescuento($matricula),
                    Descuento::APLICACION_CUOTA => $d->calcularDescuento($valorCuota),
                    default => 0,
                };
                return $valor;
            })->first();

            // Agregar el mejor descuento al grupo correspondiente
            switch ($mejorDescuento->aplicacion) {
                case Descuento::APLICACION_VALOR_TOTAL:
                    $descuentosValorTotal = [$mejorDescuento];
                    break;
                case Descuento::APLICACION_MATRICULA:
                    $descuentosMatricula = [$mejorDescuento];
                    break;
                case Descuento::APLICACION_CUOTA:
                    $descuentosCuota = [$mejorDescuento];
                    break;
            }
        }

        // Aplicar descuentos al valor total (se acumulan si permiten acumulación)
        $precioTotalBase = $resultado['precio_total'];
        foreach ($descuentosValorTotal as $descuento) {
            $descuentoAplicado = $descuento->calcularDescuento($precioTotalBase);
            $resultado['precio_total'] -= $descuentoAplicado;
            $resultado['total_descuentos'] += $descuentoAplicado;

            $resultado['descuentos_aplicados'][] = [
                'descuento_id' => $descuento->id,
                'nombre' => $descuento->nombre,
                'tipo' => $descuento->tipo,
                'valor' => $descuento->valor,
                'descuento_aplicado' => $descuentoAplicado,
                'aplicacion' => $descuento->aplicacion,
            ];

            // Actualizar base para siguiente descuento acumulable
            $precioTotalBase = max(0, $resultado['precio_total']);
        }

        // Recalcular valor de cuota si se aplicaron descuentos al valor total
        if ($resultado['precio_total'] < $precioTotal && $matricula > 0) {
            $valorRestante = max(0, $resultado['precio_total'] - $resultado['matricula']);
            // Redondear al 100 más cercano
            $resultado['valor_cuota'] = round($valorRestante / max(1, ($precioTotal - $matricula) / max(1, $valorCuota))) / 100 * 100;
        }

        // Aplicar descuentos a la matrícula (se acumulan si permiten acumulación)
        $matriculaBase = $resultado['matricula'];
        foreach ($descuentosMatricula as $descuento) {
            if ($matriculaBase > 0) {
                $descuentoAplicado = $descuento->calcularDescuento($matriculaBase);
                $resultado['matricula'] -= $descuentoAplicado;
                $resultado['total_descuentos'] += $descuentoAplicado;

                $resultado['descuentos_aplicados'][] = [
                    'descuento_id' => $descuento->id,
                    'nombre' => $descuento->nombre,
                    'tipo' => $descuento->tipo,
                    'valor' => $descuento->valor,
                    'descuento_aplicado' => $descuentoAplicado,
                    'aplicacion' => $descuento->aplicacion,
                ];

                // Actualizar base para siguiente descuento acumulable
                $matriculaBase = max(0, $resultado['matricula']);
            }
        }

        // Aplicar descuentos a la cuota (se acumulan si permiten acumulación)
        $cuotaBase = $resultado['valor_cuota'];
        foreach ($descuentosCuota as $descuento) {
            if ($cuotaBase > 0) {
                $descuentoAplicado = $descuento->calcularDescuento($cuotaBase);
                $resultado['valor_cuota'] -= $descuentoAplicado;
                $resultado['total_descuentos'] += $descuentoAplicado;

                $resultado['descuentos_aplicados'][] = [
                    'descuento_id' => $descuento->id,
                    'nombre' => $descuento->nombre,
                    'tipo' => $descuento->tipo,
                    'valor' => $descuento->valor,
                    'descuento_aplicado' => $descuentoAplicado,
                    'aplicacion' => $descuento->aplicacion,
                ];

                // Actualizar base para siguiente descuento acumulable
                $cuotaBase = max(0, $resultado['valor_cuota']);
            }
        }

        // Asegurar que los valores nunca sean negativos (regla fundamental)
        $resultado['precio_total'] = max(0, $resultado['precio_total']);
        $resultado['matricula'] = max(0, $resultado['matricula']);
        $resultado['valor_cuota'] = max(0, $resultado['valor_cuota']);

        return $resultado;
    }

    /**
     * Registra un descuento aplicado en el historial.
     *
     * @param Descuento $descuento Descuento que se aplicó
     * @param string $conceptoTipo Tipo de concepto (matricula, cuota, pago_contado)
     * @param int $conceptoId ID del concepto de pago
     * @param float $valorOriginal Valor original antes del descuento
     * @param float $valorDescuento Valor del descuento aplicado
     * @param float $valorFinal Valor final después del descuento
     * @param int|null $productoId ID del producto relacionado
     * @param int|null $listaPrecioId ID de la lista de precios relacionada
     * @param int|null $sedeId ID de la sede donde se aplicó
     * @param string|null $observaciones Observaciones adicionales
     * @return DescuentoAplicado Registro creado
     */
    public function registrarDescuentoAplicado(
        Descuento $descuento,
        string $conceptoTipo,
        int $conceptoId,
        float $valorOriginal,
        float $valorDescuento,
        float $valorFinal,
        ?int $productoId = null,
        ?int $listaPrecioId = null,
        ?int $sedeId = null,
        ?string $observaciones = null
    ): DescuentoAplicado {
        return DescuentoAplicado::create([
            'descuento_id' => $descuento->id,
            'concepto_tipo' => $conceptoTipo,
            'concepto_id' => $conceptoId,
            'valor_original' => $valorOriginal,
            'valor_descuento' => $valorDescuento,
            'valor_final' => $valorFinal,
            'producto_id' => $productoId,
            'lista_precio_id' => $listaPrecioId,
            'sede_id' => $sedeId,
            'observaciones' => $observaciones,
        ]);
    }
}

