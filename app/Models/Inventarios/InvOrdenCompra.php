<?php

namespace App\Models\Inventarios;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo InvOrdenCompra — cabecera de una orden de compra a un proveedor.
 *
 * Flujo: borrador → enviada → (recibida_parcial) → recibida | cancelada
 * Cuando se recibe, InvRecepcionService genera el documento de entrada y actualiza el stock.
 *
 * @property int         $id
 * @property int         $proveedor_id
 * @property int         $almacen_id
 * @property int         $responsable_id
 * @property string      $status
 * @property float       $subtotal
 * @property float       $total
 * @property string|null $observaciones
 * @property string|null $fecha_esperada
 */
class InvOrdenCompra extends Model
{
    use HasFactory;

    public const STATUS_BORRADOR         = 'borrador';
    public const STATUS_ENVIADA          = 'enviada';
    public const STATUS_RECIBIDA_PARCIAL = 'recibida_parcial';
    public const STATUS_RECIBIDA         = 'recibida';
    public const STATUS_CANCELADA        = 'cancelada';

    protected $table = 'inv_ordenes_compra';

    protected $guarded = ['id'];

    protected $casts = [
        'proveedor_id'    => 'integer',
        'almacen_id'      => 'integer',
        'responsable_id'  => 'integer',
        'subtotal'        => 'decimal:2',
        'total'           => 'decimal:2',
        'fecha_esperada'  => 'date',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    /**
     * Proveedor al que se emite la orden.
     *
     * @return BelongsTo
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(InvProveedor::class, 'proveedor_id');
    }

    /**
     * Almacén destino donde llegará la mercancía.
     *
     * @return BelongsTo
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(InvAlmacen::class, 'almacen_id');
    }

    /**
     * Usuario responsable de la orden.
     *
     * @return BelongsTo
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * Ítems (productos) de la orden.
     *
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvOrdenCompraItem::class, 'orden_id');
    }

    /**
     * Indica si la orden puede editarse (solo en borrador).
     *
     * @return bool
     */
    public function esEditable(): bool
    {
        return $this->status === self::STATUS_BORRADOR;
    }

    /**
     * Indica si la orden puede recibirse (solo enviada o recibida_parcial).
     *
     * @return bool
     */
    public function esRecibible(): bool
    {
        return in_array($this->status, [self::STATUS_ENVIADA, self::STATUS_RECIBIDA_PARCIAL]);
    }

    /**
     * Scope: órdenes abiertas (borrador o enviadas).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAbiertas($query)
    {
        return $query->whereIn('status', [self::STATUS_BORRADOR, self::STATUS_ENVIADA]);
    }

    /**
     * Scope: órdenes pendientes de recepción.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePendientesRecepcion($query)
    {
        return $query->whereIn('status', [self::STATUS_ENVIADA, self::STATUS_RECIBIDA_PARCIAL]);
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
                isset($filters['proveedor_id']) && $filters['proveedor_id'] !== '',
                fn ($q) => $q->where('proveedor_id', (int) $filters['proveedor_id'])
            )
            ->when(
                isset($filters['almacen_id']) && $filters['almacen_id'] !== '',
                fn ($q) => $q->where('almacen_id', (int) $filters['almacen_id'])
            );
    }

    /**
     * Recalcula subtotal y total a partir de los ítems actuales.
     *
     * @return void
     */
    public function recalcularTotales(): void
    {
        $subtotal = $this->items()->sum('subtotal');
        $this->update(['subtotal' => $subtotal, 'total' => $subtotal]);
    }
}
