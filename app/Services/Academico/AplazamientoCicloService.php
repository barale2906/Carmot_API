<?php

namespace App\Services\Academico;

use App\Models\Academico\Aplazamiento;
use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Ciclo;
use App\Models\Financiero\Cartera\Cartera;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * AplazamientoCicloService
 *
 * Centraliza toda la lógica de aplazamiento de ciclos:
 *  - aplicar: mueve fecha_inicio del ciclo y recalcula todo
 *  - confirmar: cierra el aplazamiento sin mover fechas
 *  - ampliar: extiende a fecha posterior, genera aplazamiento hijo
 *  - interrumpir: cierra anticipadamente ajustando fechas hacia atrás
 *  - revertir: deshace el aplazamiento completamente
 */
class AplazamientoCicloService
{
    // -------------------------------------------------------------------------
    // aplicar
    // -------------------------------------------------------------------------

    /**
     * Aplaza el ciclo a la fecha probable de reinicio.
     * Mueve: fecha_inicio del ciclo, fechas de grupos (pivot), clases programadas
     * y opcionalmente la cartera. Registra el aplazamiento.
     *
     * @param Ciclo $ciclo
     * @param array $datos  Keys: tipo_aplazamiento_id, fecha_reinicio_probable, fecha_aplazamiento?, mover_cartera?, observaciones?
     * @return Aplazamiento
     */
    public function aplicar(Ciclo $ciclo, array $datos): Aplazamiento
    {
        $fechaInicioOriginal   = $ciclo->fecha_inicio->copy();
        $fechaReInicioProbable = Carbon::parse($datos['fecha_reinicio_probable']);
        $dias                  = (int) $fechaInicioOriginal->diffInDays($fechaReInicioProbable);

        // 1. Mover fecha_inicio (y fecha_fin si es manual)
        $ciclo->fecha_inicio = $fechaReInicioProbable;
        if (!$ciclo->fecha_fin_automatica && $ciclo->fecha_fin) {
            $ciclo->fecha_fin = $ciclo->fecha_fin->copy()->addDays($dias);
        }
        $ciclo->saveQuietly();

        // 2. Recalcular fechas de grupos en el pivot
        $this->recalcularFechas($ciclo);

        // 3. Mover clases programadas (solo las pendientes de dictar)
        $clasesMovidas = $this->moverClases($ciclo->id, $dias);

        // 4. Mover cartera si se solicita
        $carterasMov = 0;
        if ($datos['mover_cartera'] ?? false) {
            $nota = $this->notaCarteraAplazamiento($ciclo, $datos, $dias);
            $carterasMov = $this->moverCartera($ciclo->id, $dias, $nota);
        }

        return Aplazamiento::create([
            'ciclo_id'               => $ciclo->id,
            'tipo_aplazamiento_id'   => $datos['tipo_aplazamiento_id'],
            'user_id'                => Auth::id(),
            'aplazamiento_padre_id'  => null,
            'fecha_aplazamiento'     => $datos['fecha_aplazamiento'] ?? now()->toDateString(),
            'fecha_inicio_original'  => $fechaInicioOriginal,
            'fecha_reinicio_probable' => $fechaReInicioProbable,
            'dias_aplazamiento'      => $dias,
            'mover_cartera'          => $datos['mover_cartera'] ?? false,
            'clases_movidas'         => $clasesMovidas,
            'carteras_movidas'       => $carterasMov,
            'observaciones'          => $datos['observaciones'] ?? null,
            'estado'                 => Aplazamiento::getEstadoKey('Pendiente'),
        ]);
    }

    // -------------------------------------------------------------------------
    // confirmar
    // -------------------------------------------------------------------------

    /**
     * Confirma que el ciclo reinició en la fecha probable.
     * No mueve fechas; solo cierra el aplazamiento.
     *
     * @param Aplazamiento $aplazamiento
     * @param array        $datos  Keys: fecha_reinicio_real?, observaciones?
     * @return Aplazamiento
     */
    public function confirmar(Aplazamiento $aplazamiento, array $datos): Aplazamiento
    {
        $aplazamiento->update([
            'estado'             => Aplazamiento::getEstadoKey('Confirmado'),
            'fecha_reinicio_real' => $datos['fecha_reinicio_real'] ?? $aplazamiento->fecha_reinicio_probable->toDateString(),
            'dias_reales'        => $aplazamiento->dias_aplazamiento,
            'observaciones'      => $this->appendObs($aplazamiento->observaciones, $datos['observaciones'] ?? null),
        ]);

        return $aplazamiento->fresh();
    }

    // -------------------------------------------------------------------------
    // ampliar
    // -------------------------------------------------------------------------

    /**
     * Amplía el aplazamiento a una fecha posterior.
     * Marca el padre como Ampliado y crea un aplazamiento hijo.
     *
     * @param Aplazamiento $aplazamiento
     * @param array        $datos  Keys: fecha_reinicio_probable, tipo_aplazamiento_id?, mover_cartera?, observaciones?
     * @return Aplazamiento  el aplazamiento hijo recién creado
     */
    public function ampliar(Aplazamiento $aplazamiento, array $datos): Aplazamiento
    {
        $ciclo                = $aplazamiento->ciclo;
        $fechaBaseActual      = $ciclo->fecha_inicio->copy(); // ya está en fecha_reinicio_probable del padre
        $nuevaFechaReinicio   = Carbon::parse($datos['fecha_reinicio_probable']);
        $diasAdicionales      = (int) $fechaBaseActual->diffInDays($nuevaFechaReinicio);

        // Mover el ciclo y recalcular
        $ciclo->fecha_inicio = $nuevaFechaReinicio;
        if (!$ciclo->fecha_fin_automatica && $ciclo->fecha_fin) {
            $ciclo->fecha_fin = $ciclo->fecha_fin->copy()->addDays($diasAdicionales);
        }
        $ciclo->saveQuietly();
        $this->recalcularFechas($ciclo);

        // Clases programadas
        $clasesMovidas = $this->moverClases($ciclo->id, $diasAdicionales);

        // Cartera
        $carterasMov = 0;
        $moverCartera = $datos['mover_cartera'] ?? false;
        if ($moverCartera) {
            $nota = $this->notaCarteraAmpliar($ciclo, $aplazamiento, $diasAdicionales, $nuevaFechaReinicio);
            $carterasMov = $this->moverCartera($ciclo->id, $diasAdicionales, $nota);
        }

        // Marcar padre como Ampliado
        $aplazamiento->update([
            'estado'       => Aplazamiento::getEstadoKey('Ampliado'),
            'observaciones' => $this->appendObs($aplazamiento->observaciones, $datos['observaciones'] ?? null),
        ]);

        // Crear aplazamiento hijo
        $tipoId = $datos['tipo_aplazamiento_id'] ?? $aplazamiento->tipo_aplazamiento_id;

        return Aplazamiento::create([
            'ciclo_id'               => $ciclo->id,
            'tipo_aplazamiento_id'   => $tipoId,
            'user_id'                => Auth::id(),
            'aplazamiento_padre_id'  => $aplazamiento->id,
            'fecha_aplazamiento'     => now()->toDateString(),
            'fecha_inicio_original'  => $fechaBaseActual,
            'fecha_reinicio_probable' => $nuevaFechaReinicio,
            'dias_aplazamiento'      => $diasAdicionales,
            'mover_cartera'          => $moverCartera,
            'clases_movidas'         => $clasesMovidas,
            'carteras_movidas'       => $carterasMov,
            'observaciones'          => $datos['observaciones'] ?? null,
            'estado'                 => Aplazamiento::getEstadoKey('Pendiente'),
        ]);
    }

    // -------------------------------------------------------------------------
    // interrumpir
    // -------------------------------------------------------------------------

    /**
     * Cierra el aplazamiento anticipadamente: el ciclo reinició antes de lo previsto.
     * Ajusta fechas hacia atrás por la diferencia entre probable y real.
     *
     * @param Aplazamiento $aplazamiento
     * @param array        $datos  Keys: fecha_reinicio_real, observaciones?
     * @return Aplazamiento
     */
    public function interrumpir(Aplazamiento $aplazamiento, array $datos): Aplazamiento
    {
        $ciclo              = $aplazamiento->ciclo;
        $fechaReal          = Carbon::parse($datos['fecha_reinicio_real']);
        $diasAjuste         = (int) $fechaReal->diffInDays($aplazamiento->fecha_reinicio_probable); // días a restar
        $diasReales         = (int) $aplazamiento->fecha_inicio_original->diffInDays($fechaReal);

        // Mover ciclo hacia atrás
        $ciclo->fecha_inicio = $fechaReal;
        if (!$ciclo->fecha_fin_automatica && $ciclo->fecha_fin) {
            $ciclo->fecha_fin = $ciclo->fecha_fin->copy()->subDays($diasAjuste);
        }
        $ciclo->saveQuietly();
        $this->recalcularFechas($ciclo);

        // Clases programadas: mover hacia atrás
        $clasesMovidas = $this->moverClases($ciclo->id, -$diasAjuste);

        // Cartera: mover hacia atrás si aplica
        $carterasMov = 0;
        if ($aplazamiento->mover_cartera) {
            $nota = $this->notaCarteraInterrumpir($ciclo, $aplazamiento, $diasReales, $fechaReal);
            $carterasMov = $this->moverCartera($ciclo->id, -$diasAjuste, $nota);
        }

        $aplazamiento->update([
            'estado'             => Aplazamiento::getEstadoKey('Interrumpido'),
            'fecha_reinicio_real' => $fechaReal,
            'dias_reales'        => $diasReales,
            'clases_movidas'     => $aplazamiento->clases_movidas + abs($clasesMovidas),
            'carteras_movidas'   => $aplazamiento->carteras_movidas + abs($carterasMov),
            'observaciones'      => $this->appendObs($aplazamiento->observaciones, $datos['observaciones'] ?? null),
        ]);

        return $aplazamiento->fresh();
    }

    // -------------------------------------------------------------------------
    // revertir
    // -------------------------------------------------------------------------

    /**
     * Deshace completamente el aplazamiento: restaura todas las fechas al origen.
     *
     * @param Aplazamiento $aplazamiento
     * @param array        $datos  Keys: observaciones?
     * @return Aplazamiento
     */
    public function revertir(Aplazamiento $aplazamiento, array $datos = []): Aplazamiento
    {
        $ciclo = $aplazamiento->ciclo;
        $dias  = $aplazamiento->dias_aplazamiento;

        // Restaurar fecha_inicio
        $ciclo->fecha_inicio = $aplazamiento->fecha_inicio_original->copy();
        if (!$ciclo->fecha_fin_automatica && $ciclo->fecha_fin) {
            $ciclo->fecha_fin = $ciclo->fecha_fin->copy()->subDays($dias);
        }
        $ciclo->saveQuietly();
        $this->recalcularFechas($ciclo);

        // Clases programadas
        $this->moverClases($ciclo->id, -$dias);

        // Cartera
        if ($aplazamiento->mover_cartera) {
            $nota = $this->notaCarteraRevertir($ciclo, $aplazamiento);
            $this->moverCartera($ciclo->id, -$dias, $nota);
        }

        $aplazamiento->update([
            'estado'             => Aplazamiento::getEstadoKey('Revertido'),
            'fecha_reinicio_real' => null,
            'dias_reales'        => 0,
            'observaciones'      => $this->appendObs($aplazamiento->observaciones, $datos['observaciones'] ?? null),
        ]);

        return $aplazamiento->fresh();
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Recalcula fechas del ciclo automáticamente (pivot de grupos y fecha_fin).
     */
    private function recalcularFechas(Ciclo $ciclo): void
    {
        $ciclo->refresh();
        $fechaFin = $ciclo->calcularFechaFin();

        if ($ciclo->fecha_fin_automatica && $fechaFin) {
            $ciclo->fecha_fin    = $fechaFin;
            $ciclo->duracion_dias = (int) $ciclo->fecha_inicio->diffInDays($fechaFin);
            $ciclo->saveQuietly();
        }
    }

    /**
     * Mueve las clases programadas (solo estado 'programada') del ciclo.
     * Usa días positivos para adelantar, negativos para retroceder.
     *
     * @return int filas afectadas
     */
    private function moverClases(int $cicloId, int $dias): int
    {
        if ($dias === 0) {
            return 0;
        }

        $funcion = $dias > 0 ? 'DATE_ADD' : 'DATE_SUB';
        $absDias = abs($dias);

        return AsistenciaClaseProgramada::where('ciclo_id', $cicloId)
            ->where('estado', 'programada')
            ->whereNull('deleted_at')
            ->update([
                'fecha_clase' => DB::raw("{$funcion}(fecha_clase, INTERVAL {$absDias} DAY)"),
            ]);
    }

    /**
     * Mueve la fecha_vencimiento de las carteras elegibles del ciclo.
     * Solo mueve carteras Activas (0), Abonadas (1) y En Acuerdo (4).
     * Agrega una nota a observaciones de cada cartera afectada.
     *
     * @return int filas afectadas
     */
    private function moverCartera(int $cicloId, int $dias, string $nota): int
    {
        if ($dias === 0) {
            return 0;
        }

        $carteras = Cartera::whereHas('matricula', fn ($q) => $q->where('ciclo_id', $cicloId))
            ->whereIn('status', [
                Cartera::getStatusKey('Activa'),
                Cartera::getStatusKey('Abonada'),
                Cartera::getStatusKey('En Acuerdo'),
            ])
            ->get();

        foreach ($carteras as $cartera) {
            $nuevaFecha = $dias > 0
                ? $cartera->fecha_vencimiento->copy()->addDays($dias)
                : $cartera->fecha_vencimiento->copy()->subDays(abs($dias));

            $cartera->update([
                'fecha_vencimiento' => $nuevaFecha,
                'observaciones'     => $this->appendObs($cartera->observaciones, $nota),
            ]);
        }

        return $carteras->count();
    }

    /**
     * Concatena una nota a observaciones existentes con separador visual.
     */
    private function appendObs(?string $obs, ?string $nueva): ?string
    {
        if (!$nueva) {
            return $obs;
        }
        return $obs ? $obs . "\n---\n" . $nueva : $nueva;
    }

    // ---- Generadores de notas para cartera ----

    private function notaCarteraAplazamiento(Ciclo $ciclo, array $datos, int $dias): string
    {
        $fechaReinicio = Carbon::parse($datos['fecha_reinicio_probable'])->format('d/m/Y');
        return "[Aplazamiento] Ciclo '{$ciclo->nombre}' aplazado {$dias} días. "
            . "Nueva fecha de reinicio: {$fechaReinicio}.";
    }

    private function notaCarteraAmpliar(Ciclo $ciclo, Aplazamiento $padre, int $dias, Carbon $nuevaFecha): string
    {
        return "[Ampliación aplaz. #{$padre->id}] Ciclo '{$ciclo->nombre}' extendido {$dias} días adicionales. "
            . "Nueva fecha de reinicio: {$nuevaFecha->format('d/m/Y')}.";
    }

    private function notaCarteraInterrumpir(Ciclo $ciclo, Aplazamiento $aplaz, int $diasReales, Carbon $fechaReal): string
    {
        return "[Interrupción aplaz. #{$aplaz->id}] Ciclo '{$ciclo->nombre}' reinició anticipadamente el "
            . "{$fechaReal->format('d/m/Y')} ({$diasReales} días efectivos de aplazamiento).";
    }

    private function notaCarteraRevertir(Ciclo $ciclo, Aplazamiento $aplaz): string
    {
        return "[Reversión aplaz. #{$aplaz->id}] Ciclo '{$ciclo->nombre}' restaurado a su fecha de inicio original "
            . '(' . $aplaz->fecha_inicio_original->format('d/m/Y') . ').';
    }
}
