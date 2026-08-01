<?php

namespace App\Models\Inventarios;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo InvKitComponente — define los componentes de un kit.
 *
 * Cada fila indica que el kit (kit_id) contiene una cantidad determinada
 * de un producto simple o grupo (grupo_producto_id).
 *
 * @property int $id
 * @property int $kit_id
 * @property int $grupo_producto_id
 * @property int $cantidad
 * @property int $orden
 */
class InvKitComponente extends Model
{
    use HasFactory;

    protected $table = 'inv_kit_componentes';

    protected $guarded = ['id'];

    protected $casts = [
        'kit_id'             => 'integer',
        'grupo_producto_id'  => 'integer',
        'cantidad'           => 'integer',
        'orden'              => 'integer',
    ];

    /**
     * Kit al que pertenece este componente.
     *
     * @return BelongsTo
     */
    public function kit(): BelongsTo
    {
        return $this->belongsTo(InvProducto::class, 'kit_id');
    }

    /**
     * Producto (simple o grupo) que actúa como componente.
     *
     * @return BelongsTo
     */
    public function grupoProducto(): BelongsTo
    {
        return $this->belongsTo(InvProducto::class, 'grupo_producto_id');
    }
}
