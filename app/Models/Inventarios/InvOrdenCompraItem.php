<?php

namespace App\Models\Inventarios;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo InvOrdenCompraItem — ítem de una orden de compra.
 *
 * cantidad_recibida se incrementa en cada recepción (total o parcial).
 * Cuando cantidad_recibida >= cantidad_solicitada el ítem se considera completo.
 *
 * @property int   $id
 * @property int   $orden_id
 * @property int   $producto_id
 * @property int   $cantidad_solicitada
 * @property float $precio_costo_unitario
 * @property float $subtotal
 * @property int   $cantidad_recibida
 */
class InvOrdenCompraItem extends Model
{
    use HasFactory;

    protected $table = 'inv_orden_compra_items';

    protected $guarded = ['id'];

    protected $casts = [
        'orden_id'              => 'integer',
        'producto_id'           => 'integer',
        'cantidad_solicitada'   => 'integer',
        'precio_costo_unitario' => 'decimal:2',
        'subtotal'              => 'decimal:2',
        'cantidad_recibida'     => 'integer',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
    ];

    /**
     * Orden de compra a la que pertenece este ítem.
     *
     * @return BelongsTo
     */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(InvOrdenCompra::class, 'orden_id');
    }

    /**
     * Producto ordenado.
     *
     * @return BelongsTo
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(InvProducto::class, 'producto_id');
    }

    /**
     * Indica si este ítem ya fue completamente recibido.
     *
     * @return bool
     */
    public function estaCompleto(): bool
    {
        return $this->cantidad_recibida >= $this->cantidad_solicitada;
    }

    /**
     * Cantidad pendiente de recibir.
     *
     * @return int
     */
    public function cantidadPendiente(): int
    {
        return max(0, $this->cantidad_solicitada - $this->cantidad_recibida);
    }
}
