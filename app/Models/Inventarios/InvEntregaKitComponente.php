<?php

namespace App\Models\Inventarios;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo InvEntregaKitComponente — estado de entrega por componente de kit.
 *
 * Una fila por componente × entrega de kit.
 * Si el componente referencia un grupo, el cajero elige la variante (producto_entregado_id).
 *
 * @property int         $id
 * @property int         $entrega_kit_id
 * @property int         $kit_componente_id
 * @property int|null    $producto_entregado_id
 * @property int         $cantidad_solicitada
 * @property int         $cantidad_entregada
 * @property string      $status
 * @property string|null $fecha_entrega
 * @property int|null    $user_id
 */
class InvEntregaKitComponente extends Model
{
    public const STATUS_PENDIENTE = 'pendiente';
    public const STATUS_PARCIAL   = 'parcial';
    public const STATUS_ENTREGADO = 'entregado';

    protected $table = 'inv_entregas_kit_componente';

    protected $guarded = ['id'];

    protected $casts = [
        'entrega_kit_id'        => 'integer',
        'kit_componente_id'     => 'integer',
        'producto_entregado_id' => 'integer',
        'cantidad_solicitada'   => 'integer',
        'cantidad_entregada'    => 'integer',
        'fecha_entrega'         => 'datetime',
        'user_id'               => 'integer',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
    ];

    /**
     * Entrega de kit a la que pertenece este componente.
     *
     * @return BelongsTo
     */
    public function entregaKit(): BelongsTo
    {
        return $this->belongsTo(InvEntregaKit::class, 'entrega_kit_id');
    }

    /**
     * Componente del kit (definición).
     *
     * @return BelongsTo
     */
    public function kitComponente(): BelongsTo
    {
        return $this->belongsTo(InvKitComponente::class, 'kit_componente_id');
    }

    /**
     * Producto concreto (variante) entregado.
     *
     * @return BelongsTo
     */
    public function productoEntregado(): BelongsTo
    {
        return $this->belongsTo(InvProducto::class, 'producto_entregado_id');
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
}
