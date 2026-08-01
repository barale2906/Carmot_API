<?php

namespace App\Models\Inventarios;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo InvEntregaSimple — seguimiento de entrega de un producto simple.
 *
 * Creado automáticamente cuando el pedido pasa a 'pagado'.
 * Status: pendiente → entregado.
 *
 * @property int         $id
 * @property int         $pedido_item_id
 * @property int         $producto_id
 * @property int         $cantidad_entregada
 * @property string      $status
 * @property string|null $fecha_entrega
 * @property int|null    $user_id
 */
class InvEntregaSimple extends Model
{
    public const STATUS_PENDIENTE = 'pendiente';
    public const STATUS_ENTREGADO = 'entregado';

    protected $table = 'inv_entregas_simple';

    protected $guarded = ['id'];

    protected $casts = [
        'pedido_item_id'     => 'integer',
        'producto_id'        => 'integer',
        'cantidad_entregada' => 'integer',
        'fecha_entrega'      => 'datetime',
        'user_id'            => 'integer',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    /**
     * Ítem de pedido al que pertenece esta entrega.
     *
     * @return BelongsTo
     */
    public function pedidoItem(): BelongsTo
    {
        return $this->belongsTo(InvPedidoItem::class, 'pedido_item_id');
    }

    /**
     * Producto entregado.
     *
     * @return BelongsTo
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(InvProducto::class, 'producto_id');
    }

    /**
     * Usuario que ejecutó la entrega.
     *
     * @return BelongsTo
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Necesidades de compra generadas por esta entrega pendiente.
     *
     * @return HasMany
     */
    public function necesidades(): HasMany
    {
        return $this->hasMany(InvNecesidadCompra::class, 'entregable_id')
            ->where('entregable_type', self::class);
    }
}
