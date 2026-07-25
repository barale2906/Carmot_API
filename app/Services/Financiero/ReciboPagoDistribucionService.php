<?php

namespace App\Services\Financiero;

use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use Carbon\Carbon;

/**
 * ReciboPagoDistribucionService
 *
 * Distribuye un monto de pago entre las cuotas pendientes de una matrícula
 * de la más antigua a la más reciente (algoritmo greedy oldest-to-newest).
 *
 * El resultado es un array de líneas de distribución listas para insertar en
 * recibo_pago_concepto_pago y para llamar a Cartera::aplicarPago().
 */
class ReciboPagoDistribucionService
{
    public function __construct(
        private readonly CarteraDescuentoService $descuentoService
    ) {}

    /**
     * Distribuye el monto entre las carteras pendientes de la matrícula.
     *
     * Aplica dos tipos de descuento de forma independiente:
     * - Pronto pago (pago_anticipado): solo a cuotas mensuales (numero_cuota > 0)
     *   cuando se cumple la anticipación requerida. Requiere $aplicarDescuento = true.
     * - Promoción matrícula (promocion_matricula): aplica automáticamente a la cuota
     *   de matrícula (numero_cuota = 0) si existe un descuento activo de ese tipo.
     *
     * @param  Matricula   $matricula
     * @param  float       $monto            total a distribuir
     * @param  bool        $aplicarDescuento si true, intenta aplicar descuento de pronto pago a cuotas mensuales
     * @param  Carbon|null $fechaReferencia  fecha del pago (default: hoy)
     * @return array<int, array{cartera: Cartera, valor: float, descuento: float, descuento_obj: \App\Models\Financiero\Descuento\Descuento|null}>
     *
     * @throws \InvalidArgumentException si el monto excede el saldo total pendiente
     */
    public function distribuir(
        Matricula $matricula,
        float $monto,
        bool $aplicarDescuento = false,
        ?Carbon $fechaReferencia = null
    ): array {
        $fecha = $fechaReferencia ?? Carbon::today();

        // Cargar carteras pendientes ordenadas de más antigua a más reciente
        $carteras = $matricula->carteras()
            ->pendientes()
            ->orderBy('fecha_vencimiento')
            ->orderBy('numero_cuota')
            ->get();

        if ($carteras->isEmpty()) {
            throw new \InvalidArgumentException('La matrícula no tiene cuotas pendientes de pago.');
        }

        $totalSaldo = $carteras->sum('saldo');

        if ($monto > $totalSaldo + 0.01) {
            throw new \InvalidArgumentException(
                "El monto ({$monto}) supera el saldo total pendiente ({$totalSaldo})."
            );
        }

        // Descuento de matrícula automático (cuota 0, independiente de $aplicarDescuento)
        $infoDescuentoMatricula = $this->descuentoService->calcularDescuentoMatricula($matricula, $fecha);

        // Descuento de pronto pago: se obtiene el objeto para evaluar per-cuota
        $descuentoProntoPago = $aplicarDescuento
            ? $this->descuentoService->obtenerDescuentoProntoPago($fecha)
            : null;
        $diasRequeridos = $descuentoProntoPago !== null
            ? (int) ($descuentoProntoPago->dias_anticipacion ?? 0)
            : 0;

        // Distribuir de la más antigua a la más reciente.
        // El usuario paga el costo efectivo de cada cuota (saldo menos descuento si aplica),
        // de modo que el ahorro del descuento rueda automáticamente a la siguiente cuota.
        $plan     = [];
        $restante = $monto;

        foreach ($carteras as $cartera) {
            if ($restante <= 0) {
                break;
            }

            $saldo          = (float) $cartera->saldo;
            $descuentoLinea = 0.0;

            if ($cartera->numero_cuota === 0) {
                // Cuota de matrícula: aplicar descuento de promoción si el monto cubre el costo efectivo
                if ($infoDescuentoMatricula['aplica'] && $infoDescuentoMatricula['descuento'] !== null) {
                    $candidato     = $infoDescuentoMatricula['descuento']->calcularDescuento($saldo);
                    $efectivaSaldo = $saldo - $candidato;
                    if ($restante >= $efectivaSaldo - 0.01) {
                        $descuentoLinea = $candidato;
                    }
                }
            } elseif ($descuentoProntoPago !== null) {
                // Cuota mensual: verificar anticipación de esta cuota específica
                $esProxima = $cartera->fecha_vencimiento >= $fecha->toDateString();
                $cumpleAnticipacion = true;
                if ($esProxima && $diasRequeridos > 0) {
                    $diasAntelacion     = $fecha->diffInDays(Carbon::parse($cartera->fecha_vencimiento));
                    $cumpleAnticipacion = $diasAntelacion >= $diasRequeridos;
                }
                if ($esProxima && $cumpleAnticipacion) {
                    // El descuento se calcula sobre el valor bruto de la cuota para que el
                    // beneficio sea proporcional al 100 % del precio original, sin importar
                    // cuánto se haya abonado previamente. Se resta el descuento ya aplicado
                    // en recibos anteriores y se topa al saldo para no generar negativos.
                    $descuentoSobreValor = $descuentoProntoPago->calcularDescuento((float) $cartera->valor);
                    $candidato = min(
                        max(0.0, $descuentoSobreValor - (float) $cartera->descuento),
                        $saldo
                    );
                    $efectivaSaldo = $saldo - $candidato;
                    if ($restante >= $efectivaSaldo - 0.01) {
                        $descuentoLinea = $candidato;
                    }
                }
            }

            // El usuario paga el costo efectivo: saldo menos el descuento aplicado
            $efectivaSaldo = $saldo - $descuentoLinea;
            $aCubrir       = min($restante, max(0.0, $efectivaSaldo));

            // Saltar solo si no hay pago ni descuento que aplicar
            if ($aCubrir <= 0 && $descuentoLinea <= 0) {
                continue;
            }

            $descuentoObj = null;
            if ($descuentoLinea > 0) {
                $descuentoObj = $cartera->numero_cuota === 0
                    ? $infoDescuentoMatricula['descuento']
                    : $descuentoProntoPago;
            }

            $plan[] = [
                'cartera'      => $cartera,
                'valor'        => $aCubrir,
                'descuento'    => $descuentoLinea,
                'descuento_obj' => $descuentoObj,
            ];

            $restante -= $aCubrir;
        }

        // Si después de cubrir todas las cuotas sobra dinero, el monto ingresado es mayor
        // que el costo efectivo total (descuentos reducen lo que el estudiante debe pagar).
        // Se rechaza para que el cajero corrija el monto en lugar de quedar con sobrante.
        if ($restante > 0.01) {
            $costoEfectivo = round($monto - $restante, 2);
            throw new \InvalidArgumentException(
                "El monto ({$monto}) supera el costo efectivo de las cuotas pendientes. El máximo a pagar es {$costoEfectivo}."
            );
        }

        return $plan;
    }
}
