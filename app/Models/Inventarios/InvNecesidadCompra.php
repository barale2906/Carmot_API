<?php

namespace App\Models\Inventarios;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Modelo InvNecesidadCompra — alerta de falta de stock para una entrega pendiente.
 *
 * Generada cuando no hay stock suficiente para despachar un ítem.
 * Cuando llega mercancía, InvNecesidadService verifica si queda cubierta
 * y notifica a los cajeros de la sede.
 *
 * @property int    $id
 * @property int    $producto_id
 * @property int    $cantidad_necesaria
 * @property string $entregable_type
 * @property int    $entregable_id
 * @property int    $almacen_id
 * @property int    $estudiante_id
 * @property string $status
 * @property bool   $notificado
 */
class InvNecesidadCompra extends Model
{
    public const STATUS_PENDIENTE = 'pendiente';
    public const STATUS_ATENDIDA  = 'atendida';
    public const STATUS_CANCELADA = 'cancelada';

    protected $table = 'inv_necesidades_compra';

    protected $guarded = ['id'];

    protected $casts = [
        'producto_id'       => 'integer',
        'cantidad_necesaria' => 'integer',
        'entregable_id'     => 'integer',
        'almacen_id'        => 'integer',
        'estudiante_id'     => 'integer',
        'notificado'        => 'boolean',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    /**
     * Entrega (simple o componente de kit) que origina la necesidad.
     *
     * @return MorphTo
     */
    public function entregable(): MorphTo
    {
        return $this->morphTo('entregable');
    }

    /**
     * Producto que se necesita.
     *
     * @return BelongsTo
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(InvProducto::class, 'producto_id');
    }

    /**
     * Almacén donde debe llegar el stock.
     *
     * @return BelongsTo
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(InvAlmacen::class, 'almacen_id');
    }

    /**
     * Estudiante que espera la entrega.
     *
     * @return BelongsTo
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    /**
     * Scope: solo necesidades pendientes.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePendientes($query)
    {
        return $query->where('status', self::STATUS_PENDIENTE);
    }
}
