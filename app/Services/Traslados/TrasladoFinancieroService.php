<?php

namespace App\Services\Traslados;

use App\Models\Academico\Matricula;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\ConceptoPago\ConceptoPago;
use App\Models\Financiero\Lp\LpPrecioProducto;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Parte financiera del traslado de una matrícula.
 *
 * Al cambiar de curso o de sede no hay devoluciones: lo ya pagado se queda en la sede de origen y
 * no se traslada. Lo pendiente en origen se cancela y se genera cartera nueva en la sede de
 * destino por lo que falta hasta el precio del destino (`precio destino − pagado`). Si el destino
 * vale más, ese excedente es lo que debe pagar el estudiante; si vale igual o menos, no se
 * devuelve nada. El plan de cuotas propuesto se puede reemplazar por uno acordado con el estudiante.
 *
 * Además, el concepto de pago "Traslado" define una tarifa por el traslado (0 = no se cobra).
 */
class TrasladoFinancieroService
{
    /**
     * Calcula lo que haría el traslado sin escribir nada.
     *
     * @param  bool  $regeneraCartera  El traslado cambia la sede o el curso (y por eso la cartera).
     * @param  array<int, array{valor: float|int|string, fecha_vencimiento: string}>|null  $cuotasAcordadas
     * @return array<string, mixed>
     */
    public function calcular(
        Matricula $matricula,
        Carbon $fecha,
        bool $regeneraCartera,
        ?LpPrecioProducto $lpDestino,
        ?array $cuotasAcordadas,
        bool $condonarCosto
    ): array {
        $tarifa = (float) (ConceptoPago::porNombre(ConceptoPago::TRASLADO)?->valor ?? 0);
        $costo = $condonarCosto ? 0.0 : $tarifa;
        $regenera = $regeneraCartera && $lpDestino !== null;

        $resultado = [
            'regenera_cartera' => $regenera,
            'valor_pagado' => 0.0,
            'saldo_anulado' => 0.0,
            'mora_pendiente' => 0.0,
            'precio_destino' => null,
            'saldo_nuevo' => 0.0,
            'monto' => null,
            'tarifa_traslado' => $tarifa,
            'costo_traslado' => $costo,
            'carteras_pendientes' => collect(),
            'cuotas' => [],
        ];

        if (! $regenera) {
            return $resultado;
        }

        $carteras = $this->carterasDeLaMatricula($matricula);
        $pendientes = $carteras->filter(fn (Cartera $c) => in_array($c->status, $this->estadosPendientes(), true))
            ->sortBy('numero_cuota')->values();

        $pagado = round((float) $carteras->sum(fn (Cartera $c) => (float) $c->abono + (float) $c->descuento), 2);
        $precioDestino = $this->precioTotal($lpDestino);
        $saldoNuevo = max(0.0, round($precioDestino - $pagado, 2));

        $cuotas = $cuotasAcordadas !== null
            ? $this->asignarNumeros($this->normalizarAcordadas($cuotasAcordadas), $pendientes, $carteras)
            : $this->asignarNumeros($this->proponerCuotas($pendientes, $saldoNuevo, $fecha), $pendientes, $carteras);

        return array_merge($resultado, [
            'valor_pagado' => $pagado,
            'saldo_anulado' => round((float) $pendientes->sum('saldo'), 2),
            'mora_pendiente' => round((float) $pendientes->sum('mora_acumulada'), 2),
            'precio_destino' => $precioDestino,
            'saldo_nuevo' => round(array_sum(array_column($cuotas, 'valor')), 2),
            'monto' => round($pagado + array_sum(array_column($cuotas, 'valor')), 2),
            'carteras_pendientes' => $pendientes,
            'cuotas' => $cuotas,
        ]);
    }

    /**
     * Aplica el cálculo: cancela lo pendiente en la sede de origen y genera la cartera nueva en la
     * de destino, más el cobro del traslado si lo hay. Debe llamarse dentro de una transacción.
     *
     * @param  array<string, mixed>  $calculo  Resultado de calcular()
     * @return array{anuladas: array<int, int>, cerradas: array<int, int>, creadas: array<int, int>}
     */
    public function aplicar(Matricula $matricula, Sede $sedeDestino, Carbon $fecha, array $calculo, int $trasladoId): array
    {
        $nota = "Traslado #{$trasladoId} a la sede {$sedeDestino->nombre}.";
        $ids = ['anuladas' => [], 'cerradas' => [], 'creadas' => []];

        foreach ($calculo['carteras_pendientes'] as $cartera) {
            $pagado = (float) $cartera->abono + (float) $cartera->descuento;
            $observaciones = trim(($cartera->observaciones ? $cartera->observaciones."\n---\n" : '').$nota);

            if ($pagado > 0) {
                // Cuota con abonos: lo pagado se queda en la sede de origen y el resto se cancela.
                $cartera->update(['valor' => $pagado, 'saldo' => 0, 'status' => Cartera::getStatusKey('Cerrada'), 'observaciones' => $observaciones]);
                $ids['cerradas'][] = $cartera->id;
            } else {
                $cartera->update(['status' => Cartera::getStatusKey('Anulada'), 'observaciones' => $observaciones]);
                $ids['anuladas'][] = $cartera->id;
            }
        }

        foreach ($calculo['cuotas'] as $cuota) {
            $ids['creadas'][] = $this->crearCartera($matricula, $sedeDestino, $cuota['numero_cuota'], $cuota['valor'], $cuota['fecha_vencimiento'], null, $nota)->id;
        }

        if ($calculo['costo_traslado'] > 0) {
            $ids['creadas'][] = $this->crearCartera($matricula, $sedeDestino, 0, $calculo['costo_traslado'], $fecha->toDateString(), ConceptoPago::TRASLADO, $nota)->id;
        }

        return $ids;
    }

    /**
     * Precio total que paga el estudiante por el curso del precio de lista: de contado, o la
     * matrícula más todas las cuotas si es financiado.
     */
    public function precioTotal(LpPrecioProducto $lp): float
    {
        $cuotas = (int) ($lp->numero_cuotas ?? 0);

        if ($cuotas === 0) {
            return round((float) $lp->precio_contado, 2);
        }

        $total = (float) $lp->matricula;
        for ($n = 1; $n <= $cuotas; $n++) {
            $total += $lp->valorDeCuota($n);
        }

        return round($total, 2);
    }

    /**
     * Carteras de la matrícula sin contar las anuladas ni los cobros de traslados anteriores.
     *
     * @return Collection<int, Cartera>
     */
    private function carterasDeLaMatricula(Matricula $matricula): Collection
    {
        return $matricula->carteras()
            ->where('status', '!=', Cartera::getStatusKey('Anulada'))
            ->where(fn ($q) => $q->whereNull('concepto')->orWhere('concepto', '!=', ConceptoPago::TRASLADO))
            ->get();
    }

    /**
     * @return array<int, int>
     */
    private function estadosPendientes(): array
    {
        return [Cartera::getStatusKey('Activa'), Cartera::getStatusKey('Abonada')];
    }

    /**
     * Reparte el saldo nuevo en tantas cuotas como pendientes había (mínimo una), en partes iguales
     * y conservando los vencimientos; los vencimientos ya pasados se mueven a la fecha del traslado.
     *
     * @param  Collection<int, Cartera>  $pendientes
     * @return array<int, array{valor: float, fecha_vencimiento: string}>
     */
    private function proponerCuotas(Collection $pendientes, float $saldoNuevo, Carbon $fecha): array
    {
        if ($saldoNuevo <= 0) {
            return [];
        }

        $cantidad = max(1, $pendientes->count());
        $base = round($saldoNuevo / $cantidad, 2);
        $cuotas = [];
        $anterior = null;

        for ($i = 0; $i < $cantidad; $i++) {
            $original = $pendientes->get($i)?->fecha_vencimiento;
            $vencimiento = $original
                ? ($original->lt($fecha) ? $fecha->copy() : $original->copy())
                : ($anterior ? $anterior->copy()->addMonthNoOverflow() : $fecha->copy());

            $cuotas[] = [
                'valor' => $i < $cantidad - 1 ? $base : round($saldoNuevo - $base * ($cantidad - 1), 2),
                'fecha_vencimiento' => $vencimiento->toDateString(),
            ];
            $anterior = $vencimiento;
        }

        return $cuotas;
    }

    /**
     * @param  array<int, array{valor: float|int|string, fecha_vencimiento: string}>  $cuotas
     * @return array<int, array{valor: float, fecha_vencimiento: string}>
     */
    private function normalizarAcordadas(array $cuotas): array
    {
        return array_map(fn (array $c) => [
            'valor' => round((float) $c['valor'], 2),
            'fecha_vencimiento' => Carbon::parse($c['fecha_vencimiento'])->toDateString(),
        ], array_values($cuotas));
    }

    /**
     * Numera las cuotas nuevas: reutilizan los números de las pendientes que se cancelan por
     * completo y, si hay más (o si la pendiente conserva lo abonado y por eso sigue existiendo),
     * siguen desde la última cuota de la matrícula.
     *
     * @param  array<int, array{valor: float, fecha_vencimiento: string}>  $cuotas
     * @param  Collection<int, Cartera>  $pendientes
     * @param  Collection<int, Cartera>  $carteras
     * @return array<int, array{numero_cuota: int, valor: float, fecha_vencimiento: string}>
     */
    private function asignarNumeros(array $cuotas, Collection $pendientes, Collection $carteras): array
    {
        $reutilizables = $pendientes
            ->filter(fn (Cartera $c) => (float) $c->abono + (float) $c->descuento <= 0)
            ->pluck('numero_cuota')->map(fn ($n) => (int) $n)->values();
        $siguiente = (int) $carteras->max('numero_cuota') + 1;

        return array_map(function (array $cuota, int $i) use ($reutilizables, &$siguiente) {
            return ['numero_cuota' => $reutilizables->has($i) ? $reutilizables[$i] : $siguiente++] + $cuota;
        }, $cuotas, array_keys($cuotas));
    }

    /**
     * Crea un cargo de cartera en la sede de destino.
     */
    private function crearCartera(Matricula $matricula, Sede $sede, int $numeroCuota, float $valor, string $vencimiento, ?string $concepto, string $nota): Cartera
    {
        return Cartera::create([
            'matricula_id' => $matricula->id,
            'sede_id' => $sede->id,
            'estudiante_id' => $matricula->estudiante_id,
            'numero_cuota' => $numeroCuota,
            'concepto' => $concepto,
            'valor' => $valor,
            'saldo' => $valor,
            'abono' => 0,
            'descuento' => 0,
            'fecha_vencimiento' => $vencimiento,
            'status' => Cartera::getStatusKey('Activa'),
            'observaciones' => $nota,
        ]);
    }
}
