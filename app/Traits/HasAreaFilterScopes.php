<?php

namespace App\Traits;

trait HasAreaFilterScopes
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
        return $query->where(function ($q) use ($search) {
            $q->where('nombre', 'like', '%' . $search . '%');
        });
    }

    /**
     * Scope para filtrar por nombre de área.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $nombre
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByNombre($query, $nombre)
    {
        return $query->where('nombre', 'like', '%' . $nombre . '%');
    }

    /**
     * Scope para filtrar por sede específica.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $sedeId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySede($query, $sedeId)
    {
        return $query->whereHas('sedes', function ($q) use ($sedeId) {
            $q->where('sedes.id', $sedeId);
        });
    }

    /**
     * Scope para filtrar por población a través de las sedes.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $poblacionId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPoblacion($query, $poblacionId)
    {
        return $query->whereHas('sedes.poblacion', function ($q) use ($poblacionId) {
            $q->where('poblacions.id', $poblacionId);
        });
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
            ->when(isset($filters['sede_id']) && $filters['sede_id'], function ($q) use ($filters) {
                return $q->bySede($filters['sede_id']);
            })
            ->when(isset($filters['poblacion_id']) && $filters['poblacion_id'], function ($q) use ($filters) {
                return $q->byPoblacion($filters['poblacion_id']);
            })
            ->when(isset($filters['include_trashed']) && $filters['include_trashed'], function ($q) {
                return $q->withTrashed();
            })
            ->when(isset($filters['only_trashed']) && $filters['only_trashed'], function ($q) {
                return $q->onlyTrashed();
            });
    }
}
