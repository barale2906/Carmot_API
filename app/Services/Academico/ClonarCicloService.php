<?php

namespace App\Services\Academico;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Grupo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Clona un ciclo (programación) reutilizando su configuración y sus grupos en el
 * mismo orden. Solo se requiere la nueva fecha de inicio: las fechas por grupo,
 * la fecha de fin y el ajuste de duración los calcula CalendarioGrupoService.
 *
 * El clon no puede solaparse con otro ciclo programado en ninguno de sus grupos:
 * si algún grupo queda compartiendo una ejecución o sus fechas se cruzan con las
 * de otro ciclo, se revierte la creación y se informan los conflictos.
 *
 * El clon siempre inicia después de que termina el ciclo origen y en un día de
 * clase (sesión laborable del primer grupo), para conservar los días en que se
 * dictan las clases. La fecha sugerida es el primer día de clase tras esa terminación.
 */
class ClonarCicloService
{
    public function __construct(
        private readonly CalendarioGrupoService $calendario,
        private readonly CalendarioLaboralService $calendarioLaboral,
    ) {
    }

    /**
     * Última fecha de dictado del ciclo: la mayor entre su fecha_fin y la
     * fecha_fin_grupo de sus grupos. Null si el ciclo no tiene fechas calculadas.
     *
     * @param Ciclo $ciclo
     * @return Carbon|null
     */
    public function fechaTerminacion(Ciclo $ciclo): ?Carbon
    {
        $finGrupos = $ciclo->grupos()->max('ciclo_grupo.fecha_fin_grupo');

        $fechas = array_filter([
            $ciclo->fecha_fin,
            $finGrupos ? Carbon::parse($finGrupos) : null,
        ]);

        return $fechas ? max($fechas)->copy()->startOfDay() : null;
    }

    /**
     * Fecha mínima y sugerida para clonar el ciclo.
     *
     * - fecha_minima: el día siguiente a la terminación del origen (o hoy, si es posterior).
     * - fecha_inicio_sugerida: primer día de clase (ver esDiaDeClase) desde la fecha mínima.
     *
     * @param Ciclo $origen
     * @return array{fecha_terminacion_origen: string, fecha_minima: string, fecha_inicio_sugerida: string, dias_clase: array<int, string>}|null
     *         Null si el origen no tiene fechas calculadas.
     */
    public function sugerirFechaInicio(Ciclo $origen): ?array
    {
        $terminacion = $this->fechaTerminacion($origen);

        if (! $terminacion) {
            return null;
        }

        $minima = max($terminacion->copy()->addDay(), now()->startOfDay());

        return [
            'fecha_terminacion_origen' => $terminacion->toDateString(),
            'fecha_minima'             => $minima->toDateString(),
            'fecha_inicio_sugerida'    => $this->primerDiaDeClase($origen, $minima)->toDateString(),
            'dias_clase'               => $this->diasClase($origen),
        ];
    }

    /**
     * Indica si la fecha es un día de clase del ciclo: una sesión laborable del
     * primer grupo (por orden) que tiene horarios. Si ningún grupo tiene horarios,
     * basta con que sea el mismo día de la semana en que inició el origen.
     *
     * @param Ciclo  $origen
     * @param Carbon $fecha
     * @return bool
     */
    public function esDiaDeClase(Ciclo $origen, Carbon $fecha): bool
    {
        return $this->primerDiaDeClase($origen, $fecha->copy()->startOfDay())->isSameDay($fecha);
    }

    /**
     * Días de la semana en que se dictan las clases del ciclo (ver esDiaDeClase).
     *
     * @param Ciclo $origen
     * @return array<int, string> Ej. ['lunes', 'miércoles']
     */
    public function diasClase(Ciclo $origen): array
    {
        $grupo = $this->grupoReferencia($origen);

        return $grupo
            ? $grupo->horarios->pluck('dia')->unique()->values()->all()
            : [$origen->fecha_inicio->locale('es')->dayName];
    }

    /**
     * Primer día de clase del ciclo a partir de $desde (inclusive).
     */
    private function primerDiaDeClase(Ciclo $origen, Carbon $desde): Carbon
    {
        $grupo = $this->grupoReferencia($origen);

        $fecha = $grupo
            ? $this->calendarioLaboral->calcularRangoGrupo($grupo, $desde->copy(), PHP_FLOAT_EPSILON)['fecha_inicio'] ?? null
            : null;

        if (! $fecha) {
            $fecha = $desde->copy();
            while ($fecha->dayOfWeek !== $origen->fecha_inicio->dayOfWeek) {
                $fecha->addDay();
            }
        }

        return $fecha;
    }

    /**
     * Primer grupo del ciclo (por orden) con horarios: define los días de clase.
     */
    private function grupoReferencia(Ciclo $origen): ?Grupo
    {
        return $origen->grupos()->with(['modulo', 'horarios'])->get()
            ->first(fn ($grupo) => $grupo->horarios->isNotEmpty());
    }

    /**
     * Crea una copia del ciclo con la nueva fecha de inicio y recalcula su calendario.
     *
     * En modo de fecha fin automática la fecha de fin resulta del cálculo. En modo
     * fijo se conserva la duración en días del ciclo origen, salvo que se indique
     * $fechaFin. Los inscritos arrancan en cero.
     *
     * @param Ciclo       $origen      Ciclo a copiar.
     * @param string      $fechaInicio Nueva fecha de inicio (Y-m-d).
     * @param string|null $nombre      Nombre del clon; por defecto "{nombre} (copia)" único.
     * @param string|null $fechaFin    Fecha fin fija del clon (solo modo fijo).
     * @return Ciclo Ciclo creado con sus relaciones cargadas.
     *
     * @throws ValidationException Si el clon se solapa con otro ciclo en algún grupo.
     */
    public function clonar(Ciclo $origen, string $fechaInicio, ?string $nombre = null, ?string $fechaFin = null): Ciclo
    {
        return DB::transaction(function () use ($origen, $fechaInicio, $nombre, $fechaFin) {
            $inicio = Carbon::parse($fechaInicio);

            $fechaFinFija = null;
            if (! $origen->fecha_fin_automatica) {
                $fechaFinFija = $fechaFin
                    ?? $inicio->copy()->addDays((int) $origen->fecha_inicio->diffInDays($origen->fecha_fin))->toDateString();
            }

            $clon = Ciclo::create([
                'sede_id'              => $origen->sede_id,
                'curso_id'             => $origen->curso_id,
                'nombre'               => $nombre ?? $this->nombreUnico($origen->nombre),
                'descripcion'          => $origen->descripcion,
                'fecha_inicio'         => $inicio->toDateString(),
                'fecha_fin'            => $fechaFinFija,
                'fecha_fin_automatica' => $origen->fecha_fin_automatica,
                'inscritos'            => 0,
                'status'               => $origen->status,
            ]);

            $gruposConOrden = $origen->grupos()->get()
                ->map(fn ($grupo) => ['grupo_id' => $grupo->id, 'orden' => $grupo->pivot->orden])
                ->all();

            if ($gruposConOrden) {
                $clon->asignarGruposConOrden($gruposConOrden);
                $this->calendario->recalcularCiclo($clon);
                $this->validarSinSolapamientos($clon);
            }

            return $clon->load(['sede', 'curso', 'grupos']);
        });
    }

    /**
     * Verifica que ningún grupo del clon comparta ejecución ni cruce fechas con
     * otro ciclo vigente (no eliminado). Lanza ValidationException con un mensaje
     * por cada conflicto en el campo fecha_inicio.
     *
     * @param Ciclo $clon
     * @return void
     *
     * @throws ValidationException
     */
    private function validarSinSolapamientos(Ciclo $clon): void
    {
        $conflictos = [];

        foreach ($clon->grupos()->get() as $grupo) {
            $inicio = $grupo->pivot->fecha_inicio_grupo;
            $fin    = $grupo->pivot->fecha_fin_grupo;

            if (! $inicio || ! $fin) {
                continue;
            }

            $cruces = DB::table('ciclo_grupo')
                ->join('ciclos', 'ciclos.id', '=', 'ciclo_grupo.ciclo_id')
                ->whereNull('ciclos.deleted_at')
                ->where('ciclo_grupo.grupo_id', $grupo->id)
                ->where('ciclo_grupo.ciclo_id', '!=', $clon->id)
                ->where('ciclo_grupo.fecha_inicio_grupo', '<=', $fin)
                ->where('ciclo_grupo.fecha_fin_grupo', '>=', $inicio)
                ->orderBy('ciclo_grupo.fecha_inicio_grupo')
                ->get(['ciclos.nombre', 'ciclo_grupo.fecha_inicio_grupo', 'ciclo_grupo.fecha_fin_grupo']);

            foreach ($cruces as $cruce) {
                $conflictos[] = sprintf(
                    "El grupo '%s' (%s a %s) se solapa con el ciclo '%s' (%s a %s).",
                    $grupo->nombre,
                    substr($inicio, 0, 10),
                    substr($fin, 0, 10),
                    $cruce->nombre,
                    substr($cruce->fecha_inicio_grupo, 0, 10),
                    substr($cruce->fecha_fin_grupo, 0, 10)
                );
            }
        }

        if ($conflictos) {
            throw ValidationException::withMessages(['fecha_inicio' => $conflictos]);
        }
    }

    /**
     * Genera un nombre "{base} (copia)" que no choque con otro ciclo (incluidos eliminados).
     */
    private function nombreUnico(string $base): string
    {
        $nombre = "{$base} (copia)";
        $n = 2;

        while (Ciclo::withTrashed()->where('nombre', $nombre)->exists()) {
            $nombre = "{$base} (copia {$n})";
            $n++;
        }

        return $nombre;
    }
}
