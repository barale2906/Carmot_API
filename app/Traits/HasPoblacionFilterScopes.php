<?php

namespace App\Traits;

trait HasPoblacionFilterScopes
{
    /**
     * Scope para filtrar por búsqueda de nombre.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('nombre', 'like', '%' . $search . '%');
    }

    /**
     * Scope para filtrar por país.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $pais
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPais($query, $pais)
    {
        return $query->where('pais', $pais);
    }

    /**
     * Scope para filtrar por provincia.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $provincia
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByProvincia($query, $provincia)
    {
        return $query->where('provincia', $provincia);
    }

    /**
     * Scope para aplicar múltiples filtros de manera dinámica.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(isset($filters['search']) && $filters['search'], function ($q) use ($filters) {
                return $q->search($filters['search']);
            })
            ->when(isset($filters['pais']) && $filters['pais'], function ($q) use ($filters) {
                return $q->byPais($filters['pais']);
            })
            ->when(isset($filters['provincia']) && $filters['provincia'], function ($q) use ($filters) {
                return $q->byProvincia($filters['provincia']);
            });
    }
}
