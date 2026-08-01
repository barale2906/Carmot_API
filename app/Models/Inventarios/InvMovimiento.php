<?php

namespace App\Models\Inventarios;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Modelo InvMovimiento — línea de movimiento de stock (log inmutable).
 *
 * Cada registro representa el movimiento de un producto en un almacén.
 * Nunca se elimina ni edita; se anula mediante el documento padre.
 *
 * @property int         $id
 * @property int         $documento_id
 * @property int         $almacen_id
 * @property int|null    $almacen_destino_id
 * @property int         $producto_id
 * @property string      $tipo_movimiento
 * @property int         $cantidad
 * @property float|null  $precio_costo
 * @property string|null $referencia_type
 * @property int|null    $referencia_id
 * @property int         $user_id
 */
class InvMovimiento extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'inv_movimientos';

    protected $guarded = ['id'];

    protected $casts = [
        'documento_id'       => 'integer',
        'almacen_id'         => 'integer',
        'almacen_destino_id' => 'integer',
        'producto_id'        => 'integer',
        'cantidad'           => 'integer',
        'precio_costo'       => 'decimal:2',
        'referencia_id'      => 'integer',
        'user_id'            => 'integer',
        'created_at'         => 'datetime',
    ];

    /**
     * Documento de movimiento al que pertenece esta línea.
     *
     * @return BelongsTo
     */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(InvDocumentoMovimiento::class, 'documento_id');
    }

    /**
     * Almacén origen.
     *
     * @return BelongsTo
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(InvAlmacen::class, 'almacen_id');
    }

    /**
     * Almacén destino (solo traslados).
     *
     * @return BelongsTo
     */
    public function almacenDestino(): BelongsTo
    {
        return $this->belongsTo(InvAlmacen::class, 'almacen_destino_id');
    }

    /**
     * Producto afectado por el movimiento.
     *
     * @return BelongsTo
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(InvProducto::class, 'producto_id');
    }

    /**
     * Usuario que registró el movimiento.
     *
     * @return BelongsTo
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Referencia polimórfica hacia el origen del movimiento (EntregaSimple, etc.).
     *
     * @return MorphTo
     */
    public function referencia(): MorphTo
    {
        return $this->morphTo('referencia');
    }
}
