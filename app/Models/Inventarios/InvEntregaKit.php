<?php

namespace App\Models\Inventarios;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo InvEntregaKit — cabecera de entrega de un kit.
 *
 * Creado automáticamente cuando el pedido pasa a 'pagado'.
 * Status: pendiente → parcial → completo.
 *
 * @property int    $id
 * @property int    $pedido_item_id
 * @property int    $kit_producto_id
 * @property int    $cantidad_kits
 * @property string $status
 * @property int|null $user_id
 */
class InvEntregaKit extends Model
{
    public const STATUS_PENDIENTE = 'pendiente';
    public const STATUS_PARCIAL   = 'parcial';
    public const STATUS_COMPLETO  = 'completo';

    protected $table = 'inv_entregas_kit';

    protected $guarded = ['id'];

    protected $casts = [
        'pedido_item_id'  => 'integer',
        'kit_producto_id' => 'integer',
        'cantidad_kits'   => 'integer',
        'user_id'         => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
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
     * Producto kit despachado.
     *
     * @return BelongsTo
     */
    public function kitProducto(): BelongsTo
    {
        return $this->belongsTo(InvProducto::class, 'kit_producto_id');
    }

    /**
     * Líneas de componente de esta entrega.
     *
     * @return HasMany
     */
    public function componentes(): HasMany
    {
        return $this->hasMany(InvEntregaKitComponente::class, 'entrega_kit_id');
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
     * Recalcula y actualiza el status según el estado de sus componentes.
     *
     * @return void
     */
    public function recalcularStatus(): void
    {
        $this->load('componentes');

        $total = $this->componentes->count();

        if ($total === 0) {
            $this->update(['status' => self::STATUS_PENDIENTE]);

            return;
        }

        $entregados = $this->componentes
            ->where('status', InvEntregaKitComponente::STATUS_ENTREGADO)
            ->count();

        // Un componente con entrega parcial ya cuenta como avance del kit, aunque
        // todavía no esté completo.
        $conAvance = $this->componentes
            ->filter(fn (InvEntregaKitComponente $c) => $c->cantidad_entregada > 0)
            ->count();

        if ($entregados === $total) {
            $nuevoStatus = self::STATUS_COMPLETO;
        } elseif ($conAvance > 0) {
            $nuevoStatus = self::STATUS_PARCIAL;
        } else {
            $nuevoStatus = self::STATUS_PENDIENTE;
        }

        $this->update(['status' => $nuevoStatus]);
    }
}
