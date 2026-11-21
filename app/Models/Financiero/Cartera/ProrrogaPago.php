<?php

namespace App\Models\Financiero\Cartera;

use App\Models\Configuracion\Sede;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo ProrrogaPago
 *
 * Plazo nuevo de pago (máximo {@see self::PLAZO_MAXIMO_DIAS} días) que quien toma la asistencia
 * acuerda con un estudiante en mora para dejarlo entrar a clase. NO es el acuerdo de pago de
 * cartera ({@see \App\Services\Financiero\AcuerdoPagoService}, que reestructura cuotas): aquí
 * la cartera no cambia, solo se deja constancia visible en la planilla de asistencia.
 *
 * Ciclo de vida: `vigente` → `cumplido` (las cuotas cubiertas quedaron pagadas) o `incumplido`
 * (llegó la fecha compromiso sin pagarlas; se avisa al estudiante y a la administración).
 * Cancelar es un soft delete.
 *
 * @property int $id
 * @property int $estudiante_id
 * @property int|null $sede_id Sede de la cuota vencida más antigua
 * @property array<int> $cartera_ids Cuotas vencidas que cubre (foto al registrarla)
 * @property float $saldo_vencido Saldo vencido al registrarla
 * @property Carbon $fecha_acuerdo
 * @property Carbon $fecha_compromiso Hasta cuándo puede entrar a clase
 * @property string $estado vigente | cumplido | incumplido
 * @property string|null $observacion
 * @property int|null $registrada_por_id
 */
class ProrrogaPago extends Model
{
    use SoftDeletes;

    public const PLAZO_MAXIMO_DIAS = 15;

    public const VIGENTE = 'vigente';

    public const CUMPLIDO = 'cumplido';

    public const INCUMPLIDO = 'incumplido';

    protected $table = 'prorrogas_pago';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $attributes = ['estado' => self::VIGENTE];

    protected $casts = [
        'cartera_ids' => 'array',
        'saldo_vencido' => 'decimal:2',
        'fecha_acuerdo' => 'date',
        'fecha_compromiso' => 'date',
    ];

    /** Estudiante en mora. */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /** Sede de la cuota vencida más antigua. */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /** Quien acordó la prórroga. */
    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por_id');
    }

    /**
     * Registra la prórroga de un estudiante en mora: guarda una foto de sus cuotas vencidas con
     * saldo (cuáles y cuánto), sin modificarlas. Devuelve null si el estudiante no está en mora.
     */
    public static function registrar(int $estudianteId, string $fechaCompromiso, ?string $observacion, User $registrador): ?self
    {
        $cuotas = Cartera::vencidas()->where('saldo', '>', 0)
            ->where('estudiante_id', $estudianteId)
            ->orderBy('fecha_vencimiento')->orderBy('numero_cuota')
            ->get();

        if ($cuotas->isEmpty()) {
            return null;
        }

        return static::create([
            'estudiante_id' => $estudianteId,
            'sede_id' => $cuotas->first()->sede_id,
            'cartera_ids' => $cuotas->pluck('id')->all(),
            'saldo_vencido' => round((float) $cuotas->sum('saldo'), 2),
            'fecha_acuerdo' => today(),
            'fecha_compromiso' => $fechaCompromiso,
            'observacion' => $observacion,
            'registrada_por_id' => $registrador->id,
        ]);
    }

    /**
     * Indica si las cuotas que cubre la prórroga ya no tienen saldo pendiente.
     */
    public function cuotasPagadas(): bool
    {
        return ! Cartera::whereIn('id', $this->cartera_ids)->where('saldo', '>', 0)->exists();
    }

    /**
     * Prórrogas vigentes: sin resolver y con la fecha compromiso de hoy en adelante.
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', self::VIGENTE)->whereDate('fecha_compromiso', '>=', today());
    }

    /**
     * Prórrogas sin resolver cuya fecha compromiso ya pasó (las revisa el comando diario).
     */
    public function scopePorResolver(Builder $query): Builder
    {
        return $query->where('estado', self::VIGENTE)->whereDate('fecha_compromiso', '<', today());
    }

    /**
     * Prórrogas que el usuario puede ver: un alumno solo las suyas; el resto, las de sus sedes.
     */
    public function scopeVisiblesPara(Builder $query, User $usuario): Builder
    {
        if ($usuario->esSoloEstudiante()) {
            return $query->where('estudiante_id', $usuario->id);
        }

        return $query->whereIn('sede_id', $usuario->idsSedesAccesibles());
    }

    /**
     * Filtros del listado: estudiante, estado y rango de la fecha compromiso.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function scopeConFiltros(Builder $query, array $filtros): Builder
    {
        return $query
            ->when(! empty($filtros['estudiante_id']), fn ($q) => $q->where('estudiante_id', $filtros['estudiante_id']))
            ->when(! empty($filtros['estado']), fn ($q) => $q->where('estado', $filtros['estado']))
            ->when(! empty($filtros['fecha_desde']), fn ($q) => $q->whereDate('fecha_compromiso', '>=', $filtros['fecha_desde']))
            ->when(! empty($filtros['fecha_hasta']), fn ($q) => $q->whereDate('fecha_compromiso', '<=', $filtros['fecha_hasta']));
    }

    /**
     * Veces que se acordó un plazo nuevo con cada estudiante (sin contar las canceladas), para el
     * informe de cartera: total y desglose por estado (`vigente` solo si su fecha compromiso no pasó).
     *
     * @param  array<int>  $estudianteIds
     * @return \Illuminate\Support\Collection<int, array{total: int, vigentes: int, cumplidas: int, incumplidas: int}>
     *                                                                                                                 estudiante_id => conteo
     */
    public static function resumenPorEstudiante(array $estudianteIds): \Illuminate\Support\Collection
    {
        if ($estudianteIds === []) {
            return collect();
        }

        return static::whereIn('estudiante_id', $estudianteIds)
            ->get(['estudiante_id', 'estado', 'fecha_compromiso'])
            ->groupBy('estudiante_id')
            ->map(fn ($grupo) => [
                'total' => $grupo->count(),
                'vigentes' => $grupo->filter(fn (self $p) => $p->estado === self::VIGENTE && $p->fecha_compromiso->gte(today()))->count(),
                'cumplidas' => $grupo->where('estado', self::CUMPLIDO)->count(),
                'incumplidas' => $grupo->where('estado', self::INCUMPLIDO)->count(),
            ]);
    }

    /**
     * Prórroga vigente de cada estudiante cuyas cuotas cubiertas aún tienen saldo, para mostrarla
     * en la planilla de asistencia junto a su mora.
     *
     * @param  array<int>  $estudianteIds
     * @return \Illuminate\Support\Collection<int, array{id: int, fecha_acuerdo: string, fecha_compromiso: string, dias_restantes: int, saldo_vencido: float, observacion: string|null, registrada_por: string|null}>
     *                                                                                                                                                                                                                estudiante_id => prórroga
     */
    public static function vigentesPorEstudiante(array $estudianteIds): \Illuminate\Support\Collection
    {
        if ($estudianteIds === []) {
            return collect();
        }

        return static::vigentes()
            ->whereIn('estudiante_id', $estudianteIds)
            ->with('registradaPor')
            ->orderBy('id')
            ->get()
            ->reject(fn (self $p) => $p->cuotasPagadas())
            ->keyBy('estudiante_id')
            ->map(fn (self $p) => [
                'id' => $p->id,
                'fecha_acuerdo' => $p->fecha_acuerdo->format('Y-m-d'),
                'fecha_compromiso' => $p->fecha_compromiso->format('Y-m-d'),
                'dias_restantes' => (int) today()->diffInDays($p->fecha_compromiso),
                'saldo_vencido' => (float) $p->saldo_vencido,
                'observacion' => $p->observacion,
                'registrada_por' => $p->registradaPor?->name,
            ]);
    }
}
