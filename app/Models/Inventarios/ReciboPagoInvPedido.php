<?php

namespace App\Models\Inventarios;

use App\Models\Financiero\ReciboPago\ReciboPago;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo ReciboPagoInvPedido — vínculo entre un recibo de pago y un pedido de inventario.
 *
 * Registra cuánto del recibo se destina a saldar el pedido.
 *
 * @property int   $id
 * @property int   $recibo_pago_id
 * @property int   $pedido_id
 * @property float $monto_abonado
 */
class ReciboPagoInvPedido extends Model
{
    protected $table = 'recibo_pago_inv_pedido';

    protected $guarded = ['id'];

    protected $casts = [
        'recibo_pago_id' => 'integer',
        'pedido_id'      => 'integer',
        'monto_abonado'  => 'decimal:2',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    /**
     * Recibo de pago.
     *
     * @return BelongsTo
     */
    public function reciboPago(): BelongsTo
    {
        return $this->belongsTo(ReciboPago::class, 'recibo_pago_id');
    }

    /**
     * Pedido de inventario.
     *
     * @return BelongsTo
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(InvPedido::class, 'pedido_id');
    }
}
