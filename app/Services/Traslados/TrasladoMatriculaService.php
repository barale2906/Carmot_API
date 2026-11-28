<?php

namespace App\Services\Traslados;

use App\Models\Academico\AsistenciaReposicion;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\Academico\Programacion;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Models\Traslados\MatriculaTraslado;
use App\Services\Financiero\MatriculaDescuentoService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Traslado de una matrícula a otra programación, curso o sede, conservando la matrícula para
 * poder hacerle seguimiento.
 *
 * El tipo de traslado se deduce comparando el origen con el destino:
 *  - programacion: misma sede y curso, otro ciclo/programación (jornada).
 *  - curso: misma sede, otro curso.
 *  - sede: otra sede, mismo curso.
 *  - sede_curso: otra sede y otro curso.
 *
 * `simular` calcula todo sin escribir; `ejecutar` repite el cálculo dentro de una transacción y
 * aplica el cambio: matrícula, cartera, reposiciones, descuentos e historial.
 */
class TrasladoMatriculaService
{
    public function __construct(
        private readonly TrasladoNivelacionService $nivelacion,
        private readonly TrasladoFinancieroService $financiero,
        private readonly MatriculaDescuentoService $descuentos,
    ) {}

    /**
     * Ciclos disponibles en la sede y el curso de destino (con su programación y jornada) y los
     * precios de lista con los que se puede matricular allí.
     *
     * @return array{ciclos: array<int, array<string, mixed>>, precios: array<int, array<string, mixed>>}
     */
    public function opciones(Matricula $matricula, int $sedeId, int $cursoId): array
    {
        $ciclos = Ciclo::query()
            ->where('sede_id', $sedeId)
            ->where('curso_id', $cursoId)
            ->where('status', 1)
            ->whereNull('notas_cerradas_at')
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', today()))
            ->where('id', '!=', $matricula->ciclo_id)
            ->with('grupos')
            ->orderBy('fecha_inicio')
            ->get();

        return [
            'ciclos' => $ciclos->map(fn (Ciclo $ciclo) => [
                'id' => $ciclo->id,
                'nombre' => $ciclo->nombre,
                'fecha_inicio' => $ciclo->fecha_inicio?->toDateString(),
                'fecha_fin' => $ciclo->fecha_fin?->toDateString(),
                'inscritos' => (int) $ciclo->inscritos,
                'jornadas' => $ciclo->grupos->pluck('jornada')->unique()->sort()->values()->all(),
                'programaciones' => $this->programacionesDelCiclo($ciclo)
                    ->map(fn (Programacion $p) => ['id' => $p->id, 'nombre' => $p->nombre, 'jornada' => $p->jornada])->values()->all(),
            ])->values()->all(),
            'precios' => $this->preciosDisponibles($sedeId, $cursoId)->with(['listaPrecio:id,nombre', 'producto:id,nombre'])->get()
                ->map(fn (LpPrecioProducto $lp) => [
                    'id' => $lp->id,
                    'lista' => $lp->listaPrecio?->nombre,
                    'producto' => $lp->producto?->nombre,
                    'matricula' => (float) $lp->matricula,
                    'numero_cuotas' => (int) ($lp->numero_cuotas ?? 0),
                    'precio_total' => $this->financiero->precioTotal($lp),
                ])->values()->all(),
        ];
    }

    /**
     * Precios de lista con los que se puede matricular en la sede y el curso: del curso, en una
     * lista académica vigente que aplica a la población de la sede.
     */
    public function preciosDisponibles(int $sedeId, int $cursoId): Builder
    {
        $poblacionId = Sede::whereKey($sedeId)->value('poblacion_id');

        return LpPrecioProducto::query()
            ->whereHas('listaPrecio', fn ($q) => $q->where('origen', 1)->vigentes(today())
                ->whereHas('poblaciones', fn ($p) => $p->where('poblacions.id', $poblacionId)))
            ->whereHas('producto.cursos', fn ($q) => $q->where('cursos.id', $cursoId));
    }

    /**
     * Programaciones activas de la sede y el curso que corresponden al ciclo: las que comparten
     * grupos con él; si ninguna los comparte, todas las de la sede y el curso.
     *
     * @return Collection<int, Programacion>
     */
    public function programacionesDelCiclo(Ciclo $ciclo): Collection
    {
        $programaciones = Programacion::query()
            ->where('sede_id', $ciclo->sede_id)
            ->where('curso_id', $ciclo->curso_id)
            ->where('status', 1)
            ->with('grupos:id')
            ->get();

        $gruposDelCiclo = $ciclo->grupos()->pluck('grupos.id');
        $vinculadas = $programaciones->filter(fn (Programacion $p) => $p->grupos->pluck('id')->intersect($gruposDelCiclo)->isNotEmpty());

        return $vinculadas->isNotEmpty() ? $vinculadas->values() : $programaciones;
    }

    /**
     * Calcula el traslado sin escribir nada: tipo, origen y destino, lo financiero y la nivelación.
     *
     * @param  array<string, mixed>  $destino  sede_id, curso_id, ciclo_id, programacion_id, lp_precio_producto_id, fecha, cuotas, condonar_costo
     * @return array<string, mixed>
     */
    public function simular(Matricula $matricula, array $destino): array
    {
        $contexto = $this->contexto($matricula, $destino);

        return [
            'tipo' => $contexto['tipo'],
            'tipo_texto' => MatriculaTraslado::TIPOS[$contexto['tipo']],
            'fecha' => $contexto['fecha']->toDateString(),
            'origen' => $this->describir($matricula->sede_id, $matricula->curso_id, $matricula->ciclo_id, $matricula->programacion_id),
            'destino' => $this->describir($destino['sede_id'], $destino['curso_id'], $destino['ciclo_id'], $destino['programacion_id'] ?? null),
            'financiero' => $this->resumenFinanciero($contexto['calculo']),
            'nivelacion' => $contexto['nivelacion'],
        ];
    }

    /**
     * Traslada la matrícula. Todo ocurre en una transacción: si algo falla no queda nada a medias.
     *
     * @param  array<string, mixed>  $destino  Igual que en simular()
     */
    public function ejecutar(Matricula $matricula, array $destino, string $motivo, int $usuarioId): MatriculaTraslado
    {
        return DB::transaction(function () use ($matricula, $destino, $motivo, $usuarioId) {
            $matricula = Matricula::lockForUpdate()->findOrFail($matricula->id);
            $contexto = $this->contexto($matricula, $destino);
            $calculo = $contexto['calculo'];
            $fecha = $contexto['fecha'];
            $sedeDestino = Sede::findOrFail($destino['sede_id']);

            $traslado = $matricula->traslados()->create([
                'tipo' => $contexto['tipo'],
                'fecha' => $fecha->toDateString(),
                'sede_origen_id' => $matricula->sede_id,
                'curso_origen_id' => $matricula->curso_id,
                'ciclo_origen_id' => $matricula->ciclo_id,
                'programacion_origen_id' => $matricula->programacion_id,
                'lp_origen_id' => $matricula->lp_precio_producto_id,
                'sede_destino_id' => $destino['sede_id'],
                'curso_destino_id' => $destino['curso_id'],
                'ciclo_destino_id' => $destino['ciclo_id'],
                'programacion_destino_id' => $destino['programacion_id'] ?? null,
                'lp_destino_id' => $calculo['regenera_cartera'] ? $destino['lp_precio_producto_id'] : $matricula->lp_precio_producto_id,
                'motivo' => $motivo,
                'usuario_id' => $usuarioId,
                'valor_pagado' => $calculo['valor_pagado'],
                'saldo_anulado' => $calculo['saldo_anulado'],
                'saldo_nuevo' => $calculo['saldo_nuevo'],
                'costo_traslado' => $calculo['costo_traslado'],
                'nivelacion' => $contexto['nivelacion'],
                'nivelacion_pendiente' => $contexto['nivelacion']['requiere_nivelacion'],
            ]);

            $cambios = [
                'sede_id' => $destino['sede_id'],
                'curso_id' => $destino['curso_id'],
                'ciclo_id' => $destino['ciclo_id'],
                'programacion_id' => $destino['programacion_id'] ?? null,
            ];
            if ($calculo['regenera_cartera']) {
                $lp = LpPrecioProducto::findOrFail($destino['lp_precio_producto_id']);
                $cambios += ['lp_precio_producto_id' => $lp->id, 'monto' => $calculo['monto'], 'valor_cuota' => $lp->valor_cuota];
            }
            // El update dispara los eventos de la matrícula: mueve los inscritos del ciclo y el vínculo curso-estudiante.
            $matricula->update($cambios);

            $carteras = $this->financiero->aplicar($matricula, $sedeDestino, $fecha, $calculo, $traslado->id);
            $reposiciones = $this->cancelarReposicionesPendientes($matricula, $traslado->ciclo_origen_id, $fecha);

            if (! empty($destino['recalcular_descuentos'])) {
                $this->descuentos->congelar($matricula->load('lpPrecioProducto'));
            }

            $traslado->update(['carteras' => $carteras, 'reposiciones_canceladas' => $reposiciones]);

            return $traslado->load(['usuario', 'sedeOrigen', 'sedeDestino', 'cursoOrigen', 'cursoDestino', 'cicloOrigen', 'cicloDestino', 'programacionOrigen', 'programacionDestino']);
        });
    }

    /**
     * Marca como resuelta la nivelación pendiente de un traslado.
     */
    public function resolverNivelacion(MatriculaTraslado $traslado, int $usuarioId, ?string $observaciones): MatriculaTraslado
    {
        $traslado->update([
            'nivelacion_pendiente' => false,
            'nivelacion_resuelta_at' => now(),
            'nivelacion_resuelta_por_id' => $usuarioId,
            'nivelacion_observaciones' => $observaciones,
        ]);

        return $traslado;
    }

    /**
     * Lo que depende solo de la matrícula y el destino: tipo, fecha, cálculo financiero y nivelación.
     *
     * @param  array<string, mixed>  $destino
     * @return array{tipo: string, fecha: Carbon, calculo: array<string, mixed>, nivelacion: array<string, mixed>}
     */
    private function contexto(Matricula $matricula, array $destino): array
    {
        $cambiaSede = (int) $destino['sede_id'] !== (int) $matricula->sede_id;
        $cambiaCurso = (int) $destino['curso_id'] !== (int) $matricula->curso_id;
        $tipo = MatriculaTraslado::deducirTipo($cambiaSede, $cambiaCurso);
        $fecha = Carbon::parse($destino['fecha'] ?? today());

        $lp = ! empty($destino['lp_precio_producto_id']) ? LpPrecioProducto::find($destino['lp_precio_producto_id']) : null;
        $calculo = $this->financiero->calcular(
            $matricula,
            $fecha,
            $cambiaSede || $cambiaCurso,
            $lp,
            $destino['cuotas'] ?? null,
            (bool) ($destino['condonar_costo'] ?? false)
        );

        $nivelacion = $cambiaCurso
            ? $this->nivelacion->noAplica()
            : $this->nivelacion->evaluar($matricula->ciclo, Ciclo::findOrFail($destino['ciclo_id']), $fecha);

        return compact('tipo', 'fecha', 'calculo', 'nivelacion');
    }

    /**
     * Cancela las reposiciones del estudiante que estaban pendientes en el ciclo de origen, porque
     * ya no cursa allí.
     */
    private function cancelarReposicionesPendientes(Matricula $matricula, int $cicloOrigenId, Carbon $fecha): int
    {
        $reposiciones = AsistenciaReposicion::where('estudiante_id', $matricula->estudiante_id)
            ->whereHas('claseOrigen', fn ($q) => $q->where('ciclo_id', $cicloOrigenId)->whereDate('fecha_clase', '>=', $fecha->toDateString()))
            ->get();

        $reposiciones->each->delete();

        return $reposiciones->count();
    }

    /**
     * Nombres de la ubicación académica de una matrícula, para mostrar origen y destino.
     *
     * @return array<string, array{id: int|null, nombre: string|null}>
     */
    private function describir(int $sedeId, int $cursoId, int $cicloId, ?int $programacionId): array
    {
        return [
            'sede' => ['id' => $sedeId, 'nombre' => Sede::whereKey($sedeId)->value('nombre')],
            'curso' => ['id' => $cursoId, 'nombre' => Curso::whereKey($cursoId)->value('nombre')],
            'ciclo' => ['id' => $cicloId, 'nombre' => Ciclo::whereKey($cicloId)->value('nombre')],
            'programacion' => ['id' => $programacionId, 'nombre' => $programacionId ? Programacion::whereKey($programacionId)->value('nombre') : null],
        ];
    }

    /**
     * Lo financiero del cálculo en forma de respuesta: sin modelos, solo datos.
     *
     * @param  array<string, mixed>  $calculo
     * @return array<string, mixed>
     */
    private function resumenFinanciero(array $calculo): array
    {
        return [
            'regenera_cartera' => $calculo['regenera_cartera'],
            'valor_pagado' => $calculo['valor_pagado'],
            'precio_destino' => $calculo['precio_destino'],
            'saldo_anulado' => $calculo['saldo_anulado'],
            'mora_pendiente' => $calculo['mora_pendiente'],
            'saldo_nuevo' => $calculo['saldo_nuevo'],
            'monto' => $calculo['monto'],
            'tarifa_traslado' => $calculo['tarifa_traslado'],
            'costo_traslado' => $calculo['costo_traslado'],
            'carteras_a_cancelar' => $calculo['carteras_pendientes']->map(fn ($c) => [
                'id' => $c->id,
                'numero_cuota' => $c->numero_cuota,
                'concepto' => $c->conceptoNombre(),
                'saldo' => (float) $c->saldo,
                'fecha_vencimiento' => $c->fecha_vencimiento->toDateString(),
            ])->values()->all(),
            'cuotas_nuevas' => $calculo['cuotas'],
        ];
    }
}
