<?php

namespace App\Traits;

trait HasActCategoriaFilterScopes
{
    /**
     * Scope de búsqueda por nombre.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('nombre', 'like', '%' . $search . '%');
    }

    /**
     * Scope compuesto que aplica los filtros disponibles.
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(! empty($filters['search']), fn ($q) => $q->search($filters['search']))
            ->when(isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null,
                fn ($q) => $q->where('status', (int) $filters['status']));
    }
}
