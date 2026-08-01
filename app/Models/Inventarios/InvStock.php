<?php

namespace App\Models\Inventarios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo InvStock — nivel de existencias por almacén y producto simple.
 *
 * Fila única por (almacen_id, producto_id). Actualizada atómicamente
 * cada vez que se registra un movimiento de stock.
 *
 * @property int         $id
 * @property int         $almacen_id
 * @property int         $producto_id
 * @property int         $cantidad_total
 * @property int         $cantidad_reservada
 * @property int         $cantidad_disponible
 * @property string|null $ultimo_movimiento_at
 */
class InvStock extends Model
{
    public const CREATED_AT = null;

    protected $table = 'inv_stock';

    protected $guarded = ['id'];

    protected $casts = [
        'almacen_id'          => 'integer',
        'producto_id'         => 'integer',
        'cantidad_total'      => 'integer',
        'cantidad_reservada'  => 'integer',
        'cantidad_disponible' => 'integer',
        'ultimo_movimiento_at' => 'datetime',
        'updated_at'          => 'datetime',
    ];

    /**
     * Almacén donde se encuentra el stock.
     *
     * @return BelongsTo
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(InvAlmacen::class, 'almacen_id');
    }

    /**
     * Producto al que corresponde el stock.
     *
     * @return BelongsTo
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(InvProducto::class, 'producto_id');
    }

    /**
     * Scope para filtrar productos con stock disponible por debajo del punto de reorden.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBajoStock($query)
    {
        return $query->whereHas('producto', function ($q) {
            $q->whereColumn('inv_stock.cantidad_disponible', '<=', 'inv_productos.punto_reorden')
              ->where('inv_productos.punto_reorden', '>', 0);
        });
    }

    /**
     * Scope para filtrar por almacén.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $almacenId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByAlmacen($query, int $almacenId)
    {
        return $query->where('almacen_id', $almacenId);
    }

    /**
     * Scope para filtrar por producto.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $productoId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByProducto($query, int $productoId)
    {
        return $query->where('producto_id', $productoId);
    }

    /**
     * Scope para búsqueda por nombre o código del producto.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $search)
    {
        return $query->whereHas('producto', function ($q) use ($search) {
            $q->where('nombre', 'like', '%' . $search . '%')
              ->orWhere('codigo', 'like', '%' . $search . '%');
        });
    }

    /**
     * Aplica filtros dinámicos para el listado de stock.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn ($q) => $q->search($filters['search'])
            )
            ->when(
                isset($filters['almacen_id']) && $filters['almacen_id'] !== '',
                fn ($q) => $q->byAlmacen((int) $filters['almacen_id'])
            )
            ->when(
                isset($filters['producto_id']) && $filters['producto_id'] !== '',
                fn ($q) => $q->byProducto((int) $filters['producto_id'])
            )
            ->when(
                !empty($filters['bajo_stock']),
                fn ($q) => $q->bajoStock()
            );
    }
}
