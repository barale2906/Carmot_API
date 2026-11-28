<?php

namespace App\Models\Traslados;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\Academico\Programacion;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Traslado de una matrícula: cambio de programación (jornada o ciclo), de curso, de sede, o de
 * sede y curso a la vez. La matrícula se conserva y este registro deja el origen, el destino,
 * lo financiero y el resultado de la nivelación para hacerle seguimiento.
 *
 * @property int $id
 * @property int $matricula_id
 * @property string $tipo programacion | curso | sede | sede_curso
 * @property \Illuminate\Support\Carbon $fecha
 * @property int $sede_origen_id
 * @property int $curso_origen_id
 * @property int $ciclo_origen_id
 * @property int|null $programacion_origen_id
 * @property int|null $lp_origen_id
 * @property int $sede_destino_id
 * @property int $curso_destino_id
 * @property int $ciclo_destino_id
 * @property int|null $programacion_destino_id
 * @property int|null $lp_destino_id
 * @property string $motivo
 * @property int|null $usuario_id
 * @property float $valor_pagado Lo pagado hasta el traslado (no se traslada)
 * @property float $saldo_anulado Saldo pendiente cancelado en la sede de origen
 * @property float $saldo_nuevo Saldo acordado generado en la sede de destino
 * @property float $costo_traslado Cobro por el traslado (0 si no se cobra)
 * @property array|null $carteras Ids de carteras: anuladas, cerradas y creadas
 * @property int $reposiciones_canceladas Reposiciones pendientes del ciclo de origen canceladas por el traslado
 * @property array|null $nivelacion Cruce de temas entre el ciclo de origen y el de destino
 * @property bool $nivelacion_pendiente
 * @property \Illuminate\Support\Carbon|null $nivelacion_resuelta_at
 * @property int|null $nivelacion_resuelta_por_id
 * @property string|null $nivelacion_observaciones
 */
class MatriculaTraslado extends Model
{
    public const TIPO_PROGRAMACION = 'programacion';

    public const TIPO_CURSO = 'curso';

    public const TIPO_SEDE = 'sede';

    public const TIPO_SEDE_CURSO = 'sede_curso';

    public const TIPOS = [
        self::TIPO_PROGRAMACION => 'Cambio de programación',
        self::TIPO_CURSO => 'Cambio de curso',
        self::TIPO_SEDE => 'Cambio de sede',
        self::TIPO_SEDE_CURSO => 'Cambio de sede y de curso',
    ];

    protected $table = 'matricula_traslados';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'fecha' => 'date',
        'valor_pagado' => 'decimal:2',
        'saldo_anulado' => 'decimal:2',
        'saldo_nuevo' => 'decimal:2',
        'costo_traslado' => 'decimal:2',
        'carteras' => 'array',
        'nivelacion' => 'array',
        'nivelacion_pendiente' => 'boolean',
        'nivelacion_resuelta_at' => 'datetime',
    ];

    /**
     * Deduce el tipo de traslado comparando la sede y el curso de origen con los de destino.
     */
    public static function deducirTipo(bool $cambiaSede, bool $cambiaCurso): string
    {
        return match (true) {
            $cambiaSede && $cambiaCurso => self::TIPO_SEDE_CURSO,
            $cambiaSede => self::TIPO_SEDE,
            $cambiaCurso => self::TIPO_CURSO,
            default => self::TIPO_PROGRAMACION,
        };
    }

    /**
     * Indica si el traslado se hace dentro del mismo curso, que es cuando aplica la nivelación.
     */
    public function esMismoCurso(): bool
    {
        return in_array($this->tipo, [self::TIPO_PROGRAMACION, self::TIPO_SEDE], true);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function nivelacionResueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nivelacion_resuelta_por_id');
    }

    public function sedeOrigen(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_origen_id');
    }

    public function sedeDestino(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_destino_id');
    }

    public function cursoOrigen(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_origen_id');
    }

    public function cursoDestino(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_destino_id');
    }

    public function cicloOrigen(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class, 'ciclo_origen_id');
    }

    public function cicloDestino(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class, 'ciclo_destino_id');
    }

    public function programacionOrigen(): BelongsTo
    {
        return $this->belongsTo(Programacion::class, 'programacion_origen_id');
    }

    public function programacionDestino(): BelongsTo
    {
        return $this->belongsTo(Programacion::class, 'programacion_destino_id');
    }

    public function lpOrigen(): BelongsTo
    {
        return $this->belongsTo(LpPrecioProducto::class, 'lp_origen_id');
    }

    public function lpDestino(): BelongsTo
    {
        return $this->belongsTo(LpPrecioProducto::class, 'lp_destino_id');
    }

    /**
     * Traslados con origen o destino en alguna de las sedes indicadas.
     *
     * @param  array<int>  $sedeIds
     */
    public function scopeDeSedes(Builder $query, array $sedeIds): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereIn('sede_origen_id', $sedeIds)->orWhereIn('sede_destino_id', $sedeIds));
    }

    /**
     * Filtros del listado: matricula_id, estudiante_id, tipo, sede_id (origen o destino),
     * nivelacion_pendiente y rango de fechas (fecha_inicio, fecha_fin).
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeWithFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(! empty($filters['matricula_id']), fn ($q) => $q->where('matricula_id', $filters['matricula_id']))
            ->when(! empty($filters['estudiante_id']), fn ($q) => $q->whereHas('matricula', fn ($m) => $m->where('estudiante_id', $filters['estudiante_id'])))
            ->when(! empty($filters['tipo']), fn ($q) => $q->where('tipo', $filters['tipo']))
            ->when(! empty($filters['sede_id']), fn ($q) => $q->deSedes([(int) $filters['sede_id']]))
            ->when(isset($filters['nivelacion_pendiente']) && $filters['nivelacion_pendiente'] !== '', fn ($q) => $q->where('nivelacion_pendiente', filter_var($filters['nivelacion_pendiente'], FILTER_VALIDATE_BOOLEAN)))
            ->when(! empty($filters['fecha_inicio']), fn ($q) => $q->whereDate('fecha', '>=', $filters['fecha_inicio']))
            ->when(! empty($filters['fecha_fin']), fn ($q) => $q->whereDate('fecha', '<=', $filters['fecha_fin']));
    }
}
