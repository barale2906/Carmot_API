<?php

namespace App\Services\Financiero;

use App\Models\Academico\Matricula;
use App\Models\Financiero\Descuento\Descuento;
use Carbon\Carbon;

/**
 * CarteraDescuentoService
 *
 * Calcula si aplica un descuento por pronto pago al pagar las cuotas de una matrícula.
 *
 * Condiciones generales (AND de todas):
 *  1. No hay cuotas vencidas sin pagar (mora cero antes de este pago).
 *  2. El pago se realiza antes o en la fecha de vencimiento de la siguiente cuota próxima.
 *  3. El monto a pagar cubre al menos el saldo de la siguiente cuota próxima.
 *  4. Existe un Descuento activo con tipo_activacion = 'pago_anticipado' y aplicacion = 'cuota'.
 *
 * Una vez verificadas las condiciones, el descuento se aplica a TODAS las cuotas próximas
 * (no vencidas) que quedan completamente cubiertas por el monto ingresado.
 */
class CarteraDescuentoService
{
    /**
     * Calcula el descuento aplicable para el pago de cuotas de una matrícula.
     *
     * El valor retornado es la suma de los descuentos de todas las cuotas próximas
     * que serían cubiertas completamente por $montoAPagar.
     *
     * @param  Matricula     $matricula
     * @param  float         $montoAPagar      monto total que el estudiante va a pagar
     * @param  Carbon|null   $fechaReferencia  fecha del pago (default: hoy)
     * @return array{aplica: bool, valor: float, descuento: Descuento|null, motivo: string}
     */
    public function calcular(Matricula $matricula, float $montoAPagar, ?Carbon $fechaReferencia = null): array
    {
        $fecha = $fechaReferencia ?? Carbon::today();

        // Condición 1: sin cuotas vencidas con saldo pendiente
        $tieneVencidas = $matricula->carteras()
            ->vencidas($fecha->toDateString())
            ->where('saldo', '>', 0)
            ->exists();

        if ($tieneVencidas) {
            return $this->sinDescuento('Tiene cuotas vencidas sin pagar.');
        }

        // Siguiente cuota próxima pendiente
        $siguienteCuota = $matricula->carteras()
            ->proximas($fecha->toDateString())
            ->orderBy('fecha_vencimiento')
            ->first();

        if (! $siguienteCuota) {
            return $this->sinDescuento('No hay cuotas próximas a pagar.');
        }

        // Condición 2: paga antes o en la fecha de vencimiento de la siguiente cuota
        if ($fecha->gt(Carbon::parse($siguienteCuota->fecha_vencimiento))) {
            return $this->sinDescuento('El pago llega después del vencimiento de la siguiente cuota.');
        }

        // Condición 3: el monto cubre al menos el saldo de la siguiente cuota
        if ($montoAPagar < (float) $siguienteCuota->saldo) {
            return $this->sinDescuento('El monto no cubre el saldo de la siguiente cuota.');
        }

        // Condición 4: existe descuento activo de tipo pago_anticipado aplicado a cuota
        $descuento = Descuento::vigentes($fecha)
            ->where('tipo_activacion', Descuento::ACTIVACION_PAGO_ANTICIPADO)
            ->where('aplicacion', Descuento::APLICACION_CUOTA)
            ->first();

        if (! $descuento) {
            return $this->sinDescuento('No hay descuento por pronto pago activo.');
        }

        // Simular la distribución para sumar el descuento de TODAS las cuotas elegibles.
        // Una cuota es elegible si: es próxima (no vencida) y queda completamente cubierta.
        $carteras = $matricula->carteras()
            ->pendientes()
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
            $aCubrir   = min($restante, $saldo);
            $esProxima = $cartera->fecha_vencimiento >= $fecha->toDateString();

            if ($aCubrir >= $saldo - 0.01 && $esProxima) {
                $valorTotal += $descuento->calcularDescuento($saldo);
            }

            $restante -= $aCubrir;
        }

        if ($valorTotal <= 0) {
            return $this->sinDescuento('El pago no cubre completamente ninguna cuota próxima.');
        }

        return [
            'aplica'    => true,
            'valor'     => $valorTotal,
            'descuento' => $descuento,
            'motivo'    => "Pronto pago — cuotas próximas cubiertas ({$descuento->nombre})",
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
