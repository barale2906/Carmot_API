<?php

namespace App\Models\Inventarios;

use App\Models\Configuracion\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo InvPedido — cabecera de una venta de inventario a un estudiante.
 *
 * El valor_total es inmutable una vez creado; los abonos reducen el saldo.
 * Cuando el saldo llega a 0, el status pasa a 'pagado' y se dispara el despacho.
 *
 * Status: activo → pagado → entregando → entregado | cancelado
 *
 * @property int    $id
 * @property int    $estudiante_id
 * @property int    $sede_id
 * @property int    $almacen_id
 * @property int    $cajero_id
 * @property float  $valor_total
 * @property float  $abono_acumulado
 * @property float  $saldo
 * @property string $status
 */
class InvPedido extends Model
{
    use HasFactory;

    public const STATUS_ACTIVO     = 'activo';
    public const STATUS_PAGADO     = 'pagado';
    public const STATUS_ENTREGANDO = 'entregando';
    public const STATUS_ENTREGADO  = 'entregado';
    public const STATUS_CANCELADO  = 'cancelado';

    protected $table = 'inv_pedidos';

    protected $guarded = ['id'];

    protected $casts = [
        'estudiante_id'   => 'integer',
        'sede_id'         => 'integer',
        'almacen_id'      => 'integer',
        'cajero_id'       => 'integer',
        'valor_total'     => 'decimal:2',
        'abono_acumulado' => 'decimal:2',
        'saldo'           => 'decimal:2',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    /**
     * Estudiante propietario del pedido.
     *
     * @return BelongsTo
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /**
     * Sede donde se creó el pedido.
     *
     * @return BelongsTo
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    /**
     * Almacén de despacho.
     *
     * @return BelongsTo
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(InvAlmacen::class, 'almacen_id');
    }

    /**
     * Cajero que creó el pedido.
     *
     * @return BelongsTo
     */
    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cajero_id');
    }

    /**
     * Ítems del pedido.
     *
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvPedidoItem::class, 'pedido_id');
    }

    /**
     * Vínculos con los recibos de pago que cubren este pedido.
     *
     * @return HasMany
     */
    public function reciboLinks(): HasMany
    {
        return $this->hasMany(ReciboPagoInvPedido::class, 'pedido_id');
    }

    /**
     * Scope: pedidos activos (con saldo pendiente).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActivos($query)
    {
        return $query->where('status', self::STATUS_ACTIVO);
    }

    /**
     * Scope: pedidos con entregas pendientes.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePendientesEntrega($query)
    {
        return $query->whereIn('status', [self::STATUS_PAGADO, self::STATUS_ENTREGANDO]);
    }

    /**
     * Aplica filtros dinámicos.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                isset($filters['sede_id']) && $filters['sede_id'] !== '',
                fn ($q) => $q->where('sede_id', (int) $filters['sede_id'])
            )
            ->when(
                isset($filters['estudiante_id']) && $filters['estudiante_id'] !== '',
                fn ($q) => $q->where('estudiante_id', (int) $filters['estudiante_id'])
            )
            ->when(
                isset($filters['almacen_id']) && $filters['almacen_id'] !== '',
                fn ($q) => $q->where('almacen_id', (int) $filters['almacen_id'])
            );
    }
}
