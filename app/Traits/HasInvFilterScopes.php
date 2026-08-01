<?php

namespace App\Traits;

/**
 * Trait HasInvFilterScopes
 *
 * Filtros comunes para los modelos del módulo Inventarios:
 * búsqueda por nombre y filtro por status.
 * Los modelos con filtros adicionales deben usar HasInvProductoFilterScopes.
 */
trait HasInvFilterScopes
{
    /**
     * Búsqueda por nombre.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('nombre', 'like', '%' . $search . '%');
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
     * Aplica filtros dinámicos: search y status.
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
            );
    }
}
