<?php

namespace App\Models\Inventarios;

use App\Models\Financiero\Lp\LpListaPrecio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo InvPrecioProducto — precio de venta de un producto en una lista de precios de inventario.
 *
 * Solo aplica a productos de tipo simple o kit (grupos no tienen precio).
 * Inmutable para pedidos ya creados; los cambios de precio afectan pedidos futuros.
 *
 * @property int        $id
 * @property int        $lista_precio_id
 * @property int        $producto_id
 * @property float      $precio
 * @property string|null $observaciones
 */
class InvPrecioProducto extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'inv_precios_producto';

    protected $guarded = ['id'];

    protected $casts = [
        'lista_precio_id' => 'integer',
        'producto_id'     => 'integer',
        'precio'          => 'decimal:2',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
        'deleted_at'      => 'datetime',
    ];

    /**
     * Lista de precios a la que pertenece este precio.
     *
     * @return BelongsTo
     */
    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(LpListaPrecio::class, 'lista_precio_id');
    }

    /**
     * Producto al que corresponde el precio.
     *
     * @return BelongsTo
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(InvProducto::class, 'producto_id');
    }

    /**
     * Busca el precio vigente de un producto para la población de una sede.
     * Retorna el primer precio encontrado en una lista activa asociada a esa población.
     *
     * @param int $productoId
     * @param int $poblacionId
     * @return self|null
     */
    public static function precioVigente(int $productoId, int $poblacionId): ?self
    {
        return static::where('producto_id', $productoId)
            ->whereNull('deleted_at')
            ->whereHas('listaPrecio', function ($q) use ($poblacionId) {
                $q->where('origen', 0)
                  ->where('status', LpListaPrecio::STATUS_ACTIVA)
                  ->where('fecha_inicio', '<=', today())
                  ->where('fecha_fin', '>=', today())
                  ->whereHas('poblaciones', fn ($pq) => $pq->where('poblacion_id', $poblacionId));
            })
            ->first();
    }
}
