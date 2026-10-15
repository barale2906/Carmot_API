<?php

namespace App\Services\Financiero;

use App\Models\Academico\Matricula;
use App\Models\Financiero\Cartera\Cartera;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

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
     * Cuotas de costo cero: una cuota cuyo costo efectivo es 0 (valor 0, o descuento que
     * cubre el 100 % del saldo) se incluye siempre en el plan con valor = 0 para que quede
     * Cerrada, sin importar el orden ni si el monto ya se agotó. Por eso $monto puede ser 0:
     * en ese caso el plan contiene únicamente las cuotas de costo cero.
     *
     * @param  Matricula   $matricula
     * @param  float       $monto            total a distribuir (0 = solo descargar cuotas de costo cero)
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

        $carteras = $this->carterasPendientes($matricula);

        if ($carteras->isEmpty()) {
            if ($monto <= 0.01) {
                return [];
            }
            throw new \InvalidArgumentException('La matrícula no tiene cuotas pendientes de pago.');
        }

        $totalSaldo = $carteras->sum('saldo');

        if ($monto > $totalSaldo + 0.01) {
            throw new \InvalidArgumentException(
                "El monto ({$monto}) supera el saldo total pendiente ({$totalSaldo})."
            );
        }

        [$plan, $restante] = $this->planificar($matricula, $carteras, $monto, $aplicarDescuento, $fecha);

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

    /**
     * Reparte el monto entre las cuotas pendientes (de la más antigua a la más reciente),
     * aplicando los descuentos que correspondan. Devuelve el plan y el monto que sobró.
     *
     * @param  Matricula               $matricula
     * @param  Collection<int, Cartera> $carteras
     * @param  float                   $monto
     * @param  bool                    $aplicarDescuento
     * @param  Carbon                  $fecha
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private function planificar(Matricula $matricula, Collection $carteras, float $monto, bool $aplicarDescuento, Carbon $fecha): array
    {
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

        // No se corta al agotar el monto: las cuotas posteriores de costo cero también se descargan.
        foreach ($carteras as $cartera) {
            $saldo         = (float) $cartera->saldo;
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
            $esCostoCero   = $efectivaSaldo <= 0.01;

            // Saltar si no hay pago que aplicar, salvo que la cuota se descargue por costo cero
            if ($aCubrir <= 0 && ! $esCostoCero) {
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

        return [$plan, $restante];
    }

    /**
     * Cuotas pendientes de la matrícula, de la más antigua a la más reciente.
     *
     * @param  Matricula $matricula
     * @return Collection<int, Cartera>
     */
    private function carterasPendientes(Matricula $matricula): Collection
    {
        return $matricula->carteras()
            ->pendientes()
            ->orderBy('fecha_vencimiento')
            ->orderBy('numero_cuota')
            ->get();
    }

    /**
     * Previsualiza, sin persistir, cómo se repartiría un pago entre las cuotas: lo mismo que
     * aplicará el recibo. Si el monto supera el máximo, devuelve el mensaje en vez de las líneas.
     *
     * @param  Matricula $matricula
     * @param  float     $monto
     * @param  bool      $aplicarDescuento
     * @param  Carbon    $fecha
     * @return array{lineas: array<int, array{cartera_id: int, numero_cuota: int, saldo: float, valor: float, descuento: float, cierra: bool}>|null, error: string|null}
     */
    public function previsualizar(Matricula $matricula, float $monto, bool $aplicarDescuento, Carbon $fecha): array
    {
        try {
            $plan = $this->distribuir($matricula, $monto, $aplicarDescuento, $fecha);
        } catch (\InvalidArgumentException $e) {
            return ['lineas' => null, 'error' => $e->getMessage()];
        }

        $lineas = array_map(fn (array $linea) => [
            'cartera_id'   => $linea['cartera']->id,
            'numero_cuota' => (int) $linea['cartera']->numero_cuota,
            'saldo'        => (float) $linea['cartera']->saldo,
            'valor'        => round((float) $linea['valor'], 2),
            'descuento'    => round((float) $linea['descuento'], 2),
            'cierra'       => $linea['valor'] + $linea['descuento'] >= (float) $linea['cartera']->saldo - 0.01,
        ], $plan);

        return ['lineas' => $lineas, 'error' => null];
    }

    /**
     * Mínimo a pagar a la fecha: el costo efectivo (con los descuentos que apliquen) de las
     * cuotas vencidas o que vencen ese día. Antes el formulario sugería el saldo bruto (F09).
     *
     * @param  Matricula $matricula
     * @param  bool      $aplicarDescuento
     * @param  Carbon    $fecha
     * @return float
     */
    public function minimoAPagar(Matricula $matricula, bool $aplicarDescuento, Carbon $fecha): float
    {
        $vencidas = $matricula->carteras()->pendientes()
            ->whereDate('fecha_vencimiento', '<=', $fecha->toDateString())
            ->pluck('id');

        $saldoVencido = (float) $matricula->carteras()->whereIn('id', $vencidas)->sum('saldo');
        if ($saldoVencido <= 0) {
            return 0.0;
        }

        $lineas = $this->previsualizar($matricula, $saldoVencido, $aplicarDescuento, $fecha)['lineas'] ?? [];

        return round((float) collect($lineas)->whereIn('cartera_id', $vencidas->all())->sum('valor'), 2);
    }

    /**
     * Máximo a pagar a la fecha: el costo efectivo de todas las cuotas pendientes con los
     * descuentos que apliquen al pagarlas completas. Es el tope que valida `distribuir` (N12).
     *
     * @param  Matricula $matricula
     * @param  bool      $aplicarDescuento
     * @param  Carbon    $fecha
     * @return float
     */
    public function maximoAPagar(Matricula $matricula, bool $aplicarDescuento, Carbon $fecha): float
    {
        $carteras = $this->carterasPendientes($matricula);

        $totalSaldo = (float) $carteras->sum('saldo');
        if ($totalSaldo <= 0) {
            return 0.0;
        }

        [, $restante] = $this->planificar($matricula, $carteras, $totalSaldo, $aplicarDescuento, $fecha);

        return round($totalSaldo - $restante, 2);
    }
}
