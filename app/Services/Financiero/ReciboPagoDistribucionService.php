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
     * @param  Matricula   $matricula
     * @param  float       $monto            total a distribuir
     * @param  bool        $aplicarDescuento si true, intenta aplicar descuento por pronto pago
     * @param  Carbon|null $fechaReferencia  fecha del pago (default: hoy)
     * @return array<int, array{cartera: Cartera, valor: float, descuento: float}>
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

        // Calcular descuento si aplica
        $infoDescuento = $aplicarDescuento
            ? $this->descuentoService->calcular($matricula, $monto, $fecha)
            : ['aplica' => false, 'valor' => 0.0, 'descuento' => null];

        // Distribuir de la más antigua a la más reciente
        $plan     = [];
        $restante = $monto;

        foreach ($carteras as $cartera) {
            if ($restante <= 0) {
                break;
            }

            $saldo   = (float) $cartera->saldo;
            $aCubrir = min($restante, $saldo);

            if ($aCubrir <= 0) {
                continue;
            }

            // El descuento aplica a cada cuota próxima (no vencida) que queda completamente cubierta.
            $descuentoLinea = 0.0;
            if ($infoDescuento['aplica'] && $infoDescuento['descuento'] !== null) {
                $esProxima = $cartera->fecha_vencimiento >= $fecha->toDateString();
                if ($aCubrir >= $saldo - 0.01 && $esProxima) {
                    $descuentoLinea = $infoDescuento['descuento']->calcularDescuento($saldo);
                }
            }

            $plan[] = [
                'cartera'   => $cartera,
                'valor'     => $aCubrir,
                'descuento' => $descuentoLinea,
            ];

            $restante -= $aCubrir;
        }

        return $plan;
    }
}
