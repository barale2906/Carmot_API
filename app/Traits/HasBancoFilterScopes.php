<?php

namespace App\Traits;

trait HasBancoFilterScopes
{
    /**
     * Scope de búsqueda por nombre o código.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nombre', 'like', '%' . $search . '%')
              ->orWhere('codigo', 'like', '%' . $search . '%');
        });
    }

    /**
     * Scope para filtrar por nombre (like).
     */
    public function scopeByNombre($query, string $nombre)
    {
        return $query->where('nombre', 'like', '%' . $nombre . '%');
    }

    /**
     * Scope para filtrar por status.
     */
    public function scopeByStatus($query, int $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope compuesto que aplica los filtros disponibles.
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(isset($filters['search']) && $filters['search'], fn ($q) => $q->search($filters['search']))
            ->when(isset($filters['nombre']) && $filters['nombre'], fn ($q) => $q->byNombre($filters['nombre']))
            ->when(isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '',
                fn ($q) => $q->byStatus((int) $filters['status']));
    }
}
