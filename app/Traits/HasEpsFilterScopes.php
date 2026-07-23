<?php

namespace App\Traits;

trait HasEpsFilterScopes
{
    /**
     * Scope para búsqueda por nombre o dirección.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nombre', 'like', '%' . $search . '%')
              ->orWhere('direccion', 'like', '%' . $search . '%');
        });
    }

    /**
     * Scope para filtrar por nombre exacto (like).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $nombre
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByNombre($query, string $nombre)
    {
        return $query->where('nombre', 'like', '%' . $nombre . '%');
    }

    /**
     * Scope para filtrar por status.
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
            ->when(isset($filters['nombre']) && $filters['nombre'], function ($q) use ($filters) {
                return $q->byNombre($filters['nombre']);
            })
            ->when(isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '', function ($q) use ($filters) {
                return $q->byStatus((int) $filters['status']);
            });
    }
}
