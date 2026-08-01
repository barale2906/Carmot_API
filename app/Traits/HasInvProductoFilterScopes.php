<?php

namespace App\Traits;

/**
 * Trait HasInvProductoFilterScopes
 *
 * Filtros específicos para InvProducto: extiende los filtros base del módulo
 * con búsqueda por código, tipo y categoría.
 */
trait HasInvProductoFilterScopes
{
    /**
     * Búsqueda por nombre o código.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nombre', 'like', '%' . $search . '%')
              ->orWhere('codigo', 'like', '%' . $search . '%');
        });
    }

    /**
     * Filtro exacto por status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, int $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Filtro por tipo de producto (simple, kit, grupo).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $tipo
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Filtro por categoría.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $categoriaId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCategoria($query, int $categoriaId)
    {
        return $query->where('categoria_id', $categoriaId);
    }

    /**
     * Aplica todos los filtros disponibles para productos.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(
                isset($filters['search']) && $filters['search'] !== '' && $filters['search'] !== null,
                fn ($q) => $q->search($filters['search'])
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null,
                fn ($q) => $q->byStatus((int) $filters['status'])
            )
            ->when(
                isset($filters['tipo']) && $filters['tipo'] !== '' && $filters['tipo'] !== null,
                fn ($q) => $q->byTipo($filters['tipo'])
            )
            ->when(
                isset($filters['categoria_id']) && $filters['categoria_id'] !== '' && $filters['categoria_id'] !== null,
                fn ($q) => $q->byCategoria((int) $filters['categoria_id'])
            );
    }
}
