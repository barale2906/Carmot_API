<?php

namespace App\Models\Activos;

use App\Models\Configuracion\Area;
use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro del historial de un activo: ingreso, traslado o cambio de estado.
 *
 * @property int $id
 * @property int $activo_id
 * @property string $tipo ingreso | traslado | cambio_estado
 * @property string|null $estado_traslado pendiente | recibido | cancelado (solo traslados)
 * @property int|null $responsable_destino_id responsable a asignar al recibir; null conserva el actual
 */
class ActMovimiento extends Model
{
    public const TIPOS = [
        'ingreso'       => 'Ingreso',
        'traslado'      => 'Traslado',
        'cambio_estado' => 'Cambio de estado',
    ];

    public const TIPO_TRASLADO = 'traslado';

    /**
     * Ciclo del traslado: se envía pendiente y la sede de destino registra la recepción;
     * solo al recibirlo el activo cambia de ubicación. Mientras esté pendiente se puede cancelar.
     */
    public const ESTADOS_TRASLADO = [
        'pendiente' => 'Pendiente de recepción',
        'recibido'  => 'Recibido',
        'cancelado' => 'Cancelado',
    ];

    public const TRASLADO_PENDIENTE = 'pendiente';

    public const TRASLADO_RECIBIDO = 'recibido';

    public const TRASLADO_CANCELADO = 'cancelado';

    /** Relaciones que usa ActMovimientoResource. */
    public const RELACIONES = [
        'sedeOrigen', 'areaOrigen', 'sedeDestino', 'areaDestino',
        'usuario', 'responsableDestino', 'recibidoPor', 'canceladoPor',
    ];

    protected $table = 'act_movimientos';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'fecha'           => 'date',
        'fecha_recepcion' => 'date',
    ];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(ActActivo::class, 'activo_id');
    }

    public function sedeOrigen(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_origen_id');
    }

    public function areaOrigen(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_origen_id');
    }

    public function sedeDestino(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_destino_id');
    }

    public function areaDestino(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_destino_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function responsableDestino(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_destino_id');
    }

    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por_id');
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por_id');
    }

    /**
     * Traslados cuyo origen o destino es alguna de las sedes indicadas.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array<int> $sedeIds
     */
    public function scopeTrasladosDeSedes($query, array $sedeIds)
    {
        return $query->where('tipo', self::TIPO_TRASLADO)
            ->where(fn ($q) => $q->whereIn('sede_origen_id', $sedeIds)->orWhereIn('sede_destino_id', $sedeIds));
    }

    /**
     * Filtros del listado de traslados:
     * - estado: pendiente | recibido | cancelado (vacío = todos)
     * - direccion: entrantes (destino en mis sedes) | salientes (origen en mis sedes)
     * - search: ID exacto o nombre/serial del activo
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array<string, mixed> $filters
     * @param array<int> $sedeIds Sedes del usuario, para resolver la dirección
     */
    public function scopeWithTrasladoFilters($query, array $filters, array $sedeIds)
    {
        return $query
            ->when(! empty($filters['estado']), fn ($q) => $q->where('estado_traslado', $filters['estado']))
            ->when(($filters['direccion'] ?? null) === 'entrantes', fn ($q) => $q->whereIn('sede_destino_id', $sedeIds))
            ->when(($filters['direccion'] ?? null) === 'salientes', fn ($q) => $q->whereIn('sede_origen_id', $sedeIds))
            ->when(! empty($filters['search']), fn ($q) => $q->whereHas('activo', fn ($a) => $a->search($filters['search'])));
    }

    public function estaPendiente(): bool
    {
        return $this->tipo === self::TIPO_TRASLADO && $this->estado_traslado === self::TRASLADO_PENDIENTE;
    }
}
