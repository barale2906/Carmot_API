<?php

namespace App\Services\Financiero;

use App\Models\Academico\Matricula;
use App\Models\Academico\Programacion;
use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\Lp\LpPrecioProducto;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * MatriculaDescuentoService
 *
 * Resuelve qué descuentos de matrícula se activan automáticamente según la sede, el curso
 * y la jornada (la de la programación elegida), los congela en la matrícula y arma la
 * vista previa que se muestra al matricular.
 *
 * Reglas:
 *  - Solo descuentos vigentes, de origen académico, con tipo_activacion = promocion_matricula
 *    y aplicacion = matricula (siempre recaen sobre la cuota 0).
 *  - El alcance (sedes/poblaciones, cursos, jornadas) se combina con AND; una dimensión sin
 *    restricción aplica a todo (ver Descuento::scopeConAlcance).
 *  - Acumulación: los descuentos con permite_acumulacion se suman entre sí (de forma secuencial
 *    sobre el saldo que va quedando). Un descuento no acumulable es exclusivo: se aplica solo y
 *    únicamente si supera a lo que darían todos los acumulables juntos.
 *  - Al matricular el resultado se congela en matricula_descuento (tipo y valor incluidos).
 */
class MatriculaDescuentoService
{
    /**
     * Descuentos de matrícula que aplican al contexto dado, ya resueltas las reglas de acumulación.
     *
     * @param  int|null  $jornada  jornada de la programación elegida (null si no hay)
     * @param  float  $baseMatricula  valor de la cuota 0, para comparar descuentos exclusivos
     * @param  Carbon|null  $fecha  fecha de la matrícula (default: hoy)
     * @return Collection<int, Descuento>
     */
    public function resolver(int $sedeId, int $cursoId, ?int $jornada, float $baseMatricula, ?Carbon $fecha = null): Collection
    {
        $candidatos = Descuento::vigentes($fecha ?? Carbon::today())
            ->where('origen', 1)
            ->descuentos()
            ->where('tipo_activacion', Descuento::ACTIVACION_PROMOCION_MATRICULA)
            ->where('aplicacion', Descuento::APLICACION_MATRICULA)
            ->conAlcance($sedeId, $cursoId, $jornada)
            ->orderBy('id')
            ->get();

        $acumulables = $candidatos->where('permite_acumulacion', true)->values();
        $mejorExclusivo = $candidatos->where('permite_acumulacion', false)
            ->sortByDesc(fn (Descuento $d) => $d->calcularDescuento($baseMatricula))
            ->first();

        if (! $mejorExclusivo) {
            return $acumulables;
        }

        $totalAcumulables = $this->aplicarSecuencial($acumulables, $baseMatricula)['total'];

        return $acumulables->isEmpty() || $mejorExclusivo->calcularDescuento($baseMatricula) > $totalAcumulables
            ? collect([$mejorExclusivo])
            : $acumulables;
    }

    /**
     * Aplica los descuentos uno tras otro, cada uno sobre lo que queda tras el anterior.
     *
     * @param  iterable<Descuento>  $descuentos
     * @param  float  $base  valor sobre el que se descuenta
     * @return array{detalle: array<int, array{descuento: Descuento, valor: float}>, total: float}
     */
    public function aplicarSecuencial(iterable $descuentos, float $base): array
    {
        $detalle = [];
        $restante = $base;

        foreach ($descuentos as $descuento) {
            $valor = round($descuento->calcularDescuento($restante), 2);
            if ($valor <= 0) {
                continue;
            }
            $detalle[] = ['descuento' => $descuento, 'valor' => $valor];
            $restante -= $valor;
        }

        return ['detalle' => $detalle, 'total' => round($base - $restante, 2)];
    }

    /**
     * Descuentos congelados en la matrícula, con el tipo y valor que tenían al matricular
     * (los atributos se sobrescriben en memoria; nunca se guardan).
     *
     * @return Collection<int, Descuento>
     */
    public function congelados(Matricula $matricula): Collection
    {
        return $matricula->descuentosCongelados()
            ->orderBy('descuentos.id')
            ->get()
            ->each(function (Descuento $descuento) {
                $descuento->tipo = $descuento->pivot->tipo;
                $descuento->valor = $descuento->pivot->valor;
                $descuento->permite_acumulacion = (bool) $descuento->pivot->permite_acumulacion;
            });
    }

    /**
     * Resuelve y congela los descuentos de la matrícula, reemplazando los anteriores.
     *
     * @param  Matricula  $matricula  debe tener sede, curso y (opcional) programación y precio de lista
     */
    public function congelar(Matricula $matricula): void
    {
        $descuentos = $this->resolver(
            (int) $matricula->sede_id,
            (int) $matricula->curso_id,
            $this->jornadaDeProgramacion($matricula->programacion_id),
            $this->baseMatricula($matricula->lpPrecioProducto),
            Carbon::parse($matricula->fecha_matricula),
        );

        $matricula->descuentosCongelados()->sync(
            $descuentos->mapWithKeys(fn (Descuento $d) => [$d->id => [
                'aplicacion' => $d->aplicacion,
                'tipo' => $d->tipo,
                'valor' => $d->valor,
                'permite_acumulacion' => $d->permite_acumulacion,
            ]])->all()
        );
    }

    /**
     * Vista previa para la pantalla de matrícula: total del curso, descuentos que se activan
     * y detalle de cada uno. Además lista los descuentos por pronto pago del mismo alcance,
     * que se aplican después, al pagar las cuotas.
     *
     * @return array<string, mixed>
     */
    public function previsualizar(int $sedeId, int $cursoId, ?int $programacionId, ?int $lpPrecioProductoId, ?Carbon $fecha = null): array
    {
        $fecha = $fecha ?? Carbon::today();
        $lp = $lpPrecioProductoId ? LpPrecioProducto::find($lpPrecioProductoId) : null;
        $jornada = $this->jornadaDeProgramacion($programacionId);

        $totalCurso = (float) ($lp?->precio_contado ?? 0);
        $baseMatricula = $this->baseMatricula($lp);

        $aplicados = $this->aplicarSecuencial(
            $this->resolver($sedeId, $cursoId, $jornada, $baseMatricula, $fecha),
            $baseMatricula
        );

        $pagoAnticipado = Descuento::vigentes($fecha)
            ->where('origen', 1)
            ->descuentos()
            ->where('tipo_activacion', Descuento::ACTIVACION_PAGO_ANTICIPADO)
            ->where('aplicacion', Descuento::APLICACION_CUOTA)
            ->conAlcance($sedeId, $cursoId, $jornada)
            ->orderBy('id')
            ->get();

        return [
            'total_curso' => $totalCurso,
            'valor_matricula' => $baseMatricula,
            'jornada' => $jornada,
            'descuentos' => array_map(fn (array $linea) => [
                'id' => $linea['descuento']->id,
                'nombre' => $linea['descuento']->nombre,
                'descripcion' => $linea['descuento']->descripcion,
                'tipo' => $linea['descuento']->tipo,
                'valor' => (float) $linea['descuento']->valor,
                'aplicacion' => $linea['descuento']->aplicacion,
                'permite_acumulacion' => (bool) $linea['descuento']->permite_acumulacion,
                'valor_descuento' => $linea['valor'],
            ], $aplicados['detalle']),
            'total_descuento' => $aplicados['total'],
            'valor_matricula_con_descuento' => round($baseMatricula - $aplicados['total'], 2),
            'total_curso_con_descuento' => round($totalCurso - $aplicados['total'], 2),
            'descuentos_pago_anticipado' => $pagoAnticipado->map(fn (Descuento $d) => [
                'id' => $d->id,
                'nombre' => $d->nombre,
                'tipo' => $d->tipo,
                'valor' => (float) $d->valor,
                'dias_anticipacion' => $d->dias_anticipacion,
            ])->values()->all(),
        ];
    }

    /**
     * Jornada (0-4) de la programación elegida, o null si no hay programación.
     */
    public function jornadaDeProgramacion(?int $programacionId): ?int
    {
        return $programacionId
            ? Programacion::whereKey($programacionId)->value('jornada')
            : null;
    }

    /**
     * Valor de la cuota 0: la matrícula si el plan es a cuotas; el precio completo si es de contado.
     */
    private function baseMatricula(?LpPrecioProducto $lp): float
    {
        if (! $lp) {
            return 0.0;
        }

        return (int) ($lp->numero_cuotas ?? 0) === 0
            ? (float) $lp->precio_contado
            : (float) $lp->matricula;
    }
}
