<?php

namespace App\Services\Financiero;

use App\Models\Academico\Matricula;
use App\Models\Financiero\Descuento\Descuento;
use Carbon\Carbon;

/**
 * CarteraDescuentoService
 *
 * Calcula descuentos aplicables al pagar las cuotas de una matrícula.
 *
 * Pronto pago — condiciones:
 *  1. El pago se realiza antes del vencimiento de la primera cuota mensual próxima.
 *  2. Existe un Descuento activo con tipo_activacion = 'pago_anticipado' y aplicacion = 'cuota'.
 *  3. El descuento se evalúa cuota a cuota: cada una debe cumplir dias_anticipacion individualmente.
 *
 * Las cuotas vencidas no bloquean el pronto pago. Se descuentan del monto disponible sin aplicar
 * descuento y el remanente rueda automáticamente a las cuotas próximas que sí cumplan la anticipación.
 *
 * Promoción matrícula — aplica automáticamente cuando:
 *  - Existe un Descuento activo con tipo_activacion = 'promocion_matricula' y aplicacion = 'matricula'.
 *  - La cartera de matrícula (numero_cuota = 0) está pendiente.
 *
 * El descuento de pronto pago se aplica EXCLUSIVAMENTE a cuotas mensuales (numero_cuota > 0).
 * La cuota de matrícula (numero_cuota = 0) queda reservada para el descuento de promocion_matricula.
 */
class CarteraDescuentoService
{
    /**
     * Calcula el descuento por pronto pago aplicable a las cuotas mensuales de una matrícula.
     *
     * Solo se consideran cuotas con numero_cuota > 0. La cuota de matrícula (numero_cuota = 0)
     * no participa en este cálculo; su descuento lo gestiona calcularDescuentoMatricula().
     *
     * El descuento se evalúa cuota por cuota: cada una debe cumplir los días de anticipación
     * configurados de forma individual. Cuotas sin suficiente anticipación no reciben descuento
     * pero permiten que las cuotas siguientes sí lo hagan.
     *
     * @param  Matricula     $matricula
     * @param  float         $montoAPagar      monto total que el estudiante va a pagar
     * @param  Carbon|null   $fechaReferencia  fecha del pago (default: hoy)
     * @return array{aplica: bool, valor: float, descuento: Descuento|null, motivo: string}
     */
    public function calcular(Matricula $matricula, float $montoAPagar, ?Carbon $fechaReferencia = null): array
    {
        $fecha = $fechaReferencia ?? Carbon::today();

        // Siguiente cuota mensual próxima (excluye cuota 0 / matrícula)
        $siguienteCuota = $matricula->carteras()
            ->proximas($fecha->toDateString())
            ->where('numero_cuota', '>', 0)
            ->orderBy('fecha_vencimiento')
            ->first();

        if (! $siguienteCuota) {
            return $this->sinDescuento('No hay cuotas mensuales próximas a pagar.');
        }

        // Condición 2: paga antes o en la fecha de vencimiento de la siguiente cuota mensual
        if ($fecha->gt(Carbon::parse($siguienteCuota->fecha_vencimiento))) {
            return $this->sinDescuento('El pago llega después del vencimiento de la siguiente cuota mensual.');
        }

        // Condición 3: existe descuento activo de tipo pago_anticipado aplicado a cuota
        $descuento = Descuento::vigentes($fecha)
            ->where('tipo_activacion', Descuento::ACTIVACION_PAGO_ANTICIPADO)
            ->where('aplicacion', Descuento::APLICACION_CUOTA)
            ->first();

        if (! $descuento) {
            return $this->sinDescuento('No hay descuento por pronto pago activo.');
        }

        $diasRequeridos = (int) ($descuento->dias_anticipacion ?? 0);

        // Descontar el costo efectivo de la cuota de matrícula pendiente, ya que
        // distribuir() la paga primero (orden oldest-to-newest por fecha_vencimiento).
        $infoMatricula    = $this->calcularDescuentoMatricula($matricula, $fecha);
        $carteraMatricula = $matricula->carteras()->pendientes()->where('numero_cuota', 0)->first();
        if ($carteraMatricula) {
            $costoMatricula = (float) $carteraMatricula->saldo
                - ($infoMatricula['aplica'] ? $infoMatricula['valor'] : 0.0);
            $montoAPagar = max(0.0, $montoAPagar - $costoMatricula);
        }

        // Simular la distribución cuota a cuota para sumar el descuento de las elegibles.
        // Una cuota es elegible si: es próxima, cumple los días de anticipación requeridos
        // Y el monto disponible cubre su saldo efectivo (saldo - descuento).
        $carteras = $matricula->carteras()
            ->pendientes()
            ->where('numero_cuota', '>', 0)
            ->orderBy('fecha_vencimiento')
            ->orderBy('numero_cuota')
            ->get();

        $valorTotal = 0.0;
        $restante   = $montoAPagar;

        foreach ($carteras as $cartera) {
            if ($restante <= 0) {
                break;
            }

            $saldo     = (float) $cartera->saldo;
            $esProxima = $cartera->fecha_vencimiento >= $fecha->toDateString();

            if (! $esProxima) {
                // Cuota vencida (no debe existir por Condición 1, pero se maneja por robustez)
                $restante -= min($restante, $saldo);
                continue;
            }

            $cumpleAnticipacion = true;
            if ($diasRequeridos > 0) {
                $diasAntelacion     = $fecha->diffInDays(Carbon::parse($cartera->fecha_vencimiento));
                $cumpleAnticipacion = $diasAntelacion >= $diasRequeridos;
            }

            if ($cumpleAnticipacion) {
                // El descuento se calcula sobre el valor bruto de la cuota para que el
                // beneficio sea proporcional al 100 % del precio original. En cuotas con
                // abono previo (Abonada) esto devuelve el descuento real que le corresponde
                // al estudiante por pagar a tiempo, no solo el 5 % del saldo pendiente.
                $descuentoSobreValor = $descuento->calcularDescuento((float) $cartera->valor);
                $descuentoNuevo = min(
                    max(0.0, $descuentoSobreValor - (float) $cartera->descuento),
                    $saldo
                );
                $efectivaSaldo = max(0.0, $saldo - $descuentoNuevo);

                if ($restante >= $efectivaSaldo - 0.01) {
                    // Cuota completamente cubierta: acumular descuento y avanzar por costo efectivo
                    $valorTotal += $descuentoNuevo;
                    $restante   -= $efectivaSaldo;
                } else {
                    // Monto insuficiente para cubrir ni siquiera el costo efectivo: pago parcial
                    $restante = 0;
                }
            } else {
                // No cumple anticipación: paga saldo completo sin descuento
                $restante -= min($restante, $saldo);
            }
        }

        if ($valorTotal <= 0) {
            return $this->sinDescuento(
                $diasRequeridos > 0
                    ? "Ninguna cuota próxima cumple los {$diasRequeridos} días de anticipación requeridos."
                    : 'El monto no cubre completamente ninguna cuota mensual próxima.'
            );
        }

        return [
            'aplica'    => true,
            'valor'     => $valorTotal,
            'descuento' => $descuento,
            'motivo'    => "Pronto pago — {$descuento->nombre}",
        ];
    }

    /**
     * Retorna el Descuento de pronto pago activo y vigente, si existe.
     * Usado por ReciboPagoDistribucionService para aplicar el descuento per-cuota.
     *
     * @param  Carbon|null $fechaReferencia
     * @return Descuento|null
     */
    public function obtenerDescuentoProntoPago(?Carbon $fechaReferencia = null): ?Descuento
    {
        $fecha = $fechaReferencia ?? Carbon::today();
        return Descuento::vigentes($fecha)
            ->where('tipo_activacion', Descuento::ACTIVACION_PAGO_ANTICIPADO)
            ->where('aplicacion', Descuento::APLICACION_CUOTA)
            ->first();
    }

    /**
     * Calcula el descuento por promoción de matrícula si aplica.
     *
     * Aplica automáticamente cuando existe un Descuento vigente con
     * tipo_activacion = 'promocion_matricula' y aplicacion = 'matricula',
     * y la cartera de matrícula (numero_cuota = 0) está pendiente de pago.
     *
     * @param  Matricula   $matricula
     * @param  Carbon|null $fechaReferencia
     * @return array{aplica: bool, valor: float, descuento: Descuento|null, motivo: string}
     */
    public function calcularDescuentoMatricula(Matricula $matricula, ?Carbon $fechaReferencia = null): array
    {
        $fecha = $fechaReferencia ?? Carbon::today();

        $descuento = Descuento::vigentes($fecha)
            ->where('tipo_activacion', Descuento::ACTIVACION_PROMOCION_MATRICULA)
            ->where('aplicacion', Descuento::APLICACION_MATRICULA)
            ->first();

        if (! $descuento) {
            return $this->sinDescuento('No hay descuento de matrícula activo.');
        }

        $carteraMatricula = $matricula->carteras()
            ->pendientes()
            ->where('numero_cuota', 0)
            ->first();

        if (! $carteraMatricula) {
            return $this->sinDescuento('No hay cuota de matrícula pendiente.');
        }

        $valor = $descuento->calcularDescuento((float) $carteraMatricula->saldo);

        return [
            'aplica'    => true,
            'valor'     => $valor,
            'descuento' => $descuento,
            'motivo'    => "Descuento matrícula ({$descuento->nombre})",
        ];
    }

    /**
     * Estructura de respuesta sin descuento.
     */
    private function sinDescuento(string $motivo): array
    {
        return [
            'aplica'    => false,
            'valor'     => 0.0,
            'descuento' => null,
            'motivo'    => $motivo,
        ];
    }
}
