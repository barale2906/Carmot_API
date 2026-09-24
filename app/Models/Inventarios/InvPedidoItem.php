<?php

namespace App\Models\Inventarios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modelo InvPedidoItem — línea de producto de un pedido de inventario.
 *
 * @property int        $id
 * @property int        $pedido_id
 * @property int        $producto_id
 * @property int        $cantidad
 * @property float|null $precio_lista       Precio de lista antes de descuento
 * @property float      $descuento_unitario Descuento por unidad (precio_lista - precio_unitario)
 * @property float      $precio_unitario    Precio final cobrado por unidad
 * @property float      $subtotal           cantidad × precio_unitario
 */
class InvPedidoItem extends Model
{
    protected $table = 'inv_pedido_items';

    protected $guarded = ['id'];

    protected $casts = [
        'pedido_id'          => 'integer',
        'producto_id'        => 'integer',
        'cantidad'           => 'integer',
        'precio_lista'       => 'decimal:2',
        'descuento_unitario' => 'decimal:2',
        'precio_unitario'    => 'decimal:2',
        'subtotal'           => 'decimal:2',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    /**
     * Pedido al que pertenece el ítem.
     *
     * @return BelongsTo
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(InvPedido::class, 'pedido_id');
    }

    /**
     * Producto vendido en este ítem.
     *
     * @return BelongsTo
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(InvProducto::class, 'producto_id');
    }

    /**
     * Entrega de producto simple vinculada a este ítem (si el producto es simple).
     *
     * @return HasOne
     */
    public function entregaSimple(): HasOne
    {
        return $this->hasOne(InvEntregaSimple::class, 'pedido_item_id');
    }

    /**
     * Entrega de kit vinculada a este ítem (si el producto es kit).
     *
     * @return HasOne
     */
    public function entregaKit(): HasOne
    {
        return $this->hasOne(InvEntregaKit::class, 'pedido_item_id');
    }
}
